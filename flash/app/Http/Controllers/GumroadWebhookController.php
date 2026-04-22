<?php

namespace App\Http\Controllers;

use App\Models\PaymentWebhook;
use App\Models\Payment;
use App\Models\User;
use App\Services\GumroadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GumroadWebhookController extends Controller
{
    public function __construct(private readonly GumroadService $gumroad) {}

    /**
     * POST /webhook/gumroad
     *
     * Gumroad Ping endpoint. Validates the sale, identifies the user,
     * detects the variant (Monthly vs 6 Months) and activates / cancels
     * the subscription accordingly.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Step 1 — log the raw payload so we can verify the variant field shape.
        Log::info('Gumroad webhook received', [
            'ip' => $request->ip(),
            'payload' => $payload,
        ]);

        // Persist the raw event for audit / replay.
        $log = $this->logIncoming($request, $payload);

        $email = $payload['email'] ?? null;
        $productId = $payload['product_id'] ?? null;
        $licenseKey = $payload['license_key'] ?? null;
        $refunded = filter_var($payload['refunded'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Make sure the product matches the configured Klek membership.
        $expectedProduct = config('services.gumroad.product_id');
        if ($expectedProduct && $productId && $productId !== $expectedProduct) {
            $this->markProcessed($log, 'rejected', 'Unknown product_id');
            Log::warning('Gumroad webhook rejected: unexpected product_id', [
                'expected' => $expectedProduct,
                'got' => $productId,
            ]);
            return response()->json(['ok' => false, 'reason' => 'unknown_product'], 200);
        }

        // Refund flow — no license verification required.
        if ($refunded) {
            $cancelled = $this->gumroad->cancelFromWebhook($payload);
            $this->markProcessed(
                $log,
                $cancelled ? 'cancelled' : 'ignored',
                $cancelled ? null : 'No matching subscription to cancel'
            );

            return response()->json([
                'ok' => true,
                'cancelled' => (bool) $cancelled,
            ]);
        }

        // Step 3 — verify the sale via Gumroad's API (mandatory).
        if (! $licenseKey || ! $productId) {
            $this->markProcessed($log, 'rejected', 'Missing license_key or product_id');
            return response()->json(['ok' => false, 'reason' => 'missing_license'], 200);
        }

        if (! $this->gumroad->verifyLicense($productId, $licenseKey)) {
            $this->markProcessed($log, 'rejected', 'License verification failed');
            $this->recordFailedPayment($payload, 'License verification failed');
            return response()->json(['ok' => false, 'reason' => 'verify_failed'], 200);
        }

        // Step 4 — locate the user by email.
        if (! $email) {
            $this->markProcessed($log, 'rejected', 'Missing email');
            return response()->json(['ok' => false, 'reason' => 'missing_email'], 200);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->markProcessed($log, 'rejected', 'User not found for email ' . $email);
            Log::warning('Gumroad webhook: user not found', ['email' => $email]);
            return response()->json(['ok' => false, 'reason' => 'user_not_found'], 200);
        }

        // Step 5 — detect plan from variant.
        $detected = $this->gumroad->detectPlan($payload);
        if (! $detected) {
            $this->markProcessed($log, 'rejected', 'Unable to detect plan from variant');
            Log::warning('Gumroad webhook: variant not recognised', ['payload' => $payload]);
            return response()->json(['ok' => false, 'reason' => 'unknown_variant'], 200);
        }

        // Step 6 — activate.
        $payload['__detected_variant'] = $detected['variant'];
        $sub = $this->gumroad->activateFromWebhook($user, $detected['plan'], $payload);

        $this->markProcessed($log, 'processed', null, $sub->id);

        return response()->json([
            'ok' => true,
            'plan' => $detected['plan'],
            'subscription_id' => $sub->id,
            'ends_at' => optional($sub->ends_at)->toIso8601String(),
        ]);
    }

    private function logIncoming(Request $request, array $payload): PaymentWebhook
    {
        return PaymentWebhook::create([
            'gateway' => 'gumroad',
            'event_type' => $payload['resource_name'] ?? ($payload['refunded'] ?? false ? 'refund' : 'sale'),
            'event_id' => $payload['sale_id'] ?? null,
            'gateway_payment_id' => $payload['sale_id'] ?? null,
            'gateway_subscription_id' => $payload['subscription_id'] ?? null,
            'status' => 'received',
            'payload' => $payload,
            'headers' => collect($request->headers->all())
                ->only(['user-agent', 'content-type', 'x-forwarded-for'])
                ->toArray(),
            'attempts' => 1,
            'ip_address' => $request->ip(),
        ]);
    }

    private function markProcessed(PaymentWebhook $log, string $status, ?string $error = null, ?int $subscriptionId = null): void
    {
        $log->update([
            'status' => $status,
            'error_message' => $error,
            'processed_at' => now(),
            'gateway_subscription_id' => $subscriptionId
                ? (string) $subscriptionId
                : $log->gateway_subscription_id,
        ]);
    }

    /**
     * Persist a `failed` payment row when we can match the email to a user.
     * Lets the admin Payments stats show real failure numbers.
     */
    private function recordFailedPayment(array $payload, string $reason): void
    {
        $email = $payload['email'] ?? null;
        if (! $email) {
            return;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return;
        }

        $saleId = $payload['sale_id'] ?? null;
        if ($saleId && Payment::where('payment_gateway', 'gumroad')
                ->where('gateway_payment_id', $saleId)->exists()) {
            return;
        }

        $amount = (float) ($payload['price'] ?? 0) / 100; // Gumroad sends cents
        if ($amount <= 0) {
            $amount = (float) ($payload['sale_amount_cents'] ?? 0) / 100;
        }

        Payment::create([
            'user_id' => $user->id,
            'payment_gateway' => 'gumroad',
            'gateway_payment_id' => $saleId,
            'status' => 'failed',
            'amount' => $amount,
            'net_amount' => $amount,
            'currency' => $payload['currency'] ?? 'USD',
            'payment_method' => 'gumroad',
            'description' => 'Gumroad webhook rejected: ' . $reason,
            'billing_email' => $email,
            'billing_name' => $payload['full_name'] ?? $user->name,
            'gateway_response' => $payload,
            'metadata' => [
                'reason' => $reason,
                'product_id' => $payload['product_id'] ?? null,
            ],
        ]);
    }
}
