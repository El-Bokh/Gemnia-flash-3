<?php

namespace App\Services;

use App\Models\CreditLedger;
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

        $warningLevel = 'none';
        if ($subscription->credits_remaining <= 0) {
            $warningLevel = 'depleted';
        } elseif ($percentage >= 90) {
            $warningLevel = 'critical';
        } elseif ($percentage >= 80) {
            $warningLevel = 'low';
        }

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
