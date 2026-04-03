<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupportTicket\AssignTicketRequest;
use App\Http\Requests\Admin\SupportTicket\ListSupportTicketsRequest;
use App\Http\Requests\Admin\SupportTicket\ReplyTicketRequest;
use App\Http\Requests\Admin\SupportTicket\UpdateSupportTicketRequest;
use App\Http\Resources\Admin\SupportTicketDetailResource;
use App\Http\Resources\Admin\SupportTicketReplyResource;
use App\Http\Resources\Admin\SupportTicketResource;
use App\Models\SupportTicket;
use App\Services\Admin\SupportTicketManagementService;
use Illuminate\Http\JsonResponse;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportTicketManagementService $service,
    ) {}

    // ──────────────────────────────────────────────
    //  LIST (GET /api/admin/support-tickets)
    // ──────────────────────────────────────────────

    public function index(ListSupportTicketsRequest $request): JsonResponse
    {
        $paginator = $this->service->list($request->validated());

        return response()->json([
            'success' => true,
            'data'    => SupportTicketResource::collection($paginator->items()),
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
    //  SHOW (GET /api/admin/support-tickets/{ticket})
    // ──────────────────────────────────────────────

    public function show(int $ticket): JsonResponse
    {
        $detail = $this->service->getDetail($ticket);

        return response()->json([
            'success' => true,
            'data'    => new SupportTicketDetailResource($detail),
        ]);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (PUT /api/admin/support-tickets/{ticket})
    // ──────────────────────────────────────────────

    public function update(UpdateSupportTicketRequest $request, SupportTicket $ticket): JsonResponse
    {
        $updated = $this->service->update($ticket, $request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully.',
            'data'    => new SupportTicketResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  ASSIGN (POST /api/admin/support-tickets/{ticket}/assign)
    // ──────────────────────────────────────────────

    public function assign(AssignTicketRequest $request, SupportTicket $ticket): JsonResponse
    {
        $assigned = $this->service->assign($ticket, $request->validated('assigned_to'), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully.',
            'data'    => new SupportTicketResource($assigned),
        ]);
    }

    // ──────────────────────────────────────────────
    //  REPLY (POST /api/admin/support-tickets/{ticket}/reply)
    // ──────────────────────────────────────────────

    public function reply(ReplyTicketRequest $request, SupportTicket $ticket): JsonResponse
    {
        $reply = $this->service->reply($ticket, $request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Reply added successfully.',
            'data'    => new SupportTicketReplyResource($reply),
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  CLOSE (POST /api/admin/support-tickets/{ticket}/close)
    // ──────────────────────────────────────────────

    public function close(SupportTicket $ticket): JsonResponse
    {
        $closed = $this->service->close($ticket, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Ticket closed successfully.',
            'data'    => new SupportTicketResource($closed),
        ]);
    }

    // ──────────────────────────────────────────────
    //  REOPEN (POST /api/admin/support-tickets/{ticket}/reopen)
    // ──────────────────────────────────────────────

    public function reopen(SupportTicket $ticket): JsonResponse
    {
        $reopened = $this->service->reopen($ticket, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Ticket reopened successfully.',
            'data'    => new SupportTicketResource($reopened),
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE (DELETE /api/admin/support-tickets/{ticket})
    // ──────────────────────────────────────────────

    public function destroy(SupportTicket $ticket): JsonResponse
    {
        $this->service->delete($ticket, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Ticket deleted (soft) successfully.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  FORCE DELETE (DELETE /api/admin/support-tickets/{ticket}/force)
    // ──────────────────────────────────────────────

    public function forceDelete(int $ticketId): JsonResponse
    {
        $this->service->forceDelete($ticketId, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Ticket permanently deleted.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  RESTORE (POST /api/admin/support-tickets/{ticket}/restore)
    // ──────────────────────────────────────────────

    public function restore(int $ticketId): JsonResponse
    {
        $restored = $this->service->restore($ticketId, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Ticket restored successfully.',
            'data'    => new SupportTicketResource($restored),
        ]);
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS (GET /api/admin/support-tickets/aggregations)
    // ──────────────────────────────────────────────

    public function aggregations(ListSupportTicketsRequest $request): JsonResponse
    {
        $data = $this->service->getAggregations($request->validated());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
