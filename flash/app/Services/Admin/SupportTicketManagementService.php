<?php

namespace App\Services\Admin;

use App\Models\Notification;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\UsageLog;
use App\Support\SupportTicketAttachmentManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SupportTicketManagementService
{
    // ──────────────────────────────────────────────
    //  LIST (paginated with filters)
    // ──────────────────────────────────────────────

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->with([
                'user:id,name,email,avatar',
                'user.subscriptions' => fn ($q) => $q->with('plan:id,name,slug')
                    ->orderByDesc('created_at')
                    ->limit(1),
                'assignedAgent:id,name,email,avatar',
            ])
            ->withCount('replies');

        // ── Trashed ──
        if (! empty($filters['trashed'])) {
            $filters['trashed'] === 'only'
                ? $query->onlyTrashed()
                : $query->withTrashed();
        }

        // ── Search (ticket_number, uuid, subject, message, user name/email) ──
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $q) use ($term) {
                $q->where('ticket_number', 'LIKE', "%{$term}%")
                  ->orWhere('uuid', 'LIKE', "%{$term}%")
                  ->orWhere('subject', 'LIKE', "%{$term}%")
                  ->orWhere('message', 'LIKE', "%{$term}%")
                  ->orWhereHas('user', function (Builder $uq) use ($term) {
                      $uq->where('name', 'LIKE', "%{$term}%")
                         ->orWhere('email', 'LIKE', "%{$term}%");
                  });
            });
        }

        // ── Exact filters ──
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // ── Unassigned filter ──
        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $query->whereNull('assigned_to');
        }

        // ── Date ranges ──
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['last_reply_from'])) {
            $query->whereDate('last_reply_at', '>=', $filters['last_reply_from']);
        }
        if (! empty($filters['last_reply_to'])) {
            $query->whereDate('last_reply_at', '<=', $filters['last_reply_to']);
        }

        // ── Sort ──
        $sortBy  = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    // ──────────────────────────────────────────────
    //  DETAIL (with full conversation thread)
    // ──────────────────────────────────────────────

    public function getDetail(int $id): SupportTicket
    {
        return SupportTicket::withTrashed()
            ->with([
                'user:id,name,email,avatar,phone,status',
                'user.subscriptions' => fn ($q) => $q->with('plan:id,name,slug')
                    ->orderByDesc('created_at'),
                'assignedAgent:id,name,email,avatar',
                'replies' => fn ($q) => $q->with('user:id,name,email,avatar')
                    ->orderBy('created_at'),
            ])
            ->withCount('replies')
            ->findOrFail($id);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (status, priority, category, metadata)
    // ──────────────────────────────────────────────

    public function update(SupportTicket $ticket, array $data, int $adminId): SupportTicket
    {
        $changes = [];

        // Track status change
        if (isset($data['status']) && $data['status'] !== $ticket->status) {
            $changes['status'] = ['from' => $ticket->status, 'to' => $data['status']];

            // Auto-set timestamps based on status
            if ($data['status'] === 'resolved') {
                $data['resolved_at'] = now();
                $data['closed_at'] = null;
            }
            if ($data['status'] === 'closed') {
                $data['closed_at'] = now();
                $data['resolved_at'] = $ticket->resolved_at ?? now();
            }
            // Clear timestamps if moved back into an active workflow state
            if (in_array($data['status'], ['open', 'in_progress', 'waiting_reply'], true)) {
                $data['resolved_at'] = null;
                $data['closed_at']   = null;
            }
        }

        // Track priority change
        if (isset($data['priority']) && $data['priority'] !== $ticket->priority) {
            $changes['priority'] = ['from' => $ticket->priority, 'to' => $data['priority']];
        }

        $ticket->update($data);

        if (! empty($changes)) {
            $this->logAction($ticket, $adminId, 'ticket_updated', $changes);
        }

        return $ticket->fresh([
            'user:id,name,email,avatar',
            'assignedAgent:id,name,email,avatar',
        ]);
    }

    // ──────────────────────────────────────────────
    //  ASSIGN TO AGENT
    // ──────────────────────────────────────────────

    public function assign(SupportTicket $ticket, int $agentId, int $adminId): SupportTicket
    {
        $oldAgent = $ticket->assigned_to;

        $ticket->update(['assigned_to' => $agentId]);

        // If ticket is open, move to in_progress when assigned
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        $this->logAction($ticket, $adminId, 'ticket_assigned', [
            'old_agent_id' => $oldAgent,
            'new_agent_id' => $agentId,
        ]);

        // Notify the assigned agent
        $this->createNotification(
            userId: $agentId,
            type: 'ticket_assigned',
            title: 'Ticket Assigned to You',
            body: "Ticket #{$ticket->ticket_number} — {$ticket->subject} has been assigned to you.",
            actionUrl: "/admin/support?ticket={$ticket->id}",
            data: [
                'ticket_id'      => $ticket->id,
                'ticket_number'  => $ticket->ticket_number,
                'assigned_by'    => $adminId,
            ],
        );

        return $ticket->fresh([
            'user:id,name,email,avatar',
            'assignedAgent:id,name,email,avatar',
        ]);
    }

    // ──────────────────────────────────────────────
    //  REPLY (admin/staff replies to ticket)
    // ──────────────────────────────────────────────

    public function reply(SupportTicket $ticket, array $data, int $adminId): SupportTicketReply
    {
        if ($ticket->status === 'closed') {
            abort(422, 'Cannot reply to a closed ticket. Reopen it first.');
        }

        $reply = SupportTicketReply::create([
            'ticket_id'     => $ticket->id,
            'user_id'       => $adminId,
            'message'       => $data['message'],
            'is_staff_reply'=> true,
            'attachments'   => SupportTicketAttachmentManager::storeUploadedFiles($data['attachments'] ?? [], $adminId, 'admin-replies'),
        ]);

        // Update ticket state
        $updateData = [
            'last_reply_at' => now(),
            'status' => 'waiting_reply',
            'resolved_at' => null,
            'closed_at' => null,
        ];
        $ticket->update($updateData);

        $this->logAction($ticket, $adminId, 'ticket_reply_added', [
            'reply_id' => $reply->id,
        ]);

        // Notify the ticket owner
        $this->createNotification(
            userId: $ticket->user_id,
            type: 'ticket_reply',
            title: 'New Reply on Your Ticket',
            body: "Support has replied to your ticket #{$ticket->ticket_number} — {$ticket->subject}.",
            actionUrl: "/support?ticket={$ticket->id}",
            data: [
                'ticket_id'     => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'reply_id'      => $reply->id,
                'replied_by'    => $adminId,
            ],
        );

        return $reply->fresh('user:id,name,email,avatar');
    }

    // ──────────────────────────────────────────────
    //  RESOLVE TICKET
    // ──────────────────────────────────────────────

    public function resolve(SupportTicket $ticket, int $adminId): SupportTicket
    {
        if ($ticket->status === 'resolved') {
            abort(422, 'Ticket is already resolved.');
        }

        if ($ticket->status === 'closed') {
            abort(422, 'Closed ticket cannot be resolved. Reopen it first.');
        }

        $oldStatus = $ticket->status;

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'closed_at' => null,
        ]);

        $this->logAction($ticket, $adminId, 'ticket_resolved', [
            'old_status' => $oldStatus,
        ]);

        $this->createNotification(
            userId: $ticket->user_id,
            type: 'ticket_resolved',
            title: 'Your Ticket Has Been Resolved',
            body: "Ticket #{$ticket->ticket_number} — {$ticket->subject} has been marked as resolved.",
            actionUrl: "/support?ticket={$ticket->id}",
            data: [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'resolved_by' => $adminId,
            ],
        );

        return $ticket->fresh([
            'user:id,name,email,avatar',
            'assignedAgent:id,name,email,avatar',
        ]);
    }

    // ──────────────────────────────────────────────
    //  CLOSE TICKET
    // ──────────────────────────────────────────────

    public function close(SupportTicket $ticket, int $adminId): SupportTicket
    {
        if ($ticket->status === 'closed') {
            abort(422, 'Ticket is already closed.');
        }

        $oldStatus = $ticket->status;

        $ticket->update([
            'status'    => 'closed',
            'closed_at' => now(),
            'resolved_at' => $ticket->resolved_at ?? now(),
        ]);

        $this->logAction($ticket, $adminId, 'ticket_closed', [
            'old_status' => $oldStatus,
        ]);

        // Notify the ticket owner
        $this->createNotification(
            userId: $ticket->user_id,
            type: 'ticket_closed',
            title: 'Your Ticket Has Been Closed',
            body: "Ticket #{$ticket->ticket_number} — {$ticket->subject} has been closed.",
            actionUrl: "/support?ticket={$ticket->id}",
            data: [
                'ticket_id'     => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'closed_by'     => $adminId,
            ],
        );

        return $ticket->fresh([
            'user:id,name,email,avatar',
            'assignedAgent:id,name,email,avatar',
        ]);
    }

    // ──────────────────────────────────────────────
    //  REOPEN TICKET
    // ──────────────────────────────────────────────

    public function reopen(SupportTicket $ticket, int $adminId): SupportTicket
    {
        if (! in_array($ticket->status, ['closed', 'resolved'])) {
            abort(422, 'Only closed or resolved tickets can be reopened.');
        }

        $oldStatus = $ticket->status;

        $ticket->update([
            'status'      => 'open',
            'closed_at'   => null,
            'resolved_at' => null,
        ]);

        $this->logAction($ticket, $adminId, 'ticket_reopened', [
            'old_status' => $oldStatus,
        ]);

        // Notify the ticket owner
        $this->createNotification(
            userId: $ticket->user_id,
            type: 'ticket_reopened',
            title: 'Your Ticket Has Been Reopened',
            body: "Ticket #{$ticket->ticket_number} — {$ticket->subject} has been reopened.",
            actionUrl: "/support?ticket={$ticket->id}",
            data: [
                'ticket_id'     => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'reopened_by'   => $adminId,
            ],
        );

        // Notify assigned agent if exists
        if ($ticket->assigned_to) {
            $this->createNotification(
                userId: $ticket->assigned_to,
                type: 'ticket_reopened',
                title: 'Assigned Ticket Reopened',
                body: "Ticket #{$ticket->ticket_number} — {$ticket->subject} has been reopened.",
                actionUrl: "/admin/support?ticket={$ticket->id}",
                data: [
                    'ticket_id'     => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'reopened_by'   => $adminId,
                ],
            );
        }

        return $ticket->fresh([
            'user:id,name,email,avatar',
            'assignedAgent:id,name,email,avatar',
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE / FORCE-DELETE / RESTORE
    // ──────────────────────────────────────────────

    public function delete(SupportTicket $ticket, int $adminId): bool
    {
        if (in_array($ticket->status, ['open', 'in_progress'])) {
            abort(422, 'Cannot delete an active ticket. Close it first.');
        }

        $this->logAction($ticket, $adminId, 'ticket_deleted');
        return $ticket->delete();
    }

    public function forceDelete(int $id, int $adminId): bool
    {
        $ticket = SupportTicket::withTrashed()->findOrFail($id);
        $this->logAction($ticket, $adminId, 'ticket_force_deleted');
        return $ticket->forceDelete();
    }

    public function restore(int $id, int $adminId): SupportTicket
    {
        $ticket = SupportTicket::onlyTrashed()->findOrFail($id);
        $ticket->restore();
        $this->logAction($ticket, $adminId, 'ticket_restored');

        return $ticket->fresh([
            'user:id,name,email,avatar',
            'assignedAgent:id,name,email,avatar',
        ]);
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS
    // ──────────────────────────────────────────────

    public function getAggregations(array $filters = []): array
    {
        $baseQuery = SupportTicket::query();

        if (! empty($filters['date_from'])) {
            $baseQuery->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $baseQuery->whereDate('created_at', '<=', $filters['date_to']);
        }

        // ── Summary stats ──
        $stats = (clone $baseQuery)->select([
            DB::raw('COUNT(*) as total_tickets'),
            DB::raw("COUNT(CASE WHEN status = 'open' THEN 1 END) as open_count"),
            DB::raw("COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count"),
            DB::raw("COUNT(CASE WHEN status = 'waiting_reply' THEN 1 END) as waiting_reply_count"),
            DB::raw("COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_count"),
            DB::raw("COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_count"),
            DB::raw("COUNT(CASE WHEN assigned_to IS NULL AND status NOT IN ('closed','resolved') THEN 1 END) as unassigned_active_count"),
        ])->first();

        // ── By priority ──
        $byPriority = (clone $baseQuery)
            ->select([
                'priority',
                DB::raw('COUNT(*) as count'),
                DB::raw("COUNT(CASE WHEN status NOT IN ('closed','resolved') THEN 1 END) as active_count"),
            ])
            ->groupBy('priority')
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->get();

        // ── By category ──
        $byCategory = (clone $baseQuery)
            ->whereNotNull('category')
            ->select([
                'category',
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();

        // ── Agent performance (top agents by resolved tickets) ──
        $agentPerformance = SupportTicket::query()
            ->whereNotNull('assigned_to')
            ->join('users', 'support_tickets.assigned_to', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(support_tickets.id) as total_assigned'),
                DB::raw("COUNT(CASE WHEN support_tickets.status IN ('closed','resolved') THEN 1 END) as resolved_count"),
                DB::raw("COUNT(CASE WHEN support_tickets.status NOT IN ('closed','resolved') THEN 1 END) as active_count"),
                DB::raw("AVG(TIMESTAMPDIFF(HOUR, support_tickets.created_at, COALESCE(support_tickets.resolved_at, NOW()))) as avg_resolution_hours"),
            ])
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('resolved_count')
            ->limit(10)
            ->get();

        // ── Daily trend (last 30 days) ──
        $days = (int) ($filters['trend_days'] ?? 30);
        $dailyTrend = SupportTicket::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as created'),
                DB::raw("COUNT(CASE WHEN status IN ('closed','resolved') THEN 1 END) as resolved"),
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // ── Average response time (first staff reply) ──
        $avgFirstResponse = DB::table('support_tickets')
            ->join('support_ticket_replies', function ($join) {
                $join->on('support_tickets.id', '=', 'support_ticket_replies.ticket_id')
                     ->where('support_ticket_replies.is_staff_reply', true);
            })
            ->whereNull('support_tickets.deleted_at')
            ->whereNull('support_ticket_replies.deleted_at')
            ->select([
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, support_tickets.created_at, support_ticket_replies.created_at)) as avg_first_response_minutes'),
            ])
            ->first();

        return [
            'summary'            => $stats,
            'by_priority'        => $byPriority,
            'by_category'        => $byCategory,
            'agent_performance'  => $agentPerformance,
            'daily_trend'        => $dailyTrend,
            'avg_first_response' => $avgFirstResponse,
        ];
    }

    // ──────────────────────────────────────────────
    //  PRIVATE: Notification helper
    // ──────────────────────────────────────────────

    private function createNotification(
        int $userId,
        string $type,
        string $title,
        string $body,
        ?string $actionUrl = null,
        array $data = [],
    ): void {
        Notification::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'icon'       => 'support',
            'action_url' => $actionUrl,
            'channel'    => 'in_app',
            'priority'   => 'normal',
            'sent_at'    => now(),
            'data'       => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    //  PRIVATE: Audit log
    // ──────────────────────────────────────────────

    private function logAction(SupportTicket $ticket, int $adminId, string $action, array $extra = []): void
    {
        UsageLog::create([
            'user_id'  => $ticket->user_id,
            'action'   => $action,
            'metadata' => array_merge([
                'ticket_id'      => $ticket->id,
                'ticket_number'  => $ticket->ticket_number,
                'admin_id'       => $adminId,
            ], $extra),
        ]);
    }
}
