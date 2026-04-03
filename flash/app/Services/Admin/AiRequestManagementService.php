<?php

namespace App\Services\Admin;

use App\Models\AiRequest;
use App\Models\GeneratedImage;
use App\Models\Notification;
use App\Models\UsageLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AiRequestManagementService
{
    // ──────────────────────────────────────────────
    //  LIST (paginated with filters)
    // ──────────────────────────────────────────────

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = AiRequest::query()
            ->with([
                'user:id,name,email,avatar',
                'visualStyle:id,name,slug,thumbnail',
            ])
            ->withCount('generatedImages');

        if (! empty($filters['with_trashed'])) {
            $query->withTrashed();
        }

        // ── Search (prompt, user name/email) ──
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $q) use ($term) {
                $q->where('user_prompt', 'LIKE', "%{$term}%")
                  ->orWhere('processed_prompt', 'LIKE', "%{$term}%")
                  ->orWhere('uuid', 'LIKE', "%{$term}%")
                  ->orWhere('model_used', 'LIKE', "%{$term}%")
                  ->orWhereHas('user', function (Builder $uq) use ($term) {
                      $uq->where('name', 'LIKE', "%{$term}%")
                         ->orWhere('email', 'LIKE', "%{$term}%");
                  });
            });
        }

        // ── Exact filters ──
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['visual_style_id'])) {
            $query->where('visual_style_id', $filters['visual_style_id']);
        }

        if (! empty($filters['model_used'])) {
            $query->where('model_used', $filters['model_used']);
        }

        if (! empty($filters['engine_provider'])) {
            $query->where('engine_provider', $filters['engine_provider']);
        }

        // ── Date range ──
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        // ── Has generated images ──
        if (isset($filters['has_images'])) {
            if ($filters['has_images']) {
                $query->has('generatedImages');
            } else {
                $query->doesntHave('generatedImages');
            }
        }

        // ── Credits range ──
        if (isset($filters['min_credits'])) {
            $query->where('credits_consumed', '>=', (int) $filters['min_credits']);
        }

        if (isset($filters['max_credits'])) {
            $query->where('credits_consumed', '<=', (int) $filters['max_credits']);
        }

        // ── Sorting ──
        $sortBy  = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage);
    }

    // ──────────────────────────────────────────────
    //  SHOW DETAIL
    // ──────────────────────────────────────────────

    public function getDetail(int $aiRequestId): AiRequest
    {
        return AiRequest::withTrashed()
            ->with([
                'user:id,name,email,avatar,status',
                'subscription' => fn ($q) => $q->with('plan:id,name,slug'),
                'visualStyle:id,name,slug,thumbnail,category,prompt_prefix,prompt_suffix',
                'generatedImages' => fn ($q) => $q->orderByDesc('created_at'),
                'usageLogs' => fn ($q) => $q->with('feature:id,name,slug')->orderByDesc('created_at'),
            ])
            ->findOrFail($aiRequestId);
    }

    // ──────────────────────────────────────────────
    //  UPDATE STATUS / DETAILS
    // ──────────────────────────────────────────────

    public function update(int $aiRequestId, array $data): AiRequest
    {
        $aiRequest = AiRequest::findOrFail($aiRequestId);

        $fillable = [
            'status', 'processed_prompt', 'negative_prompt',
            'model_used', 'engine_provider', 'error_message',
            'error_code', 'metadata',
        ];

        $updateData = array_intersect_key($data, array_flip($fillable));

        // Auto-set timestamps based on status changes
        if (isset($updateData['status'])) {
            $newStatus = $updateData['status'];
            $oldStatus = $aiRequest->status;

            if ($newStatus !== $oldStatus) {
                // Log the status change
                $this->logStatusChange($aiRequest, $oldStatus, $newStatus);

                if ($newStatus === 'processing' && ! $aiRequest->started_at) {
                    $updateData['started_at'] = now();
                }

                if (in_array($newStatus, ['completed', 'failed', 'cancelled', 'timeout'])) {
                    $updateData['completed_at'] = now();

                    if ($aiRequest->started_at) {
                        $updateData['processing_time_ms'] = (int) now()->diffInMilliseconds($aiRequest->started_at);
                    }
                }
            }
        }

        $aiRequest->update($updateData);

        $aiRequest->load([
            'user:id,name,email,avatar',
            'visualStyle:id,name,slug,thumbnail',
            'generatedImages',
        ]);

        return $aiRequest;
    }

    // ──────────────────────────────────────────────
    //  RETRY FAILED REQUEST
    // ──────────────────────────────────────────────

    public function retry(int $aiRequestId): AiRequest
    {
        $aiRequest = AiRequest::findOrFail($aiRequestId);

        if (! in_array($aiRequest->status, ['failed', 'timeout', 'cancelled'])) {
            abort(422, "Only failed, timed-out, or cancelled requests can be retried. Current status: \"{$aiRequest->status}\".");
        }

        return DB::transaction(function () use ($aiRequest) {
            $aiRequest->update([
                'status'          => 'pending',
                'retry_count'     => $aiRequest->retry_count + 1,
                'error_message'   => null,
                'error_code'      => null,
                'started_at'      => null,
                'completed_at'    => null,
                'processing_time_ms' => null,
            ]);

            // Log the retry
            $this->logStatusChange($aiRequest, 'retry_initiated', 'pending', [
                'retry_number' => $aiRequest->retry_count,
                'previous_error' => $aiRequest->getOriginal('error_message'),
            ]);

            // Notify user
            $this->createNotification(
                $aiRequest->user_id,
                'ai_request_retry',
                'طلبك قيد إعادة المعالجة',
                "تتم إعادة معالجة طلبك \"{$this->truncatePrompt($aiRequest->user_prompt)}\" (المحاولة #{$aiRequest->retry_count}).",
                [
                    'ai_request_id'   => $aiRequest->id,
                    'ai_request_uuid' => $aiRequest->uuid,
                    'retry_count'     => $aiRequest->retry_count,
                ]
            );

            $aiRequest->load([
                'user:id,name,email,avatar',
                'visualStyle:id,name,slug,thumbnail',
                'generatedImages',
            ]);

            return $aiRequest;
        });
    }

    // ──────────────────────────────────────────────
    //  BULK RETRY
    // ──────────────────────────────────────────────

    public function bulkRetry(array $requestIds): array
    {
        $requests = AiRequest::whereIn('id', $requestIds)->get();

        $results = [
            'retried'  => [],
            'skipped'  => [],
            'total'    => count($requestIds),
        ];

        DB::transaction(function () use ($requests, &$results) {
            foreach ($requests as $aiRequest) {
                if (in_array($aiRequest->status, ['failed', 'timeout', 'cancelled'])) {
                    $aiRequest->update([
                        'status'          => 'pending',
                        'retry_count'     => $aiRequest->retry_count + 1,
                        'error_message'   => null,
                        'error_code'      => null,
                        'started_at'      => null,
                        'completed_at'    => null,
                        'processing_time_ms' => null,
                    ]);

                    $this->logStatusChange($aiRequest, 'bulk_retry', 'pending', [
                        'retry_number' => $aiRequest->retry_count,
                    ]);

                    $this->createNotification(
                        $aiRequest->user_id,
                        'ai_request_retry',
                        'طلبك قيد إعادة المعالجة',
                        "تتم إعادة معالجة طلبك (المحاولة #{$aiRequest->retry_count}).",
                        ['ai_request_id' => $aiRequest->id]
                    );

                    $results['retried'][] = $aiRequest->id;
                } else {
                    $results['skipped'][] = [
                        'id'     => $aiRequest->id,
                        'status' => $aiRequest->status,
                        'reason' => "Cannot retry: current status is \"{$aiRequest->status}\".",
                    ];
                }
            }
        });

        $results['retried_count'] = count($results['retried']);
        $results['skipped_count'] = count($results['skipped']);

        return $results;
    }

    // ──────────────────────────────────────────────
    //  BULK DELETE (soft-delete)
    // ──────────────────────────────────────────────

    public function bulkDelete(array $requestIds): array
    {
        $requests = AiRequest::whereIn('id', $requestIds)->get();

        $results = [
            'deleted'  => [],
            'skipped'  => [],
            'total'    => count($requestIds),
        ];

        DB::transaction(function () use ($requests, &$results) {
            foreach ($requests as $aiRequest) {
                // Don't delete processing requests
                if ($aiRequest->status === 'processing') {
                    $results['skipped'][] = [
                        'id'     => $aiRequest->id,
                        'status' => $aiRequest->status,
                        'reason' => 'Cannot delete a request that is currently being processed.',
                    ];
                    continue;
                }

                $aiRequest->delete();
                $results['deleted'][] = $aiRequest->id;
            }
        });

        $results['deleted_count'] = count($results['deleted']);
        $results['skipped_count'] = count($results['skipped']);

        return $results;
    }

    // ──────────────────────────────────────────────
    //  DELETE (soft-delete single)
    // ──────────────────────────────────────────────

    public function delete(int $aiRequestId): void
    {
        $aiRequest = AiRequest::findOrFail($aiRequestId);

        if ($aiRequest->status === 'processing') {
            abort(422, 'Cannot delete a request that is currently being processed. Cancel it first.');
        }

        $aiRequest->delete();
    }

    // ──────────────────────────────────────────────
    //  FORCE DELETE (permanent)
    // ──────────────────────────────────────────────

    public function forceDelete(int $aiRequestId): void
    {
        $aiRequest = AiRequest::withTrashed()->findOrFail($aiRequestId);

        if ($aiRequest->status === 'processing') {
            abort(422, 'Cannot permanently delete a request that is being processed.');
        }

        DB::transaction(function () use ($aiRequest) {
            // Delete related generated images
            $aiRequest->generatedImages()->forceDelete();
            // Delete related usage logs
            $aiRequest->usageLogs()->delete();
            // Delete the request itself
            $aiRequest->forceDelete();
        });
    }

    // ──────────────────────────────────────────────
    //  RESTORE
    // ──────────────────────────────────────────────

    public function restore(int $aiRequestId): AiRequest
    {
        $aiRequest = AiRequest::onlyTrashed()->findOrFail($aiRequestId);
        $aiRequest->restore();

        // Also restore soft-deleted generated images
        GeneratedImage::onlyTrashed()
            ->where('ai_request_id', $aiRequest->id)
            ->restore();

        $aiRequest->load([
            'user:id,name,email,avatar',
            'visualStyle:id,name,slug,thumbnail',
            'generatedImages',
        ]);

        return $aiRequest;
    }

    // ──────────────────────────────────────────────
    //  CANCEL PROCESSING REQUEST
    // ──────────────────────────────────────────────

    public function cancel(int $aiRequestId): AiRequest
    {
        $aiRequest = AiRequest::findOrFail($aiRequestId);

        if (! in_array($aiRequest->status, ['pending', 'processing'])) {
            abort(422, "Only pending or processing requests can be cancelled. Current status: \"{$aiRequest->status}\".");
        }

        $oldStatus = $aiRequest->status;

        $aiRequest->update([
            'status'       => 'cancelled',
            'completed_at' => now(),
        ]);

        $this->logStatusChange($aiRequest, $oldStatus, 'cancelled');

        // Notify user of cancellation
        $this->createNotification(
            $aiRequest->user_id,
            'ai_request_cancelled',
            'تم إلغاء طلبك',
            "تم إلغاء طلبك \"{$this->truncatePrompt($aiRequest->user_prompt)}\" بواسطة مدير النظام.",
            [
                'ai_request_id'   => $aiRequest->id,
                'ai_request_uuid' => $aiRequest->uuid,
            ]
        );

        $aiRequest->load([
            'user:id,name,email,avatar',
            'visualStyle:id,name,slug,thumbnail',
        ]);

        return $aiRequest;
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS / STATISTICS
    // ──────────────────────────────────────────────

    public function getAggregations(array $filters = []): array
    {
        $baseQuery = AiRequest::query();

        // Apply same date filters if provided
        if (! empty($filters['date_from'])) {
            $baseQuery->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $baseQuery->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        // ── Status breakdown ──
        $statusCounts = (clone $baseQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // ── Type breakdown ──
        $typeCounts = (clone $baseQuery)
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        // ── Engine/Model breakdown ──
        $engineCounts = (clone $baseQuery)
            ->select('engine_provider', DB::raw('COUNT(*) as count'))
            ->whereNotNull('engine_provider')
            ->groupBy('engine_provider')
            ->pluck('count', 'engine_provider')
            ->toArray();

        $modelCounts = (clone $baseQuery)
            ->select('model_used', DB::raw('COUNT(*) as count'))
            ->whereNotNull('model_used')
            ->groupBy('model_used')
            ->pluck('count', 'model_used')
            ->toArray();

        // ── General stats ──
        $stats = (clone $baseQuery)
            ->select([
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('SUM(credits_consumed) as total_credits_consumed'),
                DB::raw('AVG(processing_time_ms) as avg_processing_time_ms'),
                DB::raw('MAX(processing_time_ms) as max_processing_time_ms'),
                DB::raw('MIN(processing_time_ms) as min_processing_time_ms'),
                DB::raw('AVG(credits_consumed) as avg_credits_per_request'),
                DB::raw('SUM(num_images) as total_images_requested'),
                DB::raw('AVG(retry_count) as avg_retry_count'),
                DB::raw('SUM(CASE WHEN retry_count > 0 THEN 1 ELSE 0 END) as requests_with_retries'),
            ])
            ->first();

        // ── Daily requests (last 30 days) ──
        $dailyRequests = (clone $baseQuery)
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw('SUM(credits_consumed) as credits'),
            ])
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // ── Top users by request count ──
        $topUsers = AiRequest::query()
            ->select([
                'user_id',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(credits_consumed) as total_credits'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count"),
            ])
            ->groupBy('user_id')
            ->orderByDesc('request_count')
            ->limit(10)
            ->with('user:id,name,email,avatar')
            ->get()
            ->map(fn ($row) => [
                'user' => $row->user ? [
                    'id'     => $row->user->id,
                    'name'   => $row->user->name,
                    'email'  => $row->user->email,
                    'avatar' => $row->user->avatar,
                ] : null,
                'request_count'   => $row->request_count,
                'total_credits'   => $row->total_credits,
                'completed_count' => $row->completed_count,
            ]);

        // ── Top visual styles ──
        $topStyles = AiRequest::query()
            ->select([
                'visual_style_id',
                DB::raw('COUNT(*) as usage_count'),
            ])
            ->whereNotNull('visual_style_id')
            ->groupBy('visual_style_id')
            ->orderByDesc('usage_count')
            ->limit(10)
            ->with('visualStyle:id,name,slug,thumbnail')
            ->get()
            ->map(fn ($row) => [
                'style' => $row->visualStyle ? [
                    'id'        => $row->visualStyle->id,
                    'name'      => $row->visualStyle->name,
                    'slug'      => $row->visualStyle->slug,
                    'thumbnail' => $row->visualStyle->thumbnail,
                ] : null,
                'usage_count' => $row->usage_count,
            ]);

        // ── Error analysis ──
        $errorCodes = AiRequest::query()
            ->select([
                'error_code',
                DB::raw('COUNT(*) as count'),
            ])
            ->where('status', 'failed')
            ->whereNotNull('error_code')
            ->groupBy('error_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // ── Success/Failure rates ──
        $total = $stats->total_requests ?? 0;
        $completed = $statusCounts['completed'] ?? 0;
        $failed = $statusCounts['failed'] ?? 0;

        return [
            'overview' => [
                'total_requests'         => $total,
                'total_credits_consumed' => (int) ($stats->total_credits_consumed ?? 0),
                'total_images_requested' => (int) ($stats->total_images_requested ?? 0),
                'avg_processing_time_ms' => round($stats->avg_processing_time_ms ?? 0),
                'max_processing_time_ms' => (int) ($stats->max_processing_time_ms ?? 0),
                'min_processing_time_ms' => (int) ($stats->min_processing_time_ms ?? 0),
                'avg_credits_per_request'=> round($stats->avg_credits_per_request ?? 0, 2),
                'avg_retry_count'        => round($stats->avg_retry_count ?? 0, 2),
                'requests_with_retries'  => (int) ($stats->requests_with_retries ?? 0),
                'success_rate'           => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
                'failure_rate'           => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
            ],
            'by_status'          => $statusCounts,
            'by_type'            => $typeCounts,
            'by_engine'          => $engineCounts,
            'by_model'           => $modelCounts,
            'daily_trend'        => $dailyRequests,
            'top_users'          => $topUsers,
            'top_visual_styles'  => $topStyles,
            'error_codes'        => $errorCodes,
        ];
    }

    // ──────────────────────────────────────────────
    //  NOTIFY USER (completion / failure)
    // ──────────────────────────────────────────────

    public function notifyUser(int $aiRequestId, string $type): Notification
    {
        $aiRequest = AiRequest::with(['user:id,name,email', 'generatedImages'])
            ->findOrFail($aiRequestId);

        $promptPreview = $this->truncatePrompt($aiRequest->user_prompt);

        $notificationData = match ($type) {
            'completed' => [
                'type'  => 'ai_request_completed',
                'title' => 'تم إنشاء صورتك بنجاح!',
                'body'  => "اكتملت معالجة طلبك \"{$promptPreview}\" بنجاح. تم إنشاء {$aiRequest->generatedImages->count()} صورة(صور).",
                'icon'  => 'check-circle',
            ],
            'failed' => [
                'type'  => 'ai_request_failed',
                'title' => 'فشل إنشاء الصورة',
                'body'  => "عذراً، فشلت معالجة طلبك \"{$promptPreview}\". " . ($aiRequest->error_message ? "السبب: {$aiRequest->error_message}" : 'يرجى المحاولة مرة أخرى.'),
                'icon'  => 'x-circle',
            ],
            default => abort(422, "Invalid notification type: \"{$type}\". Use 'completed' or 'failed'."),
        };

        return $this->createNotification(
            $aiRequest->user_id,
            $notificationData['type'],
            $notificationData['title'],
            $notificationData['body'],
            [
                'ai_request_id'        => $aiRequest->id,
                'ai_request_uuid'      => $aiRequest->uuid,
                'generated_images_count'=> $aiRequest->generatedImages->count(),
                'first_image_path'     => $aiRequest->generatedImages->first()?->file_path,
            ],
            $notificationData['icon']
        );
    }

    // ══════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ══════════════════════════════════════════════

    private function logStatusChange(AiRequest $aiRequest, string $fromStatus, string $toStatus, array $extra = []): void
    {
        UsageLog::create([
            'user_id'         => $aiRequest->user_id,
            'subscription_id' => $aiRequest->subscription_id,
            'ai_request_id'   => $aiRequest->id,
            'action'          => "status_change:{$fromStatus}:{$toStatus}",
            'credits_used'    => 0,
            'ip_address'      => request()?->ip(),
            'user_agent'      => request()?->userAgent(),
            'metadata'        => array_merge([
                'from_status' => $fromStatus,
                'to_status'   => $toStatus,
                'changed_by'  => 'admin',
                'admin_id'    => auth()->id(),
                'timestamp'   => now()->toIso8601String(),
            ], $extra),
        ]);
    }

    private function createNotification(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $icon = null
    ): Notification {
        return Notification::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'icon'       => $icon,
            'action_url' => null,
            'channel'    => 'in_app',
            'priority'   => 'normal',
            'is_read'    => false,
            'sent_at'    => now(),
            'data'       => $data,
        ]);
    }

    private function truncatePrompt(string $prompt, int $length = 50): string
    {
        return mb_strlen($prompt) > $length
            ? mb_substr($prompt, 0, $length) . '...'
            : $prompt;
    }
}
