<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    /**
     * GET /api/support-tickets
     *
     * List authenticated user's support tickets (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::where('user_id', $request->user()->id)
            ->withCount('replies')
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tickets = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => collect($tickets->items())->map(fn (SupportTicket $t) => [
                'id'            => $t->id,
                'uuid'          => $t->uuid,
                'ticket_number' => $t->ticket_number,
                'subject'       => $t->subject,
                'status'        => $t->status,
                'priority'      => $t->priority,
                'category'      => $t->category,
                'replies_count' => $t->replies_count,
                'last_reply_at' => $t->last_reply_at?->toISOString(),
                'created_at'    => $t->created_at->toISOString(),
                'updated_at'    => $t->updated_at->toISOString(),
            ]),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
                'per_page'     => $tickets->perPage(),
                'total'        => $tickets->total(),
            ],
        ]);
    }

    /**
     * POST /api/support-tickets
     *
     * Create a new support ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject'  => 'required|string|max:255',
            'message'  => 'required|string|max:5000',
            'category' => 'nullable|string|max:100',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $ticketNumber = 'TKT-' . strtoupper(Str::random(8));

        $ticket = SupportTicket::create([
            'uuid'          => Str::uuid()->toString(),
            'ticket_number' => $ticketNumber,
            'user_id'       => $request->user()->id,
            'subject'       => $validated['subject'],
            'message'       => $validated['message'],
            'category'      => $validated['category'] ?? null,
            'priority'      => $validated['priority'] ?? 'medium',
            'status'        => 'open',
        ]);

        $this->notifySupportTeam(
            $ticket,
            'ticket_created',
            'New Support Ticket',
            "{$request->user()->name} created ticket #{$ticket->ticket_number} — {$ticket->subject}.",
            [
                'ticket_id'     => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'created_by'    => $request->user()->id,
            ],
            $ticket->priority === 'high' || $ticket->priority === 'urgent' ? 'high' : 'normal',
            [$request->user()->id],
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully.',
            'data'    => [
                'id'            => $ticket->id,
                'uuid'          => $ticket->uuid,
                'ticket_number' => $ticket->ticket_number,
                'subject'       => $ticket->subject,
                'status'        => $ticket->status,
                'priority'      => $ticket->priority,
                'category'      => $ticket->category,
                'created_at'    => $ticket->created_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * GET /api/support-tickets/{ticket}
     *
     * Show a single ticket with its replies.
     */
    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized.');
        }

        $ticket->load([
            'replies' => fn ($q) => $q->with('user:id,name,avatar')->orderBy('created_at'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $ticket->id,
                'uuid'          => $ticket->uuid,
                'ticket_number' => $ticket->ticket_number,
                'subject'       => $ticket->subject,
                'message'       => $ticket->message,
                'status'        => $ticket->status,
                'priority'      => $ticket->priority,
                'category'      => $ticket->category,
                'last_reply_at' => $ticket->last_reply_at?->toISOString(),
                'created_at'    => $ticket->created_at->toISOString(),
                'updated_at'    => $ticket->updated_at->toISOString(),
                'replies'       => $ticket->replies->map(fn (SupportTicketReply $r) => [
                    'id'             => $r->id,
                    'message'        => $r->message,
                    'is_staff_reply' => $r->is_staff_reply,
                    'created_at'     => $r->created_at->toISOString(),
                    'user'           => [
                        'id'     => $r->user->id,
                        'name'   => $r->user->name,
                        'avatar' => $r->user->avatar,
                    ],
                ]),
            ],
        ]);
    }

    /**
     * POST /api/support-tickets/{ticket}/reply
     *
     * User replies to their own ticket.
     */
    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized.');
        }

        if ($ticket->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reply to a closed ticket.',
            ], 422);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $reply = SupportTicketReply::create([
            'ticket_id'      => $ticket->id,
            'user_id'        => $request->user()->id,
            'message'        => $validated['message'],
            'is_staff_reply' => false,
        ]);

        $ticket->update([
            'last_reply_at' => now(),
            'status'        => $ticket->status === 'resolved' ? 'open' : ($ticket->status === 'in_progress' ? 'waiting_reply' : $ticket->status),
        ]);

        if ($ticket->assigned_to) {
            Notification::create([
                'user_id'    => $ticket->assigned_to,
                'type'       => 'ticket_client_reply',
                'title'      => 'Client Replied to Ticket',
                'body'       => "Client replied to ticket #{$ticket->ticket_number} — {$ticket->subject}.",
                'icon'       => 'pi pi-reply',
                'action_url' => "/admin/support?ticket={$ticket->id}",
                'channel'    => 'in_app',
                'priority'   => 'normal',
                'sent_at'    => now(),
                'data'       => [
                    'ticket_id'     => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'reply_id'      => $reply->id,
                ],
            ]);
        } else {
            $this->notifySupportTeam(
                $ticket,
                'ticket_client_reply',
                'Client Replied to Ticket',
                "Client replied to ticket #{$ticket->ticket_number} — {$ticket->subject}.",
                [
                    'ticket_id'     => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'reply_id'      => $reply->id,
                ],
                'normal',
                [$request->user()->id],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply sent.',
            'data'    => [
                'id'             => $reply->id,
                'message'        => $reply->message,
                'is_staff_reply' => $reply->is_staff_reply,
                'created_at'     => $reply->created_at->toISOString(),
                'user'           => [
                    'id'     => $request->user()->id,
                    'name'   => $request->user()->name,
                    'avatar' => $request->user()->avatar,
                ],
            ],
        ], 201);
    }

    private function notifySupportTeam(
        SupportTicket $ticket,
        string $type,
        string $title,
        string $body,
        array $data,
        string $priority = 'normal',
        array $excludeUserIds = [],
    ): void {
        $recipientIds = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super_admin', 'admin', 'support', 'moderator', 'agent']))
            ->whereNotIn('id', $excludeUserIds)
            ->pluck('id');

        foreach ($recipientIds as $recipientId) {
            Notification::create([
                'user_id'    => $recipientId,
                'type'       => $type,
                'title'      => $title,
                'body'       => $body,
                'icon'       => 'pi pi-ticket',
                'action_url' => "/admin/support?ticket={$ticket->id}",
                'channel'    => 'in_app',
                'priority'   => $priority,
                'sent_at'    => now(),
                'data'       => $data,
            ]);
        }
    }
}
