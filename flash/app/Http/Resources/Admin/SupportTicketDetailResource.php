<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'uuid'          => $this->uuid,
            'ticket_number' => $this->ticket_number,
            'subject'       => $this->subject,
            'message'       => $this->message,
            'status'        => $this->status,
            'priority'      => $this->priority,
            'category'      => $this->category,
            'attachments'   => $this->attachments,
            'metadata'      => $this->metadata,

            // ── Dates ──
            'last_reply_at' => $this->last_reply_at?->toIso8601String(),
            'resolved_at'   => $this->resolved_at?->toIso8601String(),
            'closed_at'     => $this->closed_at?->toIso8601String(),
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
            'deleted_at'    => $this->deleted_at?->toIso8601String(),

            // ── Counts ──
            'replies_count' => $this->whenCounted('replies'),

            // ── User (ticket owner) with subscription context ──
            'user' => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'email'  => $this->user->email,
                'avatar' => $this->user->avatar,
                'phone'  => $this->user->phone,
                'status' => $this->user->status,
                'subscription' => $this->user->subscriptions?->sortByDesc('created_at')->first()
                    ? (function () {
                        $sub = $this->user->subscriptions->sortByDesc('created_at')->first();
                        return [
                            'id'            => $sub->id,
                            'status'        => $sub->status,
                            'billing_cycle' => $sub->billing_cycle,
                            'starts_at'     => $sub->starts_at?->toIso8601String(),
                            'ends_at'       => $sub->ends_at?->toIso8601String(),
                            'plan'          => $sub->plan ? [
                                'id'   => $sub->plan->id,
                                'name' => $sub->plan->name,
                                'slug' => $sub->plan->slug,
                            ] : null,
                        ];
                    })()
                    : null,
            ]),

            // ── Assigned Agent ──
            'assigned_agent' => $this->whenLoaded('assignedAgent', fn () => $this->assignedAgent ? [
                'id'     => $this->assignedAgent->id,
                'name'   => $this->assignedAgent->name,
                'email'  => $this->assignedAgent->email,
                'avatar' => $this->assignedAgent->avatar,
            ] : null),

            // ── Conversation Thread (replies ordered chronologically) ──
            'replies' => $this->whenLoaded('replies', fn () =>
                SupportTicketReplyResource::collection($this->replies->sortBy('created_at')->values())
            ),
        ];
    }
}
