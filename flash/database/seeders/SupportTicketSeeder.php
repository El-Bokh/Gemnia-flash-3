<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupportTicketSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::limit(5)->get();
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Skipping SupportTicketSeeder.');
            return;
        }

        // Use the first user (admin) as the agent for assignments
        $admin = $users->first();
        $regularUsers = $users->count() > 1 ? $users->slice(1) : $users;

        $ticketsData = [
            // ── 1. Open / Unassigned / High ──
            [
                'user'     => $regularUsers->random(),
                'subject'  => 'Cannot access AI image generation',
                'message'  => 'I have been trying to generate images using the AI tool for the last 2 hours but I keep getting a "Service Unavailable" error. My subscription is active and I still have credits remaining. This is urgent as I need these images for a project deadline.',
                'status'   => 'open',
                'priority' => 'high',
                'category' => 'technical',
                'assigned_to' => null,
                'created_at'  => now()->subDays(1)->subHours(3),
                'replies' => [
                    // No replies yet – fresh ticket
                ],
            ],

            // ── 2. In Progress / Assigned / Medium ──
            [
                'user'     => $regularUsers->random(),
                'subject'  => 'Billing discrepancy on last invoice',
                'message'  => 'Hello, I was charged $49.99 on my last invoice but my plan should be $29.99/month. I did not upgrade my plan. Could you please look into this and issue a correction?',
                'status'   => 'in_progress',
                'priority' => 'medium',
                'category' => 'billing',
                'assigned_to' => $admin->id,
                'created_at'  => now()->subDays(3),
                'replies' => [
                    [
                        'user_id'        => $admin->id,
                        'is_staff_reply' => true,
                        'message'        => 'Thank you for reaching out. I can see the charge on your account. Let me investigate this with the billing team and get back to you shortly.',
                        'created_at'     => now()->subDays(3)->addHours(2),
                    ],
                    [
                        'user_id'        => null, // will be set to ticket owner
                        'is_staff_reply' => false,
                        'message'        => 'Thank you for the quick response. I appreciate you looking into this.',
                        'created_at'     => now()->subDays(2)->addHours(5),
                    ],
                ],
            ],

            // ── 3. Waiting Reply / Assigned / Low ──
            [
                'user'     => $regularUsers->random(),
                'subject'  => 'How to export my generated content?',
                'message'  => 'Is there a way to bulk export all the content I have generated through the AI writer? I would like to have a backup of everything.',
                'status'   => 'waiting_reply',
                'priority' => 'low',
                'category' => 'general',
                'assigned_to' => $admin->id,
                'created_at'  => now()->subDays(5),
                'replies' => [
                    [
                        'user_id'        => $admin->id,
                        'is_staff_reply' => true,
                        'message'        => 'Great question! Currently you can export individual items from the content library. We are working on a bulk export feature. In the meantime, would you like me to prepare a manual export of your data?',
                        'created_at'     => now()->subDays(4)->addHours(6),
                    ],
                ],
            ],

            // ── 4. Resolved / Assigned / Urgent ──
            [
                'user'     => $regularUsers->random(),
                'subject'  => 'Account locked after multiple login attempts',
                'message'  => 'My account got locked after I forgot my password and tried several times. I cannot login or reset the password. Please unlock my account immediately.',
                'status'   => 'resolved',
                'priority' => 'urgent',
                'category' => 'account',
                'assigned_to' => $admin->id,
                'resolved_at' => now()->subDays(1),
                'created_at'  => now()->subDays(2),
                'replies' => [
                    [
                        'user_id'        => $admin->id,
                        'is_staff_reply' => true,
                        'message'        => 'I have unlocked your account and sent you a password reset link to your email. Please check your inbox (and spam folder). Let me know if you need further assistance.',
                        'created_at'     => now()->subDays(2)->addMinutes(45),
                    ],
                    [
                        'user_id'        => null,
                        'is_staff_reply' => false,
                        'message'        => 'That worked perfectly! I can login again now. Thank you so much for the quick help!',
                        'created_at'     => now()->subDays(1)->addHours(1),
                    ],
                ],
            ],

            // ── 5. Closed / Assigned / Medium ──
            [
                'user'     => $regularUsers->random(),
                'subject'  => 'Request to cancel my subscription',
                'message'  => 'I would like to cancel my Pro subscription. I found the basic tier is enough for my current needs. Please process the cancellation and confirm.',
                'status'   => 'closed',
                'priority' => 'medium',
                'category' => 'billing',
                'assigned_to' => $admin->id,
                'closed_at'   => now()->subDays(5),
                'resolved_at' => now()->subDays(5),
                'created_at'  => now()->subDays(8),
                'replies' => [
                    [
                        'user_id'        => $admin->id,
                        'is_staff_reply' => true,
                        'message'        => 'I understand. Before I process the cancellation, I want to let you know about our new flexible plan that costs less. Would you be interested?',
                        'created_at'     => now()->subDays(7)->addHours(3),
                    ],
                    [
                        'user_id'        => null,
                        'is_staff_reply' => false,
                        'message'        => 'No thank you, I would still like to cancel please.',
                        'created_at'     => now()->subDays(7)->addHours(8),
                    ],
                    [
                        'user_id'        => $admin->id,
                        'is_staff_reply' => true,
                        'message'        => 'Done! Your subscription has been cancelled. You will retain access until the end of your current billing period. If you ever want to come back, we will be here!',
                        'created_at'     => now()->subDays(6),
                    ],
                    [
                        'user_id'        => null,
                        'is_staff_reply' => false,
                        'message'        => 'Thank you for handling this. Appreciate the good service!',
                        'created_at'     => now()->subDays(5)->addHours(2),
                    ],
                ],
            ],

            // ── 6. Open / Unassigned / Urgent ──
            [
                'user'     => $regularUsers->random(),
                'subject'  => 'Data loss — generated images disappeared',
                'message'  => 'All of my generated images from the past week seem to have disappeared from my gallery. I had about 50 images that I need for client work. This is very urgent. Please help recover them.',
                'status'   => 'open',
                'priority' => 'urgent',
                'category' => 'technical',
                'assigned_to' => null,
                'created_at'  => now()->subHours(4),
                'replies' => [],
            ],

            // ── 7. In Progress / Assigned / High ──
            [
                'user'     => $regularUsers->random(),
                'subject'  => 'API rate limiting is too aggressive',
                'message'  => 'We are integrating your API into our workflow but hitting rate limits after only 10 requests per minute. Our Enterprise plan documentation says we should get 100 req/min. Can this be fixed?',
                'status'   => 'in_progress',
                'priority' => 'high',
                'category' => 'technical',
                'assigned_to' => $admin->id,
                'created_at'  => now()->subDays(2),
                'replies' => [
                    [
                        'user_id'        => $admin->id,
                        'is_staff_reply' => true,
                        'message'        => 'You are right, there appears to be a misconfiguration on your API key. I have escalated this to our infrastructure team. They will increase your rate limit to match your plan within the next few hours.',
                        'created_at'     => now()->subDays(2)->addHours(1),
                    ],
                    [
                        'user_id'        => null,
                        'is_staff_reply' => false,
                        'message'        => 'Thanks for the fast response. Could you also provide the correct rate limit headers so we can implement proper retry logic on our side?',
                        'created_at'     => now()->subDays(1)->addHours(3),
                    ],
                    [
                        'user_id'        => $admin->id,
                        'is_staff_reply' => true,
                        'message'        => 'Of course! We return X-RateLimit-Limit, X-RateLimit-Remaining, and X-RateLimit-Reset headers on every response. I will also send you our API best practices document via email.',
                        'created_at'     => now()->subDays(1)->addHours(6),
                    ],
                ],
            ],

            // ── 8. Open / Unassigned / Low ──
            [
                'user'     => $regularUsers->random(),
                'subject'  => 'Feature request: dark mode for dashboard',
                'message'  => 'Would it be possible to add a dark mode option to the admin dashboard? Working late at night can be straining on the eyes with the current bright theme.',
                'status'   => 'open',
                'priority' => 'low',
                'category' => 'feature_request',
                'assigned_to' => null,
                'created_at'  => now()->subDays(6),
                'replies' => [],
            ],

            // ── 9. Closed / Resolved / Low ──
            [
                'user'     => $regularUsers->random(),
                'subject'  => 'Typo in AI-generated email template',
                'message'  => 'The default email template that the AI generates has a typo in the footer. It says "Regards, [Yoru Company]" instead of "Your Company". Minor issue but thought you should know.',
                'status'   => 'closed',
                'priority' => 'low',
                'category' => 'bug_report',
                'assigned_to' => $admin->id,
                'closed_at'   => now()->subDays(3),
                'resolved_at' => now()->subDays(3),
                'created_at'  => now()->subDays(4),
                'replies' => [
                    [
                        'user_id'        => $admin->id,
                        'is_staff_reply' => true,
                        'message'        => 'Good catch! We have fixed the typo in the template. Thank you for reporting this. The fix will be live within the hour.',
                        'created_at'     => now()->subDays(4)->addHours(5),
                    ],
                    [
                        'user_id'        => null,
                        'is_staff_reply' => false,
                        'message'        => 'Confirmed — looks correct now. Thanks!',
                        'created_at'     => now()->subDays(3)->addHours(1),
                    ],
                ],
            ],

            // ── 10. In Progress / Assigned / Medium ──
            [
                'user'     => $regularUsers->random(),
                'subject'  => 'Cannot download PDF invoices',
                'message'  => 'When I click the Download PDF button on my invoices page, nothing happens. I have tried different browsers (Chrome, Firefox, Edge) and the issue persists. I need to download my invoices for accounting purposes.',
                'status'   => 'in_progress',
                'priority' => 'medium',
                'category' => 'technical',
                'assigned_to' => $admin->id,
                'created_at'  => now()->subDays(1)->subHours(6),
                'replies' => [
                    [
                        'user_id'        => $admin->id,
                        'is_staff_reply' => true,
                        'message'        => 'Thank you for reporting this. We have identified the issue — it appears our PDF generation service had a configuration error after the latest deployment. Our team is deploying a fix now.',
                        'created_at'     => now()->subDays(1)->subHours(4),
                    ],
                ],
            ],
        ];

        $ticketNumber = 1000;

        foreach ($ticketsData as $data) {
            $ticketNumber++;
            $user = $data['user'];

            $ticket = SupportTicket::create([
                'ticket_number' => 'TKT-' . $ticketNumber,
                'user_id'       => $user->id,
                'assigned_to'   => $data['assigned_to'],
                'subject'       => $data['subject'],
                'message'       => $data['message'],
                'status'        => $data['status'],
                'priority'      => $data['priority'],
                'category'      => $data['category'],
                'attachments'   => [],
                'resolved_at'   => $data['resolved_at'] ?? null,
                'closed_at'     => $data['closed_at'] ?? null,
                'metadata'      => [],
                'created_at'    => $data['created_at'],
                'updated_at'    => $data['created_at'],
            ]);

            $lastReplyAt = null;

            foreach ($data['replies'] as $replyData) {
                $replyUserId = $replyData['user_id'] ?? $user->id;

                SupportTicketReply::create([
                    'ticket_id'      => $ticket->id,
                    'user_id'        => $replyUserId,
                    'message'        => $replyData['message'],
                    'is_staff_reply' => $replyData['is_staff_reply'],
                    'attachments'    => [],
                    'created_at'     => $replyData['created_at'],
                    'updated_at'     => $replyData['created_at'],
                ]);

                $lastReplyAt = $replyData['created_at'];
            }

            if ($lastReplyAt) {
                $ticket->update(['last_reply_at' => $lastReplyAt]);
            }
        }

        $this->command->info('SupportTicketSeeder: 10 tickets with conversation threads seeded.');
    }
}
