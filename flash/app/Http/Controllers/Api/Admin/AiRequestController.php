<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiRequest\BulkAiRequestsRequest;
use App\Http\Requests\Admin\AiRequest\ListAiRequestsRequest;
use App\Http\Requests\Admin\AiRequest\UpdateAiRequestRequest;
use App\Http\Resources\Admin\AiRequestDetailResource;
use App\Http\Resources\Admin\AiRequestResource;
use App\Services\Admin\AiRequestManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiRequestController extends Controller
{
    public function __construct(
        private readonly AiRequestManagementService $service,
    ) {}

    // ──────────────────────────────────────────────
    //  LIST (GET /api/admin/ai-requests)
    // ──────────────────────────────────────────────

    public function index(ListAiRequestsRequest $request): JsonResponse
    {
        $paginator = $this->service->list($request->validated());

        return response()->json([
            'success' => true,
            'data'    => AiRequestResource::collection($paginator->items()),
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
    //  SHOW (GET /api/admin/ai-requests/{aiRequest})
    // ──────────────────────────────────────────────

    public function show(int $aiRequest): JsonResponse
    {
        $detail = $this->service->getDetail($aiRequest);

        return response()->json([
            'success' => true,
            'data'    => new AiRequestDetailResource($detail),
        ]);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (PUT /api/admin/ai-requests/{aiRequest})
    // ──────────────────────────────────────────────

    public function update(UpdateAiRequestRequest $request, int $aiRequest): JsonResponse
    {
        $updated = $this->service->update($aiRequest, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'AI request updated successfully.',
            'data'    => new AiRequestResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE (DELETE /api/admin/ai-requests/{aiRequest})
    // ──────────────────────────────────────────────

    public function destroy(int $aiRequest): JsonResponse
    {
        $this->service->delete($aiRequest);

        return response()->json([
            'success' => true,
            'message' => 'AI request deleted successfully.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  FORCE DELETE (DELETE /api/admin/ai-requests/{aiRequest}/force)
    // ──────────────────────────────────────────────

    public function forceDelete(int $aiRequest): JsonResponse
    {
        $this->service->forceDelete($aiRequest);

        return response()->json([
            'success' => true,
            'message' => 'AI request permanently deleted with all related data.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  RESTORE (POST /api/admin/ai-requests/{aiRequest}/restore)
    // ──────────────────────────────────────────────

    public function restore(int $aiRequest): JsonResponse
    {
        $restored = $this->service->restore($aiRequest);

        return response()->json([
            'success' => true,
            'message' => 'AI request restored successfully.',
            'data'    => new AiRequestResource($restored),
        ]);
    }

    // ──────────────────────────────────────────────
    //  RETRY (POST /api/admin/ai-requests/{aiRequest}/retry)
    // ──────────────────────────────────────────────

    public function retry(int $aiRequest): JsonResponse
    {
        $retried = $this->service->retry($aiRequest);

        return response()->json([
            'success' => true,
            'message' => 'AI request queued for retry.',
            'data'    => new AiRequestResource($retried),
        ]);
    }

    // ──────────────────────────────────────────────
    //  CANCEL (POST /api/admin/ai-requests/{aiRequest}/cancel)
    // ──────────────────────────────────────────────

    public function cancel(int $aiRequest): JsonResponse
    {
        $cancelled = $this->service->cancel($aiRequest);

        return response()->json([
            'success' => true,
            'message' => 'AI request cancelled successfully.',
            'data'    => new AiRequestResource($cancelled),
        ]);
    }

    // ──────────────────────────────────────────────
    //  BULK RETRY (POST /api/admin/ai-requests/bulk-retry)
    // ──────────────────────────────────────────────

    public function bulkRetry(BulkAiRequestsRequest $request): JsonResponse
    {
        $results = $this->service->bulkRetry($request->validated('request_ids'));

        return response()->json([
            'success' => true,
            'message' => "{$results['retried_count']} request(s) queued for retry, {$results['skipped_count']} skipped.",
            'data'    => $results,
        ]);
    }

    // ──────────────────────────────────────────────
    //  BULK DELETE (POST /api/admin/ai-requests/bulk-delete)
    // ──────────────────────────────────────────────

    public function bulkDelete(BulkAiRequestsRequest $request): JsonResponse
    {
        $results = $this->service->bulkDelete($request->validated('request_ids'));

        return response()->json([
            'success' => true,
            'message' => "{$results['deleted_count']} request(s) deleted, {$results['skipped_count']} skipped.",
            'data'    => $results,
        ]);
    }

    // ──────────────────────────────────────────────
    //  NOTIFY USER (POST /api/admin/ai-requests/{aiRequest}/notify)
    // ──────────────────────────────────────────────

    public function notify(Request $request, int $aiRequest): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:completed,failed'],
        ]);

        $notification = $this->service->notifyUser($aiRequest, $validated['type']);

        return response()->json([
            'success' => true,
            'message' => 'Notification sent to user.',
            'data'    => [
                'id'    => $notification->id,
                'uuid'  => $notification->uuid,
                'type'  => $notification->type,
                'title' => $notification->title,
                'body'  => $notification->body,
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS (GET /api/admin/ai-requests/aggregations)
    // ──────────────────────────────────────────────

    public function aggregations(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'date_from' => ['sometimes', 'date'],
            'date_to'   => ['sometimes', 'date', 'after_or_equal:date_from'],
        ]);

        $data = $this->service->getAggregations($filters);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
