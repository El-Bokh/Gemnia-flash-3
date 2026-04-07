<?php

namespace Tests\Feature\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\PlanManagementService;
use App\Services\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrialStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-06 21:59:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_usage_stats_promote_an_expired_trial_to_active(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan(trialDays: 7);

        Subscription::create([
            'user_id'           => $user->id,
            'plan_id'           => $plan->id,
            'billing_cycle'     => 'monthly',
            'status'            => 'trialing',
            'price'             => 29.99,
            'currency'          => 'USD',
            'trial_starts_at'   => now()->subDays(10),
            'trial_ends_at'     => now()->subDays(3),
            'starts_at'         => now()->subDays(10),
            'ends_at'           => now()->addDays(20),
            'credits_remaining' => 500,
            'credits_total'     => 500,
            'auto_renew'        => true,
        ]);

        $stats = app(UsageService::class)->getUsageStats($user);
        $subscription = $user->subscriptions()->firstOrFail()->fresh();

        $this->assertSame('active', $subscription->status);
        $this->assertSame('active', $stats['status']);
    }

    public function test_admin_plan_update_ends_existing_trials_when_trial_days_become_zero(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan(trialDays: 14);

        Subscription::create([
            'user_id'           => $user->id,
            'plan_id'           => $plan->id,
            'billing_cycle'     => 'monthly',
            'status'            => 'trialing',
            'price'             => 29.99,
            'currency'          => 'USD',
            'trial_starts_at'   => now()->subDay(),
            'trial_ends_at'     => now()->addDays(13),
            'starts_at'         => now()->subDay(),
            'ends_at'           => now()->addDays(29),
            'credits_remaining' => 500,
            'credits_total'     => 500,
            'auto_renew'        => true,
        ]);

        app(PlanManagementService::class)->update($plan->id, [
            'trial_days' => 0,
        ]);

        $subscription = $user->subscriptions()->firstOrFail()->fresh();

        $this->assertSame('active', $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->trial_ends_at->lessThanOrEqualTo(now()));
    }

    private function createPlan(int $trialDays): Plan
    {
        return Plan::create([
            'name'            => 'Professional',
            'slug'            => 'professional-' . $trialDays,
            'price_monthly'   => 29.99,
            'price_yearly'    => 299.99,
            'currency'        => 'USD',
            'credits_monthly' => 500,
            'credits_yearly'  => 6000,
            'is_free'         => false,
            'is_active'       => true,
            'is_featured'     => true,
            'sort_order'      => 1,
            'trial_days'      => $trialDays,
        ]);
    }
}