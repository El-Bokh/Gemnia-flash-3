<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Coupon\ListCouponsRequest;
use App\Http\Requests\Admin\Coupon\StoreCouponRequest;
use App\Http\Requests\Admin\Coupon\UpdateCouponRequest;
use App\Http\Requests\Admin\Coupon\ValidateCouponRequest;
use App\Http\Resources\Admin\CouponDetailResource;
use App\Http\Resources\Admin\CouponResource;
use App\Models\Coupon;
use App\Services\Admin\CouponManagementService;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponManagementService $service,
    ) {}

    // ──────────────────────────────────────────────
    //  LIST (GET /api/admin/coupons)
    // ──────────────────────────────────────────────

    public function index(ListCouponsRequest $request): JsonResponse
    {
        $paginator = $this->service->list($request->validated());

        return response()->json([
            'success' => true,
            'data'    => CouponResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  SHOW (GET /api/admin/coupons/{coupon})
    // ──────────────────────────────────────────────

    public function show(int $coupon): JsonResponse
    {
        $detail = $this->service->getDetail($coupon);

        return response()->json([
            'success' => true,
            'data'    => new CouponDetailResource($detail),
        ]);
    }

    // ──────────────────────────────────────────────
    //  CREATE (POST /api/admin/coupons)
    // ──────────────────────────────────────────────

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = $this->service->create($request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully.',
            'data'    => new CouponResource($coupon),
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (PUT /api/admin/coupons/{coupon})
    // ──────────────────────────────────────────────

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $updated = $this->service->update($coupon, $request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully.',
            'data'    => new CouponResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  TOGGLE ACTIVE (POST /api/admin/coupons/{coupon}/toggle)
    // ──────────────────────────────────────────────

    public function toggle(Coupon $coupon): JsonResponse
    {
        $toggled = $this->service->toggleActive($coupon, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => $toggled->is_active ? 'Coupon activated.' : 'Coupon deactivated.',
            'data'    => new CouponResource($toggled),
        ]);
    }

    // ──────────────────────────────────────────────
    //  VALIDATE (POST /api/admin/coupons/validate)
    // ──────────────────────────────────────────────

    public function validateCoupon(ValidateCouponRequest $request): JsonResponse
    {
        $result = $this->service->validate($request->validated());

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    // ──────────────────────────────────────────────
    //  USAGE STATS (GET /api/admin/coupons/{coupon}/usage)
    // ──────────────────────────────────────────────

    public function usage(Coupon $coupon): JsonResponse
    {
        $stats = $this->service->getUsageStats($coupon);

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE (DELETE /api/admin/coupons/{coupon})
    // ──────────────────────────────────────────────

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->service->delete($coupon, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted (soft) successfully.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  FORCE DELETE (DELETE /api/admin/coupons/{coupon}/force)
    // ──────────────────────────────────────────────

    public function forceDelete(int $coupon): JsonResponse
    {
        $this->service->forceDelete($coupon, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Coupon permanently deleted.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  RESTORE (POST /api/admin/coupons/{coupon}/restore)
    // ──────────────────────────────────────────────

    public function restore(int $coupon): JsonResponse
    {
        $restored = $this->service->restore($coupon, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Coupon restored successfully.',
            'data'    => new CouponResource($restored),
        ]);
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS (GET /api/admin/coupons/aggregations)
    // ──────────────────────────────────────────────

    public function aggregations(): JsonResponse
    {
        $data = $this->service->getAggregations();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
