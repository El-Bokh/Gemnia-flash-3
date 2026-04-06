<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\SubscriptionService;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * GET /api/subscription
     *
     * Return the current user's active subscription & quota info.
     */
    public function show(Request $request): JsonResponse
    {
        $usageService = new UsageService();
        $quota = $usageService->getUsageStats($request->user());

        return response()->json([
            'success' => true,
            'data'    => $quota,
        ]);
    }

    /**
     * GET /api/plans/public
     *
     * Return all active plans for the upgrade/pricing page.
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->with(['features' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->map(function (Plan $plan) {
                return [
                    'id'              => $plan->id,
                    'name'            => $plan->name,
                    'slug'            => $plan->slug,
                    'description'     => $plan->description,
                    'price_monthly'   => $plan->price_monthly,
                    'price_yearly'    => $plan->price_yearly,
                    'currency'        => $plan->currency,
                    'credits_monthly' => $plan->credits_monthly,
                    'credits_yearly'  => $plan->credits_yearly,
                    'is_free'         => $plan->is_free,
                    'is_featured'     => $plan->is_featured,
                    'trial_days'      => $plan->trial_days,
                    'features'        => $plan->features->map(fn ($f) => [
                        'name'           => $f->name,
                        'slug'           => $f->slug,
                        'credits_per_use'=> $f->pivot->credits_per_use,
                        'usage_limit'    => $f->pivot->usage_limit,
                    ]),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    /**
     * POST /api/subscription/upgrade
     *
     * Upgrade or change the user's plan.
     */
    public function upgrade(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id'       => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['sometimes', 'string', 'in:monthly,yearly'],
        ]);

        $subscriptionService = app(SubscriptionService::class);
        $result = $subscriptionService->changePlan(
            $request->user(),
            $data['plan_id'],
            $data['billing_cycle'] ?? 'monthly'
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        // Return updated quota alongside the subscription
        $usageService = new UsageService();
        $quota = $usageService->getUsageStats($request->user());

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => [
                'subscription' => $result['subscription'],
                'quota'        => $quota,
            ],
        ]);
    }

    /**
     * POST /api/subscription/cancel
     *
     * Cancel the user's active subscription (downgrade to free).
     */
    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $subscriptionService = app(SubscriptionService::class);
        $result = $subscriptionService->cancel(
            $request->user(),
            $data['reason'] ?? null
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        // Return updated quota
        $usageService = new UsageService();
        $quota = $usageService->getUsageStats($request->user());

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => [
                'quota' => $quota,
            ],
        ]);
    }
}
