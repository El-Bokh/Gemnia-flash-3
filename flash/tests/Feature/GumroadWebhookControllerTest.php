<?php

namespace Tests\Feature;

use App\Models\PaymentWebhook;
use App\Models\Subscription;
use App\Models\User;
use App\Services\GumroadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GumroadWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_sale_without_license_key_activates_from_short_product_id_match(): void
    {
        config()->set('services.gumroad.product_id', 'mghcgm');

        $user = User::factory()->create([
            'email' => 'aliamir1090x@gmail.com',
        ]);

        $payload = [
            'product_id' => 'xO4Ej-MCUl28E1vXjttPKg==',
            'short_product_id' => 'mghcgm',
            'email' => $user->email,
            'sale_id' => 'U97JDcJ6Y_tgk2lMlik3nQ==',
            'subscription_id' => '7-z9_nPDHQUNVMYkjuFynA==',
            'full_name' => 'Ali Ameer',
            'currency' => 'USD',
            'recurrence' => 'monthly',
            'variants' => ['Tier' => 'Untitled'],
            'refunded' => 'false',
            'resource_name' => 'sale',
        ];

        $response = $this->postJson('/webhook/gumroad', $payload);

        $response->assertOk()->assertJson([
            'ok' => true,
            'plan' => GumroadService::PLAN_MONTHLY,
        ]);

        $this->assertDatabaseHas('payment_webhooks', [
            'gateway' => 'gumroad',
            'event_id' => $payload['sale_id'],
            'status' => 'processed',
        ]);

        $subscription = Subscription::query()->where('gumroad_sale_id', $payload['sale_id'])->first();

        $this->assertNotNull($subscription);
        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame('active', $subscription->status);
        $this->assertSame($payload['product_id'], $subscription->gumroad_product_id);
    }

    public function test_unknown_product_marks_webhook_as_failed_instead_of_crashing(): void
    {
        config()->set('services.gumroad.product_id', 'mghcgm');

        $payload = [
            'product_id' => 'xO4Ej-MCUl28E1vXjttPKg==',
            'short_product_id' => 'wrong-short-id',
            'sale_id' => 'sale-unknown-product',
            'subscription_id' => 'subscription-unknown-product',
            'email' => 'missing@example.com',
            'refunded' => 'false',
            'resource_name' => 'sale',
        ];

        $response = $this->postJson('/webhook/gumroad', $payload);

        $response->assertOk()->assertJson([
            'ok' => false,
            'reason' => 'unknown_product',
        ]);

        $webhook = PaymentWebhook::query()->where('event_id', $payload['sale_id'])->first();

        $this->assertNotNull($webhook);
        $this->assertSame('failed', $webhook->status);
        $this->assertSame('Unknown product_id', $webhook->error_message);
    }
}