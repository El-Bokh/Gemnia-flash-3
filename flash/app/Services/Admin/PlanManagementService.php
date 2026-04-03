<?php

namespace App\Services\Admin;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\UserLimit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanManagementService
{
    // ──────────────────────────────────────────────
    //  LIST
    // ──────────────────────────────────────────────

    public function list(array $filters = []): Collection
    {
        $query = Plan::query()
            ->with(['features'])
            ->withCount([
                'features',
                'subscriptions',
                'subscriptions as active_subscriptions_count' => fn ($q) =>
                    $q->whereIn('status', ['active', 'trialing']),
            ]);

        if (! empty($filters['with_trashed'])) {
            $query->withTrashed();
        }

        // Search
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('slug', 'LIKE', "%{$term}%")
                  ->orWhere('description', 'LIKE', "%{$term}%");
            });
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Filter free plans
        if (isset($filters['is_free'])) {
            $query->where('is_free', (bool) $filters['is_free']);
        }

        // Sorting
        $sortBy  = $filters['sort_by'] ?? 'sort_order';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->get();
    }

    // ──────────────────────────────────────────────
    //  SHOW DETAIL
    // ──────────────────────────────────────────────

    public function getDetail(int $planId): Plan
    {
        return Plan::withTrashed()
            ->with([
                'features' => fn ($q) => $q->orderBy('sort_order'),
                'subscriptions' => fn ($q) => $q->with('user:id,name,email,avatar')
                    ->latest()
                    ->limit(10),
            ])
            ->withCount([
                'features',
                'subscriptions',
                'subscriptions as active_subscriptions_count' => fn ($q) =>
                    $q->whereIn('status', ['active', 'trialing']),
            ])
            ->findOrFail($planId);
    }

    // ──────────────────────────────────────────────
    //  CREATE
    // ──────────────────────────────────────────────

    public function create(array $data): Plan
    {
        return DB::transaction(function () use ($data) {
            $plan = Plan::create([
                'name'            => $data['name'],
                'slug'            => $data['slug'],
                'description'     => $data['description'] ?? null,
                'price_monthly'   => $data['price_monthly'],
                'price_yearly'    => $data['price_yearly'] ?? $data['price_monthly'] * 10,
                'currency'        => $data['currency'] ?? 'USD',
                'credits_monthly' => $data['credits_monthly'],
                'credits_yearly'  => $data['credits_yearly'] ?? $data['credits_monthly'] * 12,
                'is_free'         => $data['is_free'] ?? false,
                'is_active'       => $data['is_active'] ?? true,
                'is_featured'     => $data['is_featured'] ?? false,
                'sort_order'      => $data['sort_order'] ?? 0,
                'trial_days'      => $data['trial_days'] ?? 0,
                'metadata'        => $data['metadata'] ?? null,
            ]);

            // Attach features with limits
            if (! empty($data['features'])) {
                $this->syncFeatures($plan, $data['features']);
            }

            $plan->load('features');
            $plan->loadCount(['features', 'subscriptions']);

            return $plan;
        });
    }

    // ──────────────────────────────────────────────
    //  UPDATE
    // ──────────────────────────────────────────────

    public function update(int $planId, array $data): Plan
    {
        return DB::transaction(function () use ($planId, $data) {
            $plan = Plan::findOrFail($planId);

            $fillable = [
                'name', 'slug', 'description',
                'price_monthly', 'price_yearly', 'currency',
                'credits_monthly', 'credits_yearly',
                'is_free', 'is_active', 'is_featured',
                'sort_order', 'trial_days', 'metadata',
            ];

            $updateData = array_intersect_key($data, array_flip($fillable));
            $plan->update($updateData);

            // Sync features if provided
            if (array_key_exists('features', $data)) {
                $this->syncFeatures($plan, $data['features'] ?? []);

                // Propagate limits to active subscribers
                $this->propagateLimitsToSubscribers($plan);
            }

            $plan->load('features');
            $plan->loadCount([
                'features',
                'subscriptions',
                'subscriptions as active_subscriptions_count' => fn ($q) =>
                    $q->whereIn('status', ['active', 'trialing']),
            ]);

            return $plan;
        });
    }

    // ──────────────────────────────────────────────
    //  DELETE (SOFT)
    // ──────────────────────────────────────────────

    public function delete(int $planId): void
    {
        $plan = Plan::withCount([
            'subscriptions as active_subscriptions_count' => fn ($q) =>
                $q->whereIn('status', ['active', 'trialing']),
        ])->findOrFail($planId);

        if ($plan->active_subscriptions_count > 0) {
            abort(422, "Cannot delete plan \"{$plan->name}\" because it has {$plan->active_subscriptions_count} active subscription(s). Migrate users to another plan first.");
        }

        $plan->delete();
    }

    // ──────────────────────────────────────────────
    //  FORCE DELETE
    // ──────────────────────────────────────────────

    public function forceDelete(int $planId): void
    {
        $plan = Plan::withTrashed()
            ->withCount('subscriptions')
            ->findOrFail($planId);

        if ($plan->subscriptions_count > 0) {
            abort(422, "Cannot permanently delete plan \"{$plan->name}\" because it has {$plan->subscriptions_count} total subscription(s). Remove all subscriptions first.");
        }

        DB::transaction(function () use ($plan) {
            $plan->planFeatures()->delete();
            $plan->forceDelete();
        });
    }

    // ──────────────────────────────────────────────
    //  RESTORE
    // ──────────────────────────────────────────────

    public function restore(int $planId): Plan
    {
        $plan = Plan::withTrashed()->findOrFail($planId);
        $plan->restore();

        $plan->load('features');
        $plan->loadCount(['features', 'subscriptions']);

        return $plan;
    }

    // ──────────────────────────────────────────────
    //  DUPLICATE PLAN
    // ──────────────────────────────────────────────

    public function duplicate(int $planId): Plan
    {
        return DB::transaction(function () use ($planId) {
            $original = Plan::with('planFeatures')->findOrFail($planId);

            // Generate unique slug
            $baseSlug = $original->slug . '_copy';
            $slug = $baseSlug;
            $counter = 1;
            while (Plan::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '_' . $counter;
                $counter++;
            }

            $clone = $original->replicate();
            $clone->name = $original->name . ' (Copy)';
            $clone->slug = $slug;
            $clone->is_active = false; // Start as inactive
            $clone->save();

            // Copy feature limits
            foreach ($original->planFeatures as $pf) {
                PlanFeature::create([
                    'plan_id'         => $clone->id,
                    'feature_id'      => $pf->feature_id,
                    'is_enabled'      => $pf->is_enabled,
                    'usage_limit'     => $pf->usage_limit,
                    'limit_period'    => $pf->limit_period,
                    'credits_per_use' => $pf->credits_per_use,
                    'constraints'     => $pf->constraints,
                ]);
            }

            $clone->load('features');
            $clone->loadCount(['features', 'subscriptions']);

            return $clone;
        });
    }

    // ──────────────────────────────────────────────
    //  SYNC FEATURES (assign/update limits)
    // ──────────────────────────────────────────────

    public function syncPlanFeatures(int $planId, array $featuresData): Plan
    {
        return DB::transaction(function () use ($planId, $featuresData) {
            $plan = Plan::findOrFail($planId);

            $this->syncFeatures($plan, $featuresData);

            // Propagate new limits to active subscribers
            $this->propagateLimitsToSubscribers($plan);

            $plan->load('features');
            $plan->loadCount(['features', 'subscriptions']);

            return $plan;
        });
    }

    // ──────────────────────────────────────────────
    //  UPDATE SINGLE FEATURE LIMIT
    // ──────────────────────────────────────────────

    public function updateFeatureLimit(int $planId, int $featureId, array $data): PlanFeature
    {
        $planFeature = PlanFeature::where('plan_id', $planId)
            ->where('feature_id', $featureId)
            ->firstOrFail();

        $fillable = ['is_enabled', 'usage_limit', 'limit_period', 'credits_per_use', 'constraints'];
        $updateData = array_intersect_key($data, array_flip($fillable));

        $planFeature->update($updateData);

        // Propagate this specific limit change to active subscribers
        $plan = Plan::findOrFail($planId);
        $this->propagateLimitsToSubscribers($plan);

        return $planFeature->fresh(['feature']);
    }

    // ──────────────────────────────────────────────
    //  TOGGLE PLAN STATUS
    // ──────────────────────────────────────────────

    public function toggleActive(int $planId): Plan
    {
        $plan = Plan::findOrFail($planId);
        $plan->update(['is_active' => ! $plan->is_active]);

        $plan->load('features');
        $plan->loadCount(['features', 'subscriptions']);

        return $plan;
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS / COMPARISON
    // ──────────────────────────────────────────────

    public function getComparison(): array
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->with(['features' => fn ($q) => $q->orderBy('sort_order')])
            ->withCount([
                'subscriptions as active_subscriptions_count' => fn ($q) =>
                    $q->whereIn('status', ['active', 'trialing']),
            ])
            ->orderBy('sort_order')
            ->get();

        $allFeatures = \App\Models\Feature::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'type']);

        $matrix = [];
        foreach ($plans as $plan) {
            $planFeatures = $plan->features->keyBy('id');

            $matrix[] = [
                'plan' => [
                    'id'              => $plan->id,
                    'name'            => $plan->name,
                    'slug'            => $plan->slug,
                    'price_monthly'   => (float) $plan->price_monthly,
                    'price_yearly'    => (float) $plan->price_yearly,
                    'credits_monthly' => $plan->credits_monthly,
                    'is_free'         => $plan->is_free,
                    'is_featured'     => $plan->is_featured,
                    'trial_days'      => $plan->trial_days,
                    'active_subscribers' => $plan->active_subscriptions_count,
                ],
                'features' => $allFeatures->map(function ($feature) use ($planFeatures) {
                    $pf = $planFeatures->get($feature->id);

                    return [
                        'id'              => $feature->id,
                        'name'            => $feature->name,
                        'slug'            => $feature->slug,
                        'type'            => $feature->type,
                        'included'        => $pf !== null,
                        'is_enabled'      => $pf ? (bool) $pf->pivot->is_enabled : false,
                        'usage_limit'     => $pf?->pivot->usage_limit,
                        'limit_period'    => $pf?->pivot->limit_period,
                        'credits_per_use' => $pf?->pivot->credits_per_use,
                    ];
                })->toArray(),
            ];
        }

        return [
            'plans'    => $matrix,
            'features' => $allFeatures->toArray(),
        ];
    }

    // ──────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────

    private function syncFeatures(Plan $plan, array $featuresData): void
    {
        $syncData = [];
        foreach ($featuresData as $featureItem) {
            $featureId = $featureItem['feature_id'];
            $syncData[$featureId] = [
                'is_enabled'      => $featureItem['is_enabled'] ?? true,
                'usage_limit'     => $featureItem['usage_limit'] ?? null,
                'limit_period'    => $featureItem['limit_period'] ?? 'month',
                'credits_per_use' => $featureItem['credits_per_use'] ?? 1,
                'constraints'     => isset($featureItem['constraints'])
                    ? json_encode($featureItem['constraints'])
                    : null,
            ];
        }

        $plan->features()->sync($syncData);
    }

    /**
     * When a plan's feature limits change, update the user_limits
     * for all users with active subscriptions on this plan.
     */
    private function propagateLimitsToSubscribers(Plan $plan): void
    {
        $plan->load('planFeatures.feature');

        // Get all user IDs with active subscriptions on this plan
        $userIds = Subscription::where('plan_id', $plan->id)
            ->whereIn('status', ['active', 'trialing'])
            ->pluck('user_id')
            ->unique();

        if ($userIds->isEmpty()) {
            return;
        }

        foreach ($plan->planFeatures as $pf) {
            if (! $pf->feature || ! $pf->is_enabled) {
                continue;
            }

            $limitType = $pf->feature->slug;

            // Map plan_features.limit_period to user_limits.period
            $periodMap = [
                'day'      => 'day',
                'week'     => 'week',
                'month'    => 'month',
                'year'     => 'month', // approximate as month
                'lifetime' => 'month', // tracked monthly
            ];

            $period = $periodMap[$pf->limit_period] ?? 'month';

            foreach ($userIds as $userId) {
                UserLimit::updateOrCreate(
                    [
                        'user_id'    => $userId,
                        'limit_type' => $limitType,
                    ],
                    [
                        'max_requests' => $pf->usage_limit ?? 999999,
                        'period'       => $period,
                        'is_active'    => true,
                    ]
                );
            }
        }
    }
}
