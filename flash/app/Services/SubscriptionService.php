<?php

namespace App\Services;

use App\Models\CreditLedger;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionLog;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Upgrade or downgrade a user to a new plan.
     *
     * @return array{success: bool, subscription: ?Subscription, message: string}
     */
    public function changePlan(User $user, int $newPlanId, string $billingCycle = 'monthly'): array
    {
        return DB::transaction(function () use ($user, $newPlanId, $billingCycle) {
            $newPlan = Plan::where('is_active', true)->findOrFail($newPlanId);

            // Get current active subscription (lock for update)
            $currentSub = $user->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->where(function ($q) {
                    $q->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
                })
                ->latest('starts_at')
                ->lockForUpdate()
                ->first();

            // Prevent switching to the same plan
            if ($currentSub && $currentSub->plan_id === $newPlan->id && $currentSub->billing_cycle === $billingCycle) {
                return [
                    'success'      => false,
                    'subscription' => $currentSub,
                    'message'      => 'You are already on this plan.',
                ];
            }

            $oldPlanName = null;
            $isUpgrade = true;

            // Cancel the current subscription
            if ($currentSub) {
                $currentSub->load('plan');
                $oldPlanName = $currentSub->plan->name ?? 'Unknown';

                $isUpgrade = $newPlan->credits_monthly > ($currentSub->plan->credits_monthly ?? 0);

                $currentSub->update([
                    'status'       => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $isUpgrade ? 'Upgraded to ' . $newPlan->name : 'Downgraded to ' . $newPlan->name,
                ]);

                // Log the change
                SubscriptionLog::create([
                    'subscription_id' => $currentSub->id,
                    'user_id'         => $user->id,
                    'action'          => $isUpgrade ? 'upgraded' : 'downgraded',
                    'old_plan_id'     => $currentSub->plan_id,
                    'new_plan_id'     => $newPlan->id,
                    'description'     => ($isUpgrade ? 'Upgraded' : 'Downgraded') . " from {$oldPlanName} to {$newPlan->name}",
                ]);
            }

            // Create the new subscription
            $price   = $billingCycle === 'yearly' ? $newPlan->price_yearly : $newPlan->price_monthly;
            $credits = $billingCycle === 'yearly' ? $newPlan->credits_yearly : $newPlan->credits_monthly;

            $newSub = Subscription::create([
                'user_id'           => $user->id,
                'plan_id'           => $newPlan->id,
                'billing_cycle'     => $billingCycle,
                'status'            => $newPlan->trial_days > 0 ? 'trialing' : 'active',
                'price'             => $price,
                'currency'          => $newPlan->currency ?? 'USD',
                'trial_starts_at'   => $newPlan->trial_days > 0 ? now() : null,
                'trial_ends_at'     => $newPlan->trial_days > 0 ? now()->addDays($newPlan->trial_days) : null,
                'starts_at'         => now(),
                'ends_at'           => $billingCycle === 'yearly'
                    ? now()->addYear()
                    : now()->addMonth(),
                'credits_remaining' => $credits,
                'credits_total'     => $credits,
                'auto_renew'        => true,
            ]);

            // Log creation
            SubscriptionLog::create([
                'subscription_id' => $newSub->id,
                'user_id'         => $user->id,
                'action'          => 'created',
                'old_plan_id'     => $currentSub?->plan_id,
                'new_plan_id'     => $newPlan->id,
                'description'     => "Subscribed to {$newPlan->name} ({$billingCycle})",
            ]);

            // Credit ledger entry
            CreditLedger::create([
                'user_id'         => $user->id,
                'subscription_id' => $newSub->id,
                'type'            => 'credit',
                'amount'          => $credits,
                'balance_after'   => $credits,
                'source'          => 'subscription',
                'reference_type'  => Subscription::class,
                'reference_id'    => $newSub->id,
                'description'     => "Credits from {$newPlan->name} plan ({$billingCycle})",
            ]);

            // Send notifications
            if ($oldPlanName) {
                $this->notifications->sendSubscriptionUpgraded($user, $oldPlanName, $newPlan->name);
                $this->notifications->notifyPlanUpgrade($user, $oldPlanName, $newPlan->name);
            } else {
                $this->notifications->sendSubscriptionActivated($user, $newPlan->name, $billingCycle);
                $this->notifications->notifyNewSubscription($user, $newPlan->name, $billingCycle);
            }

            Log::info('Plan changed', [
                'user_id'     => $user->id,
                'old_plan'    => $oldPlanName,
                'new_plan'    => $newPlan->name,
                'is_upgrade'  => $isUpgrade,
                'cycle'       => $billingCycle,
            ]);

            return [
                'success'      => true,
                'subscription' => $newSub->load('plan'),
                'message'      => $isUpgrade
                    ? "Successfully upgraded to {$newPlan->name}."
                    : "Plan changed to {$newPlan->name}.",
            ];
        });
    }

    /**
     * Cancel the user's active subscription.
     */
    public function cancel(User $user, ?string $reason = null): array
    {
        return DB::transaction(function () use ($user, $reason) {
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
                    'success' => false,
                    'message' => 'No active subscription found.',
                ];
            }

            $subscription->load('plan');
            $planName = $subscription->plan->name ?? 'Unknown';

            $subscription->update([
                'status'              => 'cancelled',
                'cancelled_at'        => now(),
                'cancellation_reason' => $reason ?? 'User cancelled',
                'auto_renew'          => false,
            ]);

            SubscriptionLog::create([
                'subscription_id' => $subscription->id,
                'user_id'         => $user->id,
                'action'          => 'cancelled',
                'old_plan_id'     => $subscription->plan_id,
                'description'     => "Cancelled {$planName} subscription" . ($reason ? ": {$reason}" : ''),
            ]);

            // Auto-subscribe to free plan
            $freePlan = Plan::where('is_free', true)->where('is_active', true)->first();
            if ($freePlan) {
                $freeSub = Subscription::create([
                    'user_id'           => $user->id,
                    'plan_id'           => $freePlan->id,
                    'billing_cycle'     => 'monthly',
                    'status'            => 'active',
                    'price'             => 0,
                    'currency'          => $freePlan->currency ?? 'USD',
                    'starts_at'         => now(),
                    'ends_at'           => now()->addMonth(),
                    'credits_remaining' => $freePlan->credits_monthly,
                    'credits_total'     => $freePlan->credits_monthly,
                    'auto_renew'        => true,
                ]);

                CreditLedger::create([
                    'user_id'         => $user->id,
                    'subscription_id' => $freeSub->id,
                    'type'            => 'credit',
                    'amount'          => $freePlan->credits_monthly,
                    'balance_after'   => $freePlan->credits_monthly,
                    'source'          => 'subscription',
                    'reference_type'  => Subscription::class,
                    'reference_id'    => $freeSub->id,
                    'description'     => "Downgraded to {$freePlan->name} plan after cancellation",
                ]);
            }

            Log::info('Subscription cancelled', [
                'user_id'   => $user->id,
                'plan_name' => $planName,
                'reason'    => $reason,
            ]);

            return [
                'success' => true,
                'message' => "Your {$planName} subscription has been cancelled. You are now on the free plan.",
            ];
        });
    }

    /**
     * Renew an expired subscription (auto-renew flow).
     */
    public function renewCurrent(User $user): array
    {
        $subscription = $user->subscriptions()
            ->whereIn('status', ['active', 'expired', 'past_due'])
            ->latest('starts_at')
            ->latest('id')
            ->first();

        if (! $subscription) {
            return ['success' => false, 'message' => 'No renewable subscription found.'];
        }

        return $this->renew($subscription);
    }

    /**
     * Renew an expired or depleted subscription.
     */
    public function renew(Subscription $subscription): array
    {
        return DB::transaction(function () use ($subscription) {
            $subscription = Subscription::lockForUpdate()->findOrFail($subscription->id);

            if (! in_array($subscription->status, ['active', 'expired', 'past_due'], true)) {
                return ['success' => false, 'message' => 'Subscription cannot be renewed.'];
            }

            $subscription->load('plan');
            $plan = $subscription->plan;

            if (! $plan || ! $plan->is_active) {
                return ['success' => false, 'message' => 'Plan is no longer available.'];
            }

            if (
                $subscription->status === 'active'
                && $subscription->credits_remaining > 0
                && ($subscription->ends_at === null || $subscription->ends_at->isFuture())
            ) {
                return ['success' => false, 'message' => 'Your current plan still has available credits.'];
            }

            if (! $plan->is_free && $subscription->payment_gateway === 'gumroad') {
                return ['success' => false, 'message' => 'Please complete checkout to renew this plan.'];
            }

            $credits = $subscription->billing_cycle === 'yearly'
                ? $plan->credits_yearly
                : $plan->credits_monthly;

            $newEndsAt = $subscription->billing_cycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth();

            $subscription->update([
                'status'            => 'active',
                'credits_remaining' => $credits,
                'credits_total'     => $credits,
                'starts_at'         => now(),
                'ends_at'           => $newEndsAt,
                'auto_renew'        => true,
            ]);

            CreditLedger::create([
                'user_id'         => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'type'            => 'credit',
                'amount'          => $credits,
                'balance_after'   => $credits,
                'source'          => 'renewal',
                'reference_type'  => Subscription::class,
                'reference_id'    => $subscription->id,
                'description'     => "Renewed {$plan->name} plan ({$subscription->billing_cycle})",
            ]);

            SubscriptionLog::create([
                'subscription_id' => $subscription->id,
                'user_id'         => $subscription->user_id,
                'action'          => 'renewed',
                'new_plan_id'     => $plan->id,
                'description'     => "Renewed {$plan->name} subscription",
            ]);

            Log::info('Subscription renewed', [
                'subscription_id' => $subscription->id,
                'user_id'         => $subscription->user_id,
                'plan'            => $plan->name,
            ]);

            return ['success' => true, 'message' => "Subscription renewed until {$newEndsAt->toDateString()}."];
        });
    }
}
