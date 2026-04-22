<?php

namespace App\Services;

use App\Models\CreditLedger;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles Gumroad checkout webhooks (Ping endpoint).
 *
 * Two plans live inside ONE Gumroad membership product:
 *   - Monthly      ($25, recurrence=monthly)
 *   - Every 6 months ($130, recurrence=biannually)
 *
 * Gumroad uses the same option/tier name for both. The recurrence or sale amount
 * may be the only reliable signal in webhook payloads.
 */
class GumroadService
{
    public const PLAN_MONTHLY = 'monthly';
    public const PLAN_SIX_MONTHS = '6_months';

    /**
     * Verify the sale against Gumroad's license API.
     * Returns true when Gumroad reports success === true.
     */
    public function verifyLicense(string $productId, string $licenseKey): bool
    {
        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post(config('services.gumroad.verify_url'), [
                    'product_id' => $productId,
                    'license_key' => $licenseKey,
                    // Don't bump the use_count; we only want to verify.
                    'increment_uses_count' => 'false',
                ]);

            if (! $response->ok()) {
                Log::warning('Gumroad verify HTTP failure', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();
            return (bool) ($data['success'] ?? false);
        } catch (\Throwable $e) {
            Log::error('Gumroad verify threw', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Look at the webhook payload and figure out which plan was bought.
     * Returns ['plan' => 'monthly'|'6_months', 'variant' => string] or null.
     */
    public function detectPlan(array $payload): ?array
    {
        $candidates = [];

        // Plain string options
        foreach (['option', 'variant'] as $key) {
            if (! empty($payload[$key]) && is_string($payload[$key])) {
                $candidates[] = $payload[$key];
            }
        }

        // variants can be an array like ['Tier' => 'Monthly'] or a string
        if (! empty($payload['variants'])) {
            $candidates = array_merge($candidates, $this->flattenVariantField($payload['variants']));
        }

        // variants_and_quantity is usually a string like "Monthly (x1)" or
        // a structured array.
        if (! empty($payload['variants_and_quantity'])) {
            $candidates = array_merge(
                $candidates,
                $this->flattenVariantField($payload['variants_and_quantity'])
            );
        }

        foreach (['recurrence', 'subscription_recurrence', 'billing_period', 'duration'] as $key) {
            if (! empty($payload[$key]) && is_string($payload[$key])) {
                $candidates[] = $payload[$key];
                $candidates[] = $key . ':' . $payload[$key];
            }
        }

        foreach ($candidates as $value) {
            $plan = $this->matchVariantString((string) $value);
            if ($plan !== null) {
                return ['plan' => $plan, 'variant' => (string) $value];
            }
        }

        $amount = $this->extractAmountCents($payload);
        if ($amount !== null) {
            $plan = $this->matchAmountCents($amount);
            if ($plan !== null) {
                return ['plan' => $plan, 'variant' => 'amount_cents:' . $amount];
            }
        }

        return null;
    }

    /**
     * Activate (or replace) the user's subscription based on a verified sale.
     */
    public function activateFromWebhook(User $user, string $planKey, array $payload): Subscription
    {
        return DB::transaction(function () use ($user, $planKey, $payload) {
            $plan = $this->resolvePlan($planKey);
            $duration = $planKey === self::PLAN_SIX_MONTHS ? 6 : 1;
            $price = $planKey === self::PLAN_SIX_MONTHS ? 130 : 25;

            // Cancel any other active subscription for this user.
            $existing = $user->subscriptions()
                ->whereIn('status', ['active', 'trialing', 'past_due'])
                ->lockForUpdate()
                ->get();

            foreach ($existing as $old) {
                $old->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Replaced by Gumroad subscription',
                    'auto_renew' => false,
                ]);
            }

            $saleId = $payload['sale_id'] ?? $payload['sale_id'] ?? null;
            $licenseKey = $payload['license_key'] ?? null;
            $variant = $payload['__detected_variant'] ?? null;

            // Idempotency: if we already processed this exact sale, just return it.
            if ($saleId) {
                $already = Subscription::where('gumroad_sale_id', $saleId)->first();
                if ($already) {
                    return $already;
                }
            }

            $credits = (int) ($plan->credits_monthly ?? 0) * $duration;
            if ($credits <= 0) {
                $credits = $duration === 6 ? 6000 : 1000;
            }

            $sub = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $duration === 6 ? 'yearly' : 'monthly',
                'status' => 'active',
                'price' => $price,
                'currency' => 'USD',
                'starts_at' => now(),
                'ends_at' => now()->addMonths($duration),
                'payment_gateway' => 'gumroad',
                'gateway_subscription_id' => $payload['subscription_id'] ?? null,
                'gumroad_product_id' => $payload['product_id'] ?? null,
                'gumroad_sale_id' => $saleId,
                'gumroad_variant' => $variant,
                'gumroad_license_key' => $licenseKey,
                'credits_remaining' => $credits,
                'credits_total' => $credits,
                'auto_renew' => true,
                'metadata' => [
                    'gumroad_payload_keys' => array_keys($payload),
                ],
            ]);

            SubscriptionLog::create([
                'subscription_id' => $sub->id,
                'user_id' => $user->id,
                'action' => 'created',
                'new_plan_id' => $plan->id,
                'description' => "Activated via Gumroad ({$variant})",
            ]);

            CreditLedger::create([
                'user_id' => $user->id,
                'subscription_id' => $sub->id,
                'type' => 'credit',
                'amount' => $credits,
                'balance_after' => $credits,
                'source' => 'subscription',
                'reference_type' => Subscription::class,
                'reference_id' => $sub->id,
                'description' => "Gumroad {$plan->name} purchase",
            ]);

            // Record the payment so the admin Payments panel + dashboard see it.
            $this->recordPayment($user, $sub, $price, $payload, 'completed');

            return $sub;
        });
    }

    /**
     * Cancel a subscription tied to a refunded Gumroad sale.
     */
    public function cancelFromWebhook(array $payload): ?Subscription
    {
        $saleId = $payload['sale_id'] ?? null;
        $email = $payload['email'] ?? null;

        $query = Subscription::query()->where('payment_gateway', 'gumroad');

        if ($saleId) {
            $sub = (clone $query)->where('gumroad_sale_id', $saleId)->first();
            if ($sub) {
                return $this->markCancelled($sub, 'Gumroad refund');
            }
        }

        if ($email) {
            $sub = (clone $query)
                ->whereHas('user', fn ($q) => $q->where('email', $email))
                ->whereIn('status', ['active', 'trialing', 'past_due'])
                ->latest('starts_at')
                ->first();
            if ($sub) {
                return $this->markCancelled($sub, 'Gumroad refund');
            }
        }

        return null;
    }

    /**
     * Map plan key → Plan model (firstOrCreate).
     */
    public function resolvePlan(string $planKey): Plan
    {
        $cfg = config('services.gumroad');

        if ($planKey === self::PLAN_SIX_MONTHS) {
            return Plan::firstOrCreate(
                ['slug' => $cfg['six_months_plan_slug']],
                [
                    'name' => 'Klek 6 Months',
                    'description' => '6 months access via Gumroad',
                    'price_monthly' => 21.67,
                    'price_yearly' => 130,
                    'currency' => 'USD',
                    'credits_monthly' => 1000,
                    'credits_yearly' => 6000,
                    'is_free' => false,
                    'is_active' => true,
                    'is_featured' => true,
                    'sort_order' => 50,
                    'trial_days' => 0,
                ]
            );
        }

        return Plan::firstOrCreate(
            ['slug' => $cfg['monthly_plan_slug']],
            [
                'name' => 'Klek Monthly',
                'description' => 'Monthly access via Gumroad',
                'price_monthly' => 25,
                'price_yearly' => 300,
                'currency' => 'USD',
                'credits_monthly' => 1000,
                'credits_yearly' => 12000,
                'is_free' => false,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 49,
                'trial_days' => 0,
            ]
        );
    }

    private function markCancelled(Subscription $sub, string $reason): Subscription
    {
        $sub->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'auto_renew' => false,
        ]);

        SubscriptionLog::create([
            'subscription_id' => $sub->id,
            'user_id' => $sub->user_id,
            'action' => 'cancelled',
            'old_plan_id' => $sub->plan_id,
            'description' => $reason,
        ]);

        // Mark the matching payment as refunded so admin stats stay accurate.
        $this->refundLatestPaymentFor($sub, $reason);

        return $sub;
    }

    /**
     * Insert a Payment row mirroring the Gumroad sale.
     * Idempotent: returns the existing payment if sale_id matches.
     */
    private function recordPayment(User $user, Subscription $sub, float $price, array $payload, string $status): Payment
    {
        $saleId = $payload['sale_id'] ?? null;

        if ($saleId) {
            $existing = Payment::where('payment_gateway', 'gumroad')
                ->where('gateway_payment_id', $saleId)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'payment_gateway' => 'gumroad',
            'gateway_payment_id' => $saleId,
            'gateway_customer_id' => $payload['purchaser_id'] ?? null,
            'status' => $status,
            'amount' => $price,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => $price,
            'currency' => $payload['currency'] ?? 'USD',
            'payment_method' => 'gumroad',
            'description' => 'Gumroad: ' . ($payload['__detected_variant'] ?? 'membership'),
            'billing_name' => $payload['full_name'] ?? $user->name,
            'billing_email' => $payload['email'] ?? $user->email,
            'billing_country' => $payload['ip_country_code'] ?? null,
            'gateway_response' => $payload,
            'metadata' => [
                'license_key' => $payload['license_key'] ?? null,
                'product_id' => $payload['product_id'] ?? null,
                'variant' => $payload['__detected_variant'] ?? null,
            ],
            'paid_at' => $status === 'completed' ? now() : null,
        ]);
    }

    /**
     * Mark the most relevant payment as refunded when Gumroad sends a refund ping.
     */
    private function refundLatestPaymentFor(Subscription $sub, string $reason): void
    {
        $payment = Payment::where('payment_gateway', 'gumroad')
            ->where(function ($q) use ($sub) {
                $q->where('subscription_id', $sub->id);
                if ($sub->gumroad_sale_id) {
                    $q->orWhere('gateway_payment_id', $sub->gumroad_sale_id);
                }
            })
            ->whereIn('status', ['completed', 'partially_refunded'])
            ->latest('paid_at')
            ->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => 'refunded',
            'refunded_amount' => $payment->amount,
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ]);
    }

    private function matchVariantString(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        // Match the 6-month case first so "month" in "6 months" doesn't fall
        // through to the Monthly branch.
        if (
            str_contains($normalized, '6 month')
            || str_contains($normalized, '6-month')
            || str_contains($normalized, 'six month')
            || str_contains($normalized, 'every 6')
            || str_contains($normalized, 'semi')
            || str_contains($normalized, 'biannual')
            || str_contains($normalized, 'bi-ann')
        ) {
            return self::PLAN_SIX_MONTHS;
        }

        if (str_contains($normalized, 'month')) {
            return self::PLAN_MONTHLY;
        }

        return null;
    }

    /**
     * Recursively collect string values from a Gumroad variant field.
     */
    private function flattenVariantField(mixed $field): array
    {
        if (is_string($field)) {
            return [$field];
        }

        if (! is_array($field)) {
            return [];
        }

        $out = [];
        foreach ($field as $key => $value) {
            if (is_string($value)) {
                $out[] = $value;
                if (is_string($key)) {
                    $out[] = $key . ' ' . $value;
                }
            } elseif (is_array($value)) {
                $out = array_merge($out, $this->flattenVariantField($value));
            }
        }
        return $out;
    }

    private function extractAmountCents(array $payload): ?int
    {
        foreach (['price', 'sale_amount_cents', 'display_price_cents'] as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
                continue;
            }

            if (is_numeric($payload[$key])) {
                return (int) round((float) $payload[$key]);
            }
        }

        return null;
    }

    private function matchAmountCents(int $amountCents): ?string
    {
        return match ($amountCents) {
            2500 => self::PLAN_MONTHLY,
            13000 => self::PLAN_SIX_MONTHS,
            default => null,
        };
    }
}
