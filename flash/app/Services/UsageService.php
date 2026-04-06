<?php

namespace App\Services;

use App\Models\CreditLedger;
use App\Models\Feature;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsageService
{
    /**
     * Get the user's active subscription (active or trialing).
     */
    public function getActiveSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();
    }

    /**
     * Check if the user can consume `$cost` credits.
     * Returns an array with status info.
     *
     * @return array{allowed: bool, subscription: ?Subscription, remaining: int, total: int, reason: ?string}
     */
    public function checkQuota(User $user, int $cost = 1): array
    {
        $subscription = $this->getActiveSubscription($user);

        if (! $subscription) {
            return [
                'allowed'      => false,
                'subscription' => null,
                'remaining'    => 0,
                'total'        => 0,
                'reason'       => 'no_subscription',
            ];
        }

        if ($subscription->credits_remaining < $cost) {
            return [
                'allowed'      => false,
                'subscription' => $subscription,
                'remaining'    => $subscription->credits_remaining,
                'total'        => $subscription->credits_total,
                'reason'       => 'insufficient_credits',
            ];
        }

        return [
            'allowed'      => true,
            'subscription' => $subscription,
            'remaining'    => $subscription->credits_remaining,
            'total'        => $subscription->credits_total,
            'reason'       => null,
        ];
    }

    /**
     * Atomically consume credits — uses DB row-level locking to prevent race conditions.
     *
     * @return array{success: bool, subscription: ?Subscription, remaining: int, reason: ?string}
     */
    public function consume(User $user, int $cost = 1, ?string $action = null, array $meta = []): array
    {
        return DB::transaction(function () use ($user, $cost, $action, $meta) {
            // Lock the subscription row for update (SELECT ... FOR UPDATE)
            $subscription = $user->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->where(function ($q) {
                    $q->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
                })
                ->latest('starts_at')
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                return [
                    'success'      => false,
                    'subscription' => null,
                    'remaining'    => 0,
                    'reason'       => 'no_subscription',
                ];
            }

            if ($subscription->credits_remaining < $cost) {
                return [
                    'success'      => false,
                    'subscription' => $subscription,
                    'remaining'    => $subscription->credits_remaining,
                    'reason'       => 'insufficient_credits',
                ];
            }

            // Deduct credits atomically
            $subscription->decrement('credits_remaining', $cost);
            $subscription->refresh();

            // Record in credit ledger (audit trail)
            CreditLedger::create([
                'user_id'        => $user->id,
                'subscription_id'=> $subscription->id,
                'type'           => 'debit',
                'amount'         => $cost,
                'balance_after'  => $subscription->credits_remaining,
                'source'         => 'usage',
                'description'    => $action ?? 'AI request',
                'metadata'       => $meta ?: null,
            ]);

            // Record in usage log
            UsageLog::create([
                'user_id'         => $user->id,
                'subscription_id' => $subscription->id,
                'feature_id'      => $meta['feature_id'] ?? null,
                'action'          => $action ?? 'chat_message',
                'credits_used'    => $cost,
                'ip_address'      => request()->ip(),
                'user_agent'      => request()->userAgent(),
                'metadata'        => $meta ?: null,
            ]);

            Log::info('Usage consumed', [
                'user_id'     => $user->id,
                'cost'        => $cost,
                'remaining'   => $subscription->credits_remaining,
                'action'      => $action,
            ]);

            return [
                'success'      => true,
                'subscription' => $subscription,
                'remaining'    => $subscription->credits_remaining,
                'reason'       => null,
            ];
        });
    }

    /**
     * Refund credits back to the user (e.g. when an AI request fails).
     * Uses DB row-level locking for consistency.
     */
    public function refund(User $user, int $amount = 1, ?string $reason = null, array $meta = []): array
    {
        return DB::transaction(function () use ($user, $amount, $reason, $meta) {
            $subscription = $user->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->where(function ($q) {
                    $q->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
                })
                ->latest('starts_at')
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                return [
                    'success'      => false,
                    'subscription' => null,
                    'remaining'    => 0,
                    'reason'       => 'no_subscription',
                ];
            }

            // Add credits back (never exceed total)
            $newRemaining = min(
                $subscription->credits_remaining + $amount,
                $subscription->credits_total
            );
            $actualRefund = $newRemaining - $subscription->credits_remaining;

            $subscription->update(['credits_remaining' => $newRemaining]);

            // Record refund in credit ledger
            CreditLedger::create([
                'user_id'         => $user->id,
                'subscription_id' => $subscription->id,
                'type'            => 'credit',
                'amount'          => $actualRefund,
                'balance_after'   => $newRemaining,
                'source'          => 'refund',
                'description'     => $reason ?? 'Automatic refund',
                'metadata'        => $meta ?: null,
            ]);

            Log::info('Credits refunded', [
                'user_id'     => $user->id,
                'amount'      => $actualRefund,
                'remaining'   => $newRemaining,
                'reason'      => $reason,
            ]);

            return [
                'success'      => true,
                'subscription' => $subscription,
                'remaining'    => $newRemaining,
                'reason'       => null,
            ];
        });
    }

    /**
     * Compute the warning level from remaining/total credits.
     * Single source of truth — used by both getUsageStats and ConversationController.
     */
    public function computeWarningLevel(int $remaining, int $total): string
    {
        if ($remaining <= 0) {
            return 'depleted';
        }

        $used = $total - $remaining;
        $percentage = $total > 0 ? ($used / $total) * 100 : 0;

        if ($percentage >= 90) {
            return 'critical';
        }
        if ($percentage >= 80) {
            return 'low';
        }

        return 'none';
    }

    /**
     * Check if a user can use a specific feature based on plan-level limits.
     * This checks per-feature usage_limit + limit_period (e.g. 10 requests/day).
     *
     * @return array{allowed: bool, reason: ?string, used: int, limit: ?int, period: ?string}
     */
    public function checkFeatureLimit(User $user, string $featureSlug): array
    {
        $subscription = $this->getActiveSubscription($user);

        if (! $subscription) {
            return [
                'allowed' => false,
                'reason'  => 'no_subscription',
                'used'    => 0,
                'limit'   => 0,
                'period'  => null,
            ];
        }

        $feature = Feature::where('slug', $featureSlug)->where('is_active', true)->first();

        if (! $feature) {
            // Unknown feature — allow (don't block for unconfigured features)
            return ['allowed' => true, 'reason' => null, 'used' => 0, 'limit' => null, 'period' => null];
        }

        $planFeature = PlanFeature::where('plan_id', $subscription->plan_id)
            ->where('feature_id', $feature->id)
            ->first();

        // Feature not assigned to this plan → disabled
        if (! $planFeature) {
            return [
                'allowed' => false,
                'reason'  => 'feature_not_available',
                'used'    => 0,
                'limit'   => 0,
                'period'  => null,
            ];
        }

        // Feature explicitly disabled
        if (! $planFeature->is_enabled) {
            return [
                'allowed' => false,
                'reason'  => 'feature_disabled',
                'used'    => 0,
                'limit'   => 0,
                'period'  => $planFeature->limit_period,
            ];
        }

        // Unlimited usage
        if ($planFeature->usage_limit === null) {
            return ['allowed' => true, 'reason' => null, 'used' => 0, 'limit' => null, 'period' => $planFeature->limit_period];
        }

        // Count usage within the period
        $periodStart = $this->getPeriodStart($planFeature->limit_period);

        $usedCount = UsageLog::where('user_id', $user->id)
            ->where('subscription_id', $subscription->id)
            ->where('feature_id', $feature->id)
            ->where('created_at', '>=', $periodStart)
            ->count();

        if ($usedCount >= $planFeature->usage_limit) {
            return [
                'allowed' => false,
                'reason'  => 'feature_limit_reached',
                'used'    => $usedCount,
                'limit'   => $planFeature->usage_limit,
                'period'  => $planFeature->limit_period,
            ];
        }

        return [
            'allowed' => true,
            'reason'  => null,
            'used'    => $usedCount,
            'limit'   => $planFeature->usage_limit,
            'period'  => $planFeature->limit_period,
        ];
    }

    /**
     * Get the start of the current billing period for a given limit_period.
     */
    private function getPeriodStart(string $limitPeriod): \Carbon\Carbon
    {
        return match ($limitPeriod) {
            'day'      => now()->startOfDay(),
            'week'     => now()->startOfWeek(),
            'month'    => now()->startOfMonth(),
            'year'     => now()->startOfYear(),
            'lifetime' => now()->subYears(100), // effectively no reset
            default    => now()->startOfMonth(),
        };
    }

    /**
     * Get usage statistics for the current billing period.
     */
    public function getUsageStats(User $user): array
    {
        $subscription = $this->getActiveSubscription($user);

        if (! $subscription) {
            return [
                'has_subscription' => false,
                'plan_name'        => null,
                'credits_remaining'=> 0,
                'credits_total'    => 0,
                'usage_percentage' => 100,
                'period_start'     => null,
                'period_end'       => null,
                'requests_today'   => 0,
                'requests_this_month' => 0,
                'warning_level'    => 'depleted', // none, low, critical, depleted
            ];
        }

        $subscription->load('plan');

        $used = $subscription->credits_total - $subscription->credits_remaining;
        $percentage = $subscription->credits_total > 0
            ? round(($used / $subscription->credits_total) * 100, 1)
            : 0;

        $requestsToday = UsageLog::where('user_id', $user->id)
            ->where('subscription_id', $subscription->id)
            ->whereDate('created_at', today())
            ->count();

        $requestsThisMonth = UsageLog::where('user_id', $user->id)
            ->where('subscription_id', $subscription->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $warningLevel = $this->computeWarningLevel(
            $subscription->credits_remaining,
            $subscription->credits_total
        );

        return [
            'has_subscription'    => true,
            'plan_name'           => $subscription->plan->name ?? 'Unknown',
            'plan_slug'           => $subscription->plan->slug ?? null,
            'plan_is_free'        => $subscription->plan->is_free ?? true,
            'credits_remaining'   => $subscription->credits_remaining,
            'credits_total'       => $subscription->credits_total,
            'credits_used'        => $used,
            'usage_percentage'    => $percentage,
            'period_start'        => $subscription->starts_at?->toIso8601String(),
            'period_end'          => $subscription->ends_at?->toIso8601String(),
            'status'              => $subscription->status,
            'requests_today'      => $requestsToday,
            'requests_this_month' => $requestsThisMonth,
            'warning_level'       => $warningLevel,
        ];
    }
}
