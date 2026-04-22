<?php

namespace Tests\Unit;

use App\Services\GumroadService;
use Tests\TestCase;

class GumroadServiceTest extends TestCase
{
    public function test_detect_plan_uses_biannual_recurrence_for_six_month_plan(): void
    {
        $service = new GumroadService();

        $detected = $service->detectPlan([
            'option' => 'klek studio membership',
            'recurrence' => 'biannually',
        ]);

        $this->assertSame([
            'plan' => GumroadService::PLAN_SIX_MONTHS,
            'variant' => 'biannually',
        ], $detected);
    }

    public function test_detect_plan_uses_monthly_recurrence_for_monthly_plan(): void
    {
        $service = new GumroadService();

        $detected = $service->detectPlan([
            'option' => 'klek studio membership',
            'recurrence' => 'monthly',
        ]);

        $this->assertSame([
            'plan' => GumroadService::PLAN_MONTHLY,
            'variant' => 'monthly',
        ], $detected);
    }

    public function test_detect_plan_falls_back_to_sale_amount_when_recurrence_is_missing(): void
    {
        $service = new GumroadService();

        $detected = $service->detectPlan([
            'option' => 'klek studio membership',
            'sale_amount_cents' => 13000,
        ]);

        $this->assertSame([
            'plan' => GumroadService::PLAN_SIX_MONTHS,
            'variant' => 'amount_cents:13000',
        ], $detected);
    }
}