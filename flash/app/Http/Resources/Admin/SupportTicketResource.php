<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'uuid'          => $this->uuid,
            'ticket_number' => $this->ticket_number,
            'subject'       => $this->subject,
            'message_preview'=> \Illuminate\Support\Str::limit(strip_tags($this->message), 120),
            'status'        => $this->status,
            'priority'      => $this->priority,
            'category'      => $this->category,

            // ── Counts ──
            'replies_count' => $this->whenCounted('replies'),

            // ── Dates ──
            'last_reply_at' => $this->last_reply_at?->toIso8601String(),
            'resolved_at'   => $this->resolved_at?->toIso8601String(),
            'closed_at'     => $this->closed_at?->toIso8601String(),
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
            'deleted_at'    => $this->deleted_at?->toIso8601String(),

            // ── User (ticket owner) ──
            'user' => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'email'  => $this->user->email,
                'avatar' => $this->user->avatarUrl(),
            ]),

            // ── Assigned Agent ──
            'assigned_agent' => $this->whenLoaded('assignedAgent', fn () => $this->assignedAgent ? [
                'id'     => $this->assignedAgent->id,
                'name'   => $this->assignedAgent->name,
                'email'  => $this->assignedAgent->email,
                'avatar' => $this->assignedAgent->avatarUrl(),
            ] : null),

            // ── User's active subscription (quick context) ──
            'user_subscription' => $this->whenLoaded('user', function () {
                $sub = $this->user->subscriptions?->first();
                if (! $sub) return null;
                return [
                    'id'            => $sub->id,
                    'status'        => $sub->status,
                    'billing_cycle' => $sub->billing_cycle,
                    'plan'          => $sub->plan ? [
                        'id'   => $sub->plan->id,
                        'name' => $sub->plan->name,
                        'slug' => $sub->plan->slug,
                    ] : null,
                ];
            }),
        ];
    }
}
