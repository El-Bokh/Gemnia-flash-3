<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\CreditLedger;
use App\Models\SubscriptionLog;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Expire subscriptions that have passed their end date and are not set to auto-renew';

    public function handle(): int
    {
        $notifications = app(NotificationService::class);

        // 1. Expire subscriptions that ended and auto_renew is false
        $toExpire = Subscription::query()
            ->where('status', 'active')
            ->where('auto_renew', false)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->with('plan', 'user')
            ->get();

        $expired = 0;

        foreach ($toExpire as $sub) {
            try {
                DB::transaction(function () use ($sub, $notifications) {
                    $locked = Subscription::lockForUpdate()->find($sub->id);
                    if (! $locked || $locked->status !== 'active') {
                        return;
                    }

                    $planName = $locked->plan->name ?? 'Unknown';

                    $locked->update(['status' => 'expired']);

                    SubscriptionLog::create([
                        'subscription_id' => $locked->id,
                        'user_id'         => $locked->user_id,
                        'action'          => 'expired',
                        'old_plan_id'     => $locked->plan_id,
                        'description'     => "{$planName} subscription expired",
                    ]);

                    // Notify user
                    if ($locked->user) {
                        $notifications->sendSubscriptionExpired($locked->user, $planName);
                    }

                    // Auto-subscribe to free plan
                    $freePlan = Plan::where('is_free', true)->where('is_active', true)->first();
                    if ($freePlan && $locked->user) {
                        $freeSub = Subscription::create([
                            'user_id'           => $locked->user_id,
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
                            'user_id'         => $locked->user_id,
                            'subscription_id' => $freeSub->id,
                            'type'            => 'credit',
                            'amount'          => $freePlan->credits_monthly,
                            'balance_after'   => $freePlan->credits_monthly,
                            'source'          => 'subscription',
                            'reference_type'  => Subscription::class,
                            'reference_id'    => $freeSub->id,
                            'description'     => "Downgraded to {$freePlan->name} after subscription expiry",
                        ]);
                    }
                });

                $expired++;
            } catch (\Throwable $e) {
                Log::error('Subscription expiry failed', [
                    'subscription_id' => $sub->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        // 2. Also expire past_due subscriptions older than 7 days
        $pastDueExpired = Subscription::query()
            ->where('status', 'past_due')
            ->where('updated_at', '<=', now()->subDays(7))
            ->update(['status' => 'expired']);

        // 3. Send expiry warnings (3 days before for active subscriptions)
        $soonExpiring = Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->where('auto_renew', false)
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addDays(3)])
            ->with('plan', 'user')
            ->get();

        $warned = 0;
        foreach ($soonExpiring as $sub) {
            if ($sub->user && $sub->plan) {
                $daysLeft = (int) now()->diffInDays($sub->ends_at, false);
                $notifications->sendSubscriptionExpiring($sub->user, $sub->plan->name, max($daysLeft, 1));
                $warned++;
            }
        }

        $this->info("Expired: {$expired}, Past-due expired: {$pastDueExpired}, Warnings sent: {$warned}");
        Log::info("subscriptions:expire completed", [
            'expired'      => $expired,
            'past_due'     => $pastDueExpired,
            'warnings'     => $warned,
        ]);

        return self::SUCCESS;
    }
}
