<?php

namespace App\Services\Admin;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FeatureManagementService
{
    // ──────────────────────────────────────────────
    //  LIST
    // ──────────────────────────────────────────────

    public function list(array $filters = []): Collection
    {
        $query = Feature::query()
            ->with(['plans' => fn ($q) => $q->where('is_active', true)])
            ->withCount('plans');

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('slug', 'LIKE', "%{$term}%");
            });
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $sortBy  = $filters['sort_by'] ?? 'sort_order';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->get();
    }

    // ──────────────────────────────────────────────
    //  SHOW DETAIL
    // ──────────────────────────────────────────────

    public function getDetail(int $featureId): Feature
    {
        return Feature::with([
                'plans' => fn ($q) => $q->withCount([
                    'subscriptions as active_subscriptions_count' => fn ($sq) =>
                        $sq->whereIn('status', ['active', 'trialing']),
                ]),
            ])
            ->withCount('plans')
            ->findOrFail($featureId);
    }

    // ──────────────────────────────────────────────
    //  CREATE
    // ──────────────────────────────────────────────

    public function create(array $data): Feature
    {
        $feature = Feature::create([
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
            'type'        => $data['type'],
            'is_active'   => $data['is_active'] ?? true,
            'sort_order'  => $data['sort_order'] ?? 0,
            'metadata'    => $data['metadata'] ?? null,
        ]);

        $feature->loadCount('plans');

        return $feature;
    }

    // ──────────────────────────────────────────────
    //  UPDATE
    // ──────────────────────────────────────────────

    public function update(int $featureId, array $data): Feature
    {
        $feature = Feature::findOrFail($featureId);

        $fillable = ['name', 'slug', 'description', 'type', 'is_active', 'sort_order', 'metadata'];
        $updateData = array_intersect_key($data, array_flip($fillable));

        $feature->update($updateData);

        $feature->load('plans');
        $feature->loadCount('plans');

        return $feature;
    }

    // ──────────────────────────────────────────────
    //  DELETE
    // ──────────────────────────────────────────────

    public function delete(int $featureId): void
    {
        $feature = Feature::withCount('plans')->findOrFail($featureId);

        if ($feature->plans_count > 0) {
            abort(422, "Cannot delete feature \"{$feature->name}\" because it is linked to {$feature->plans_count} plan(s). Remove it from all plans first.");
        }

        $feature->delete();
    }

    // ──────────────────────────────────────────────
    //  TOGGLE ACTIVE
    // ──────────────────────────────────────────────

    public function toggleActive(int $featureId): Feature
    {
        $feature = Feature::findOrFail($featureId);
        $feature->update(['is_active' => ! $feature->is_active]);

        $feature->load('plans');
        $feature->loadCount('plans');

        return $feature;
    }

    // ──────────────────────────────────────────────
    //  ASSIGN TO PLANS
    // ──────────────────────────────────────────────

    public function assignToPlans(int $featureId, array $plansData): Feature
    {
        return DB::transaction(function () use ($featureId, $plansData) {
            $feature = Feature::findOrFail($featureId);

            $syncData = [];
            foreach ($plansData as $planItem) {
                $planId = $planItem['plan_id'];
                $syncData[$planId] = [
                    'is_enabled'      => $planItem['is_enabled'] ?? true,
                    'usage_limit'     => $planItem['usage_limit'] ?? null,
                    'limit_period'    => $planItem['limit_period'] ?? 'month',
                    'credits_per_use' => $planItem['credits_per_use'] ?? 1,
                    'constraints'     => isset($planItem['constraints'])
                        ? json_encode($planItem['constraints'])
                        : null,
                ];
            }

            $feature->plans()->sync($syncData);

            $feature->load('plans');
            $feature->loadCount('plans');

            return $feature;
        });
    }
}
