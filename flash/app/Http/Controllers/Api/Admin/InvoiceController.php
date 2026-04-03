<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Invoice\ListInvoicesRequest;
use App\Http\Requests\Admin\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\Admin\InvoiceDetailResource;
use App\Http\Resources\Admin\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Admin\InvoiceManagementService;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceManagementService $service,
    ) {}

    // ──────────────────────────────────────────────
    //  LIST (GET /api/admin/invoices)
    // ──────────────────────────────────────────────

    public function index(ListInvoicesRequest $request): JsonResponse
    {
        $paginator = $this->service->list($request->validated());

        return response()->json([
            'success' => true,
            'data'    => InvoiceResource::collection($paginator->items()),
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
    //  SHOW (GET /api/admin/invoices/{invoice})
    // ──────────────────────────────────────────────

    public function show(int $invoice): JsonResponse
    {
        $detail = $this->service->getDetail($invoice);

        return response()->json([
            'success' => true,
            'data'    => new InvoiceDetailResource($detail),
        ]);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (PUT /api/admin/invoices/{invoice})
    // ──────────────────────────────────────────────

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $updated = $this->service->update($invoice, $request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Invoice updated successfully.',
            'data'    => new InvoiceResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  GENERATE FROM PAYMENT (POST /api/admin/invoices/generate/{payment})
    // ──────────────────────────────────────────────

    public function generateFromPayment(Payment $payment): JsonResponse
    {
        $invoice = $this->service->generateFromPayment($payment, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Invoice generated successfully.',
            'data'    => new InvoiceDetailResource($invoice),
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  DOWNLOAD DATA (GET /api/admin/invoices/{invoice}/download)
    // ──────────────────────────────────────────────

    public function download(int $invoice): JsonResponse
    {
        $data = $this->service->getInvoiceData($invoice);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE (DELETE /api/admin/invoices/{invoice})
    // ──────────────────────────────────────────────

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->service->delete($invoice, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Invoice deleted (soft) successfully.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  FORCE DELETE (DELETE /api/admin/invoices/{invoice}/force)
    // ──────────────────────────────────────────────

    public function forceDelete(int $invoice): JsonResponse
    {
        $this->service->forceDelete($invoice, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Invoice permanently deleted.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  RESTORE (POST /api/admin/invoices/{invoice}/restore)
    // ──────────────────────────────────────────────

    public function restore(int $invoice): JsonResponse
    {
        $restored = $this->service->restore($invoice, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Invoice restored successfully.',
            'data'    => new InvoiceResource($restored),
        ]);
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS (GET /api/admin/invoices/aggregations)
    // ──────────────────────────────────────────────

    public function aggregations(ListInvoicesRequest $request): JsonResponse
    {
        $data = $this->service->getAggregations($request->validated());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
