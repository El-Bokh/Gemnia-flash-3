<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payment\ListPaymentsRequest;
use App\Http\Requests\Admin\Payment\RefundPaymentRequest;
use App\Http\Requests\Admin\Payment\UpdatePaymentRequest;
use App\Http\Resources\Admin\PaymentDetailResource;
use App\Http\Resources\Admin\PaymentResource;
use App\Models\Payment;
use App\Services\Admin\PaymentManagementService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentManagementService $service,
    ) {}

    // ──────────────────────────────────────────────
    //  LIST (GET /api/admin/payments)
    // ──────────────────────────────────────────────

    public function index(ListPaymentsRequest $request): JsonResponse
    {
        $paginator = $this->service->list($request->validated());

        return response()->json([
            'success' => true,
            'data'    => PaymentResource::collection($paginator->items()),
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
    //  SHOW (GET /api/admin/payments/{payment})
    // ──────────────────────────────────────────────

    public function show(int $payment): JsonResponse
    {
        $detail = $this->service->getDetail($payment);

        return response()->json([
            'success' => true,
            'data'    => new PaymentDetailResource($detail),
        ]);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (PUT /api/admin/payments/{payment})
    // ──────────────────────────────────────────────

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $updated = $this->service->update($payment, $request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment updated successfully.',
            'data'    => new PaymentResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  REFUND (POST /api/admin/payments/{payment}/refund)
    // ──────────────────────────────────────────────

    public function refund(RefundPaymentRequest $request, Payment $payment): JsonResponse
    {
        $refunded = $this->service->processRefund($payment, $request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment refunded successfully.',
            'data'    => new PaymentDetailResource($refunded),
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE (DELETE /api/admin/payments/{payment})
    // ──────────────────────────────────────────────

    public function destroy(Payment $payment): JsonResponse
    {
        $this->service->delete($payment, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment deleted (soft) successfully.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  FORCE DELETE (DELETE /api/admin/payments/{payment}/force)
    // ──────────────────────────────────────────────

    public function forceDelete(int $payment): JsonResponse
    {
        $this->service->forceDelete($payment, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment permanently deleted.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  RESTORE (POST /api/admin/payments/{payment}/restore)
    // ──────────────────────────────────────────────

    public function restore(int $payment): JsonResponse
    {
        $restored = $this->service->restore($payment, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment restored successfully.',
            'data'    => new PaymentResource($restored),
        ]);
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS (GET /api/admin/payments/aggregations)
    // ──────────────────────────────────────────────

    public function aggregations(ListPaymentsRequest $request): JsonResponse
    {
        $data = $this->service->getAggregations($request->validated());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
