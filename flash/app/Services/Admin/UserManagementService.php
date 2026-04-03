<?php

namespace App\Services\Admin;

use App\Models\AiRequest;
use App\Models\CreditLedger;
use App\Models\GeneratedImage;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserManagementService
{
    // ──────────────────────────────────────────────
    //  LIST / SEARCH / FILTER
    // ──────────────────────────────────────────────

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = User::query()
            ->with([
                'roles',
                'subscriptions' => fn ($q) => $q->with('plan:id,name,slug')
                    ->whereIn('status', ['active', 'trialing'])
                    ->latest(),
            ]);

        // Include soft-deleted users
        if (! empty($filters['with_trashed'])) {
            $query->withTrashed();
        }

        // Full-text search across name and email
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('email', 'LIKE', "%{$term}%")
                  ->orWhere('phone', 'LIKE', "%{$term}%");
            });
        }

        // Filter by user status
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by role slug
        if (! empty($filters['role'])) {
            $query->whereHas('roles', fn (Builder $q) => $q->where('slug', $filters['role']));
        }

        // Filter by plan slug (users who have an active subscription on this plan)
        if (! empty($filters['plan'])) {
            $query->whereHas('subscriptions', function (Builder $q) use ($filters) {
                $q->whereHas('plan', fn (Builder $pq) => $pq->where('slug', $filters['plan']));
                $q->whereIn('status', ['active', 'trialing']);
            });
        }

        // Filter by subscription status
        if (! empty($filters['subscription_status'])) {
            $query->whereHas('subscriptions', fn (Builder $q) =>
                $q->where('status', $filters['subscription_status'])
            );
        }

        // Sorting
        $sortBy  = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->paginate($perPage);
    }

    // ──────────────────────────────────────────────
    //  SHOW DETAIL
    // ──────────────────────────────────────────────

    public function getDetail(int $userId): User
    {
        return User::withTrashed()
            ->with([
                'roles',
                'subscriptions.plan:id,name,slug',
                'aiRequests' => fn ($q) => $q->with('visualStyle:id,name')->latest()->limit(10),
                'generatedImages' => fn ($q) => $q->latest()->limit(10),
                'payments' => fn ($q) => $q->latest()->limit(10),
                'creditLedgers' => fn ($q) => $q->latest()->limit(10),
            ])
            ->withCount('aiRequests')
            ->withCount(['aiRequests as ai_requests_completed_count' => fn ($q) => $q->where('status', 'completed')])
            ->withCount(['aiRequests as ai_requests_failed_count' => fn ($q) => $q->where('status', 'failed')])
            ->withCount(['aiRequests as ai_requests_pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->withCount('generatedImages')
            ->withCount('payments')
            ->withSum(['payments as payments_total' => fn ($q) => $q->where('status', 'completed')], 'net_amount')
            ->addSelect([
                'credit_balance' => CreditLedger::select('balance_after')
                    ->whereColumn('user_id', 'users.id')
                    ->latest()
                    ->limit(1),
            ])
            ->findOrFail($userId);
    }

    // ──────────────────────────────────────────────
    //  CREATE
    // ──────────────────────────────────────────────

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'phone'    => $data['phone'] ?? null,
                'avatar'   => $data['avatar'] ?? null,
                'status'   => $data['status'] ?? 'active',
                'locale'   => $data['locale'] ?? 'en',
                'timezone' => $data['timezone'] ?? 'UTC',
            ]);

            // Assign roles
            if (! empty($data['roles'])) {
                $user->roles()->sync($data['roles']);
            } else {
                // Default: assign the default "user" role
                $defaultRole = Role::where('is_default', true)->first();
                if ($defaultRole) {
                    $user->roles()->attach($defaultRole->id);
                }
            }

            // Optionally create an initial subscription
            if (! empty($data['plan_id'])) {
                $this->createInitialSubscription($user, $data['plan_id'], $data['billing_cycle'] ?? 'monthly');
            }

            $user->load('roles', 'subscriptions.plan');

            return $user;
        });
    }

    private function createInitialSubscription(User $user, int $planId, string $billingCycle): Subscription
    {
        $plan = Plan::findOrFail($planId);

        $price   = $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
        $credits = $billingCycle === 'yearly' ? $plan->credits_yearly : $plan->credits_monthly;

        $subscription = Subscription::create([
            'user_id'           => $user->id,
            'plan_id'           => $plan->id,
            'billing_cycle'     => $billingCycle,
            'status'            => $plan->trial_days > 0 ? 'trialing' : 'active',
            'price'             => $price,
            'currency'          => $plan->currency ?? 'USD',
            'trial_starts_at'   => $plan->trial_days > 0 ? now() : null,
            'trial_ends_at'     => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
            'starts_at'         => now(),
            'ends_at'           => $billingCycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth(),
            'credits_remaining' => $credits,
            'credits_total'     => $credits,
            'auto_renew'        => true,
        ]);

        // Credit ledger entry
        CreditLedger::create([
            'user_id'         => $user->id,
            'subscription_id' => $subscription->id,
            'type'            => 'credit',
            'amount'          => $credits,
            'balance_after'   => $credits,
            'source'          => 'subscription',
            'reference_type'  => Subscription::class,
            'reference_id'    => $subscription->id,
            'description'     => "Initial credits from {$plan->name} plan ({$billingCycle})",
        ]);

        return $subscription;
    }

    // ──────────────────────────────────────────────
    //  UPDATE
    // ──────────────────────────────────────────────

    public function update(int $userId, array $data): User
    {
        return DB::transaction(function () use ($userId, $data) {
            $user = User::findOrFail($userId);

            // Core fields
            $fillable = ['name', 'email', 'phone', 'avatar', 'status', 'locale', 'timezone'];
            $updateData = array_intersect_key($data, array_flip($fillable));

            if (! empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $user->update($updateData);

            // Sync roles if provided
            if (array_key_exists('roles', $data)) {
                $user->roles()->sync($data['roles'] ?? []);
            }

            $user->load('roles', 'subscriptions.plan');

            return $user;
        });
    }

    // ──────────────────────────────────────────────
    //  ASSIGN ROLE
    // ──────────────────────────────────────────────

    public function assignRoles(int $userId, array $roleIds): User
    {
        $user = User::findOrFail($userId);
        $user->roles()->sync($roleIds);
        $user->load('roles');

        return $user;
    }

    // ──────────────────────────────────────────────
    //  RESET PASSWORD
    // ──────────────────────────────────────────────

    public function resetPassword(int $userId, string $password, bool $revokeTokens = false): void
    {
        $user = User::findOrFail($userId);
        $user->update([
            'password' => Hash::make($password),
        ]);

        if ($revokeTokens) {
            $user->tokens()->delete();
        }
    }

    // ──────────────────────────────────────────────
    //  DELETE / ARCHIVE
    // ──────────────────────────────────────────────

    public function archive(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $user = User::findOrFail($userId);

            // Soft-delete related AI requests
            AiRequest::where('user_id', $userId)->delete();

            // Soft-delete related generated images
            GeneratedImage::where('user_id', $userId)->delete();

            // Cancel active subscriptions
            Subscription::where('user_id', $userId)
                ->whereIn('status', ['active', 'trialing', 'paused'])
                ->update([
                    'status'       => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'User archived by admin',
                ]);

            // Log final credit balance
            $lastLedger = CreditLedger::where('user_id', $userId)
                ->latest()
                ->first();

            if ($lastLedger && $lastLedger->balance_after > 0) {
                CreditLedger::create([
                    'user_id'        => $userId,
                    'type'           => 'debit',
                    'amount'         => -$lastLedger->balance_after,
                    'balance_after'  => 0,
                    'source'         => 'admin_archive',
                    'description'    => 'Credits zeroed out — user archived by admin',
                ]);
            }

            // Revoke all API tokens
            $user->tokens()->delete();

            // Soft-delete the user
            $user->delete();
        });
    }

    public function forceDelete(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $user = User::withTrashed()->findOrFail($userId);

            // Hard-delete AI requests (cascade will remove generated_images)
            AiRequest::withTrashed()->where('user_id', $userId)->forceDelete();

            // Hard-delete generated images
            GeneratedImage::withTrashed()->where('user_id', $userId)->forceDelete();

            // Subscriptions
            Subscription::withTrashed()->where('user_id', $userId)->forceDelete();

            // Credit ledger
            CreditLedger::where('user_id', $userId)->delete();

            // Tokens
            $user->tokens()->delete();

            // Roles pivot
            $user->roles()->detach();

            // Hard-delete user
            $user->forceDelete();
        });
    }

    public function restore(int $userId): User
    {
        $user = User::withTrashed()->findOrFail($userId);
        $user->restore();

        // Restore the related soft-deleted records
        AiRequest::withTrashed()->where('user_id', $userId)->restore();
        GeneratedImage::withTrashed()->where('user_id', $userId)->restore();

        $user->load('roles', 'subscriptions.plan');

        return $user;
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS
    // ──────────────────────────────────────────────

    public function getAggregations(): array
    {
        // Users per role
        $usersPerRole = Role::withCount('users')
            ->get()
            ->map(fn ($r) => [
                'role'  => $r->name,
                'slug'  => $r->slug,
                'count' => $r->users_count,
            ])
            ->toArray();

        // Users per plan (active subscriptions)
        $usersPerPlan = Plan::query()
            ->select('plans.id', 'plans.name', 'plans.slug')
            ->withCount(['subscriptions as users_count' => fn ($q) =>
                $q->whereIn('status', ['active', 'trialing'])
            ])
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        // Users per status
        $usersPerStatus = User::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Registration trend (last 30 days)
        $registrationTrend = User::query()
            ->where('created_at', '>=', Carbon::now()->subDays(29)->startOfDay())
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        return [
            'total_users'       => User::count(),
            'users_per_role'    => $usersPerRole,
            'users_per_plan'    => $usersPerPlan,
            'users_per_status'  => $usersPerStatus,
            'registration_trend'=> $registrationTrend,
        ];
    }

    // ──────────────────────────────────────────────
    //  USER AI REQUESTS (separate paginated view)
    // ──────────────────────────────────────────────

    public function getUserAiRequests(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return AiRequest::with('visualStyle:id,name')
            ->where('user_id', $userId)
            ->select([
                'id', 'uuid', 'user_id', 'visual_style_id',
                'type', 'status', 'user_prompt', 'model_used',
                'credits_consumed', 'processing_time_ms',
                'created_at',
            ])
            ->latest()
            ->paginate($perPage);
    }

    // ──────────────────────────────────────────────
    //  USER GENERATED IMAGES (separate paginated view)
    // ──────────────────────────────────────────────

    public function getUserGeneratedImages(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return GeneratedImage::where('user_id', $userId)
            ->select([
                'id', 'uuid', 'ai_request_id', 'file_path',
                'file_name', 'width', 'height', 'file_size',
                'is_public', 'is_favorite', 'is_nsfw',
                'created_at',
            ])
            ->latest()
            ->paginate($perPage);
    }
}
