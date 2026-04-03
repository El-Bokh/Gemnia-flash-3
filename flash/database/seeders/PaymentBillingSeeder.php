<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PaymentBillingSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::limit(5)->get();
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Skipping PaymentBillingSeeder.');
            return;
        }

        $plans = Plan::all();
        $plan  = $plans->first();

        // ── Create Coupons ──
        $coupons = [];

        $coupons[] = Coupon::create([
            'code'              => 'WELCOME20',
            'name'              => 'Welcome 20% Off',
            'description'       => 'Welcome discount for new users — 20% off first payment.',
            'discount_type'     => 'percentage',
            'discount_value'    => 20,
            'currency'          => 'USD',
            'max_uses'          => 100,
            'max_uses_per_user' => 1,
            'times_used'        => 5,
            'min_order_amount'  => 10.00,
            'applicable_plan_id'=> null,
            'is_active'         => true,
            'starts_at'         => now()->subMonth(),
            'expires_at'        => now()->addMonths(3),
        ]);

        $coupons[] = Coupon::create([
            'code'              => 'FLAT10',
            'name'              => '$10 Off Any Plan',
            'description'       => 'Fixed $10 discount on any plan subscription.',
            'discount_type'     => 'fixed_amount',
            'discount_value'    => 10.00,
            'currency'          => 'USD',
            'max_uses'          => 50,
            'max_uses_per_user' => 2,
            'times_used'        => 12,
            'min_order_amount'  => 20.00,
            'applicable_plan_id'=> $plan?->id,
            'is_active'         => true,
            'starts_at'         => now()->subWeeks(2),
            'expires_at'        => now()->addMonth(),
        ]);

        $coupons[] = Coupon::create([
            'code'              => 'BONUS50CR',
            'name'              => '50 Bonus Credits',
            'description'       => 'Get 50 bonus credits with your subscription.',
            'discount_type'     => 'credits',
            'discount_value'    => 50,
            'currency'          => 'USD',
            'max_uses'          => null,
            'max_uses_per_user' => 1,
            'times_used'        => 3,
            'is_active'         => true,
            'starts_at'         => now()->subMonth(),
            'expires_at'        => null,
        ]);

        $coupons[] = Coupon::create([
            'code'              => 'EXPIRED99',
            'name'              => 'Expired Promo',
            'description'       => 'This coupon has expired.',
            'discount_type'     => 'percentage',
            'discount_value'    => 99,
            'currency'          => 'USD',
            'max_uses'          => 10,
            'max_uses_per_user' => 1,
            'times_used'        => 10,
            'is_active'         => false,
            'starts_at'         => now()->subMonths(3),
            'expires_at'        => now()->subMonth(),
        ]);

        // ── Create Subscriptions & Payments ──
        $statuses  = ['completed', 'completed', 'completed', 'failed', 'pending', 'refunded', 'partially_refunded', 'completed'];
        $gateways  = ['stripe', 'paypal', 'stripe', 'stripe', 'paypal', 'stripe', 'stripe', 'paypal'];

        foreach ($statuses as $i => $status) {
            $user   = $users[$i % $users->count()];
            $amount = round(rand(1000, 9999) / 100, 2);
            $coupon = $i < 3 ? $coupons[$i] : null;
            $discount = $coupon ? round($amount * 0.1, 2) : 0;
            $tax      = round($amount * 0.08, 2);
            $net      = round($amount - $discount + $tax, 2);

            // Create subscription if plan exists
            $subscription = null;
            if ($plan) {
                $subscription = Subscription::create([
                    'user_id'       => $user->id,
                    'plan_id'       => $plan->id,
                    'billing_cycle' => $i % 2 === 0 ? 'monthly' : 'yearly',
                    'status'        => 'active',
                    'price'         => $amount,
                    'currency'      => 'USD',
                    'starts_at'     => now()->subDays(rand(1, 60)),
                    'ends_at'       => now()->addDays(rand(1, 365)),
                    'auto_renew'    => true,
                ]);
            }

            $paidAt     = in_array($status, ['completed', 'refunded', 'partially_refunded']) ? now()->subDays(rand(1, 30)) : null;
            $refundedAmt = $status === 'refunded' ? $amount : ($status === 'partially_refunded' ? round($amount * 0.5, 2) : 0);
            $refundedAt  = $refundedAmt > 0 ? now()->subDays(rand(0, 5)) : null;

            $payment = Payment::create([
                'user_id'            => $user->id,
                'subscription_id'    => $subscription?->id,
                'coupon_id'          => $coupon?->id,
                'payment_gateway'    => $gateways[$i],
                'gateway_payment_id' => 'pi_' . fake()->bothify('??????????##########'),
                'gateway_customer_id'=> 'cus_' . fake()->bothify('??????????####'),
                'status'             => $status,
                'amount'             => $amount,
                'discount_amount'    => $discount,
                'tax_amount'         => $tax,
                'net_amount'         => $net,
                'currency'           => 'USD',
                'payment_method'     => $i % 3 === 0 ? 'card' : ($i % 3 === 1 ? 'paypal' : 'bank_transfer'),
                'description'        => "Subscription payment #{$i} for {$plan?->name}",
                'refunded_amount'    => $refundedAmt,
                'refunded_at'        => $refundedAt,
                'refund_reason'      => $refundedAmt > 0 ? 'Customer requested refund' : null,
                'billing_name'       => $user->name,
                'billing_email'      => $user->email,
                'billing_address'    => fake()->streetAddress(),
                'billing_city'       => fake()->city(),
                'billing_state'      => fake()->state(),
                'billing_zip'        => fake()->postcode(),
                'billing_country'    => 'US',
                'gateway_response'   => ['status' => $status, 'id' => 'ch_' . fake()->bothify('##########')],
                'metadata'           => ['source' => 'admin_seeder'],
                'paid_at'            => $paidAt,
            ]);

            // Create invoice for completed payments
            if (in_array($status, ['completed', 'refunded', 'partially_refunded'])) {
                $invoiceStatus = match ($status) {
                    'refunded' => 'refunded',
                    'partially_refunded' => 'paid',
                    default => 'paid',
                };

                Invoice::create([
                    'invoice_number'  => 'INV-' . date('Y') . '-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                    'user_id'         => $user->id,
                    'payment_id'      => $payment->id,
                    'subscription_id' => $subscription?->id,
                    'status'          => $invoiceStatus,
                    'subtotal'        => $amount,
                    'discount_amount' => $discount,
                    'tax_amount'      => $tax,
                    'total'           => $net,
                    'currency'        => 'USD',
                    'billing_name'    => $user->name,
                    'billing_email'   => $user->email,
                    'billing_address' => $payment->billing_address,
                    'billing_city'    => $payment->billing_city,
                    'billing_state'   => $payment->billing_state,
                    'billing_zip'     => $payment->billing_zip,
                    'billing_country' => 'US',
                    'line_items'      => [
                        ['description' => "Subscription: {$plan?->name}", 'quantity' => 1, 'unit_price' => (float) $amount, 'total' => (float) $amount],
                    ],
                    'notes'           => 'Thank you for your payment.',
                    'issued_at'       => $paidAt ?? now(),
                    'paid_at'         => $paidAt,
                    'due_at'          => ($paidAt ?? now())->copy()->addDays(30),
                ]);
            }
        }

        $this->command->info('PaymentBillingSeeder: Created 4 coupons, 8 payments, and invoices for completed payments.');
    }
}
