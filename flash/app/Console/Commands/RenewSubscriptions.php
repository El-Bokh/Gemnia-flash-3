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

class RenewSubscriptions extends Command
{
    protected $signature = 'subscriptions:renew';
    protected $description = 'Auto-renew expired subscriptions that have auto_renew enabled';

    public function handle(): int
    {
        $expiredAutoRenew = Subscription::query()
            ->where('status', 'active')
            ->where('auto_renew', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->with('plan', 'user')
            ->get();

        $renewed = 0;
        $failed  = 0;

        foreach ($expiredAutoRenew as $subscription) {
            try {
                DB::transaction(function () use ($subscription) {
                    $sub = Subscription::lockForUpdate()->find($subscription->id);
                    if (! $sub || $sub->status !== 'active') {
                        return;
                    }

                    $plan = $sub->plan;
                    if (! $plan || ! $plan->is_active) {
                        // Plan no longer available — expire the subscription
                        $sub->update(['status' => 'expired']);
                        return;
                    }

                    // For free plans: simply reset credits and extend period
                    // For paid plans: only renew if payment gateway handles it
                    // (here we renew free plans and mark paid as expired until payment confirmation)
                    if ($plan->is_free) {
                        $credits = $sub->billing_cycle === 'yearly'
                            ? $plan->credits_yearly
                            : $plan->credits_monthly;

                        $newEndsAt = $sub->billing_cycle === 'yearly'
                            ? now()->addYear()
                            : now()->addMonth();

                        $sub->update([
                            'credits_remaining' => $credits,
                            'credits_total'     => $credits,
                            'starts_at'         => now(),
                            'ends_at'           => $newEndsAt,
                        ]);

                        CreditLedger::create([
                            'user_id'         => $sub->user_id,
                            'subscription_id' => $sub->id,
                            'type'            => 'credit',
                            'amount'          => $credits,
                            'balance_after'   => $credits,
                            'source'          => 'auto_renewal',
                            'reference_type'  => Subscription::class,
                            'reference_id'    => $sub->id,
                            'description'     => "Auto-renewed {$plan->name} plan ({$sub->billing_cycle})",
                        ]);

                        SubscriptionLog::create([
                            'subscription_id' => $sub->id,
                            'user_id'         => $sub->user_id,
                            'action'          => 'renewed',
                            'new_plan_id'     => $plan->id,
                            'description'     => "Auto-renewed {$plan->name} subscription",
                        ]);
                    } else {
                        // Paid plan: mark as past_due (awaiting payment gateway confirmation)
                        $sub->update(['status' => 'past_due']);

                        SubscriptionLog::create([
                            'subscription_id' => $sub->id,
                            'user_id'         => $sub->user_id,
                            'action'          => 'payment_failed',
                            'new_plan_id'     => $plan->id,
                            'description'     => "Auto-renewal pending payment for {$plan->name}",
                        ]);
                    }
                });

                $renewed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Subscription renewal failed', [
                    'subscription_id' => $subscription->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        $this->info("Renewed: {$renewed}, Failed: {$failed}");
        Log::info("subscriptions:renew completed", ['renewed' => $renewed, 'failed' => $failed]);

        return self::SUCCESS;
    }
}
