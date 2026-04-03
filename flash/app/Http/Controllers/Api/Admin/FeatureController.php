<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Feature\StoreFeatureRequest;
use App\Http\Requests\Admin\Feature\UpdateFeatureRequest;
use App\Http\Resources\Admin\FeatureResource;
use App\Services\Admin\FeatureManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeatureController extends Controller
{
    public function __construct(
        private readonly FeatureManagementService $service,
    ) {}

    // ──────────────────────────────────────────────
    //  LIST (GET /api/admin/features)
    // ──────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search'    => ['sometimes', 'string', 'max:100'],
            'type'      => ['sometimes', 'string', 'in:text_to_image,image_to_image,inpainting,upscale,other'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_by'   => ['sometimes', 'string', 'in:name,slug,type,sort_order,created_at,plans_count'],
            'sort_dir'  => ['sometimes', 'string', 'in:asc,desc'],
        ]);

        $features = $this->service->list($filters);

        return response()->json([
            'success' => true,
            'data'    => FeatureResource::collection($features),
        ]);
    }

    // ──────────────────────────────────────────────
    //  SHOW (GET /api/admin/features/{feature})
    // ──────────────────────────────────────────────

    public function show(int $feature): JsonResponse
    {
        $detail = $this->service->getDetail($feature);

        return response()->json([
            'success' => true,
            'data'    => new FeatureResource($detail),
        ]);
    }

    // ──────────────────────────────────────────────
    //  CREATE (POST /api/admin/features)
    // ──────────────────────────────────────────────

    public function store(StoreFeatureRequest $request): JsonResponse
    {
        $feature = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Feature created successfully.',
            'data'    => new FeatureResource($feature),
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (PUT /api/admin/features/{feature})
    // ──────────────────────────────────────────────

    public function update(UpdateFeatureRequest $request, int $feature): JsonResponse
    {
        $updated = $this->service->update($feature, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Feature updated successfully.',
            'data'    => new FeatureResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE (DELETE /api/admin/features/{feature})
    // ──────────────────────────────────────────────

    public function destroy(int $feature): JsonResponse
    {
        $this->service->delete($feature);

        return response()->json([
            'success' => true,
            'message' => 'Feature deleted successfully.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  TOGGLE ACTIVE (POST /api/admin/features/{feature}/toggle-active)
    // ──────────────────────────────────────────────

    public function toggleActive(int $feature): JsonResponse
    {
        $updated = $this->service->toggleActive($feature);

        return response()->json([
            'success' => true,
            'message' => $updated->is_active ? 'Feature activated.' : 'Feature deactivated.',
            'data'    => new FeatureResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  ASSIGN TO PLANS (PUT /api/admin/features/{feature}/plans)
    // ──────────────────────────────────────────────

    public function assignToPlans(Request $request, int $feature): JsonResponse
    {
        $validated = $request->validate([
            'plans'                   => ['required', 'array', 'min:1'],
            'plans.*.plan_id'         => ['required', 'integer', Rule::exists('plans', 'id')],
            'plans.*.is_enabled'      => ['sometimes', 'boolean'],
            'plans.*.usage_limit'     => ['nullable', 'integer', 'min:0'],
            'plans.*.limit_period'    => ['sometimes', 'string', Rule::in(['day', 'week', 'month', 'year', 'lifetime'])],
            'plans.*.credits_per_use' => ['sometimes', 'integer', 'min:0'],
            'plans.*.constraints'     => ['sometimes', 'nullable', 'array'],
        ]);

        $updated = $this->service->assignToPlans($feature, $validated['plans']);

        return response()->json([
            'success' => true,
            'message' => 'Feature assigned to plans successfully.',
            'data'    => new FeatureResource($updated),
        ]);
    }
}
