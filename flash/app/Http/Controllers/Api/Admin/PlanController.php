<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Plan\ListPlansRequest;
use App\Http\Requests\Admin\Plan\StorePlanRequest;
use App\Http\Requests\Admin\Plan\SyncFeaturesRequest;
use App\Http\Requests\Admin\Plan\UpdatePlanRequest;
use App\Http\Resources\Admin\PlanDetailResource;
use App\Http\Resources\Admin\PlanResource;
use App\Services\Admin\PlanManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanManagementService $service,
    ) {}

    // ──────────────────────────────────────────────
    //  LIST (GET /api/admin/plans)
    // ──────────────────────────────────────────────

    public function index(ListPlansRequest $request): JsonResponse
    {
        $plans = $this->service->list($request->validated());

        return response()->json([
            'success' => true,
            'data'    => PlanResource::collection($plans),
        ]);
    }

    // ──────────────────────────────────────────────
    //  SHOW (GET /api/admin/plans/{plan})
    // ──────────────────────────────────────────────

    public function show(int $plan): JsonResponse
    {
        $detail = $this->service->getDetail($plan);

        return response()->json([
            'success' => true,
            'data'    => new PlanDetailResource($detail),
        ]);
    }

    // ──────────────────────────────────────────────
    //  CREATE (POST /api/admin/plans)
    // ──────────────────────────────────────────────

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Plan created successfully.',
            'data'    => new PlanResource($plan),
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (PUT /api/admin/plans/{plan})
    // ──────────────────────────────────────────────

    public function update(UpdatePlanRequest $request, int $plan): JsonResponse
    {
        $updated = $this->service->update($plan, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully.',
            'data'    => new PlanResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE / SOFT-DELETE (DELETE /api/admin/plans/{plan})
    // ──────────────────────────────────────────────

    public function destroy(int $plan): JsonResponse
    {
        $this->service->delete($plan);

        return response()->json([
            'success' => true,
            'message' => 'Plan deleted successfully.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  FORCE DELETE (DELETE /api/admin/plans/{plan}/force)
    // ──────────────────────────────────────────────

    public function forceDelete(int $plan): JsonResponse
    {
        $this->service->forceDelete($plan);

        return response()->json([
            'success' => true,
            'message' => 'Plan permanently deleted.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  RESTORE (POST /api/admin/plans/{plan}/restore)
    // ──────────────────────────────────────────────

    public function restore(int $plan): JsonResponse
    {
        $restored = $this->service->restore($plan);

        return response()->json([
            'success' => true,
            'message' => 'Plan restored successfully.',
            'data'    => new PlanResource($restored),
        ]);
    }

    // ──────────────────────────────────────────────
    //  DUPLICATE (POST /api/admin/plans/{plan}/duplicate)
    // ──────────────────────────────────────────────

    public function duplicate(int $plan): JsonResponse
    {
        $clone = $this->service->duplicate($plan);

        return response()->json([
            'success' => true,
            'message' => 'Plan duplicated successfully.',
            'data'    => new PlanResource($clone),
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  SYNC FEATURES (PUT /api/admin/plans/{plan}/features)
    // ──────────────────────────────────────────────

    public function syncFeatures(SyncFeaturesRequest $request, int $plan): JsonResponse
    {
        $updated = $this->service->syncPlanFeatures($plan, $request->validated('features'));

        return response()->json([
            'success' => true,
            'message' => 'Plan features synced successfully.',
            'data'    => new PlanResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  UPDATE SINGLE FEATURE LIMIT (PATCH /api/admin/plans/{plan}/features/{feature})
    // ──────────────────────────────────────────────

    public function updateFeatureLimit(Request $request, int $plan, int $feature): JsonResponse
    {
        $validated = $request->validate([
            'is_enabled'      => ['sometimes', 'boolean'],
            'usage_limit'     => ['nullable', 'integer', 'min:0'],
            'limit_period'    => ['sometimes', 'string', Rule::in(['day', 'week', 'month', 'year', 'lifetime'])],
            'credits_per_use' => ['sometimes', 'integer', 'min:0'],
            'constraints'     => ['sometimes', 'nullable', 'array'],
        ]);

        $planFeature = $this->service->updateFeatureLimit($plan, $feature, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Feature limit updated successfully.',
            'data'    => [
                'plan_id'         => $planFeature->plan_id,
                'feature_id'      => $planFeature->feature_id,
                'feature'         => $planFeature->feature ? [
                    'id'   => $planFeature->feature->id,
                    'name' => $planFeature->feature->name,
                    'slug' => $planFeature->feature->slug,
                ] : null,
                'is_enabled'      => (bool) $planFeature->is_enabled,
                'usage_limit'     => $planFeature->usage_limit,
                'limit_period'    => $planFeature->limit_period,
                'credits_per_use' => $planFeature->credits_per_use,
                'constraints'     => $planFeature->constraints,
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  TOGGLE ACTIVE (POST /api/admin/plans/{plan}/toggle-active)
    // ──────────────────────────────────────────────

    public function toggleActive(int $plan): JsonResponse
    {
        $updated = $this->service->toggleActive($plan);

        return response()->json([
            'success' => true,
            'message' => $updated->is_active ? 'Plan activated.' : 'Plan deactivated.',
            'data'    => new PlanResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  COMPARISON MATRIX (GET /api/admin/plans/comparison)
    // ──────────────────────────────────────────────

    public function comparison(): JsonResponse
    {
        $data = $this->service->getComparison();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
