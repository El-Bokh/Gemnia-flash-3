<?php

namespace App\Jobs;

use App\Models\AiRequest;
use App\Models\ConversationMessage;
use App\Models\MediaFile;
use App\Services\GeminiService;
use App\Services\UsageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PollVeoVideoGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public int $aiRequestId,
        public int $messageId,
    ) {}

    public function handle(): void
    {
        $aiRequest = AiRequest::find($this->aiRequestId);
        $message = ConversationMessage::find($this->messageId);

        if (! $aiRequest || ! $message) {
            return;
        }

        if (! in_array($aiRequest->status, ['pending', 'processing'], true)) {
            return;
        }

        $metadata = $aiRequest->metadata ?? [];
        $operationName = $metadata['vertex_operation_name'] ?? null;

        if (! is_string($operationName) || $operationName === '') {
            $this->failRequest($aiRequest, $message, 'Video generation operation name is missing.', 'VEO_OPERATION_MISSING');
            return;
        }

        $videoOptions = $metadata['video_options'] ?? [];
        $gemini = new GeminiService('video', $videoOptions['aspect_ratio'] ?? null);
        $result = $gemini->fetchVideoOperation($operationName);

        if (! ($result['success'] ?? false)) {
            $this->failRequest(
                $aiRequest,
                $message,
                $result['error'] ?? 'Video generation status check failed.',
                'VEO_POLL_FAILED',
                $result['response_payload'] ?? null,
            );
            return;
        }

        $pollCount = (int) ($metadata['video_poll_count'] ?? 0) + 1;
        $metadata['video_poll_count'] = $pollCount;
        $metadata['video_last_polled_at'] = now()->toIso8601String();

        if (! ($result['done'] ?? false)) {
            $maxPolls = max(1, (int) config('services.vertex_ai.video_max_polls', 80));
            if ($pollCount >= $maxPolls) {
                $aiRequest->update(['metadata' => $metadata]);
                $this->failRequest($aiRequest, $message, 'Video generation timed out before Vertex AI completed the operation.', 'VEO_TIMEOUT');
                return;
            }

            $aiRequest->update(['metadata' => $metadata]);

            self::dispatch($aiRequest->id, $message->id)
                ->delay(now()->addSeconds((int) config('services.vertex_ai.video_poll_interval_seconds', 15)));
            return;
        }

        $operation = $result['operation'] ?? [];
        $parsed = $gemini->parseVideoOperationResult($operation);

        if (! ($parsed['success'] ?? false)) {
            $this->failRequest(
                $aiRequest,
                $message,
                $parsed['error'] ?? 'Veo completed without a usable video.',
                (string) ($parsed['error_code'] ?? 'VEO_EMPTY_RESULT'),
                ['operation' => $this->redactedOperation($operation)],
            );
            return;
        }

        $stored = $this->storeOrResolveVideo($aiRequest, $parsed);

        if ($stored === null) {
            $this->failRequest($aiRequest, $message, 'Generated video could not be stored.', 'VEO_STORE_FAILED', ['operation' => $this->redactedOperation($operation)]);
            return;
        }

        $completedAt = now();
        $metadata = array_merge($metadata, [
            'video_status' => 'completed',
            'video_completed_at' => $completedAt->toIso8601String(),
            'gcs_uris' => $parsed['gcs_uris'] ?? [],
        ]);

        $aiRequest->update([
            'status' => 'completed',
            'output_video_path' => $stored['url'],
            'response_payload' => [
                'operation' => $this->redactedOperation($operation),
                'artifacts' => $this->summarizeArtifacts($parsed),
            ],
            'metadata' => $metadata,
            'completed_at' => $completedAt,
            'processing_time_ms' => $this->processingTimeMs($aiRequest, $completedAt),
        ]);

        $message->update([
            'content' => '',
            'video_url' => $stored['url'],
            'status' => 'sent',
            'metadata' => array_merge($message->metadata ?? [], [
                'gcs_uri' => $stored['gcs_uri'] ?? null,
                'file_size' => $stored['file_size'] ?? null,
            ]),
        ]);
    }

    private function storeOrResolveVideo(AiRequest $aiRequest, array $parsed): ?array
    {
        $inlineVideos = $parsed['inline_videos'] ?? [];
        if (! empty($inlineVideos)) {
            $video = $inlineVideos[0];
            $binary = base64_decode((string) ($video['data'] ?? ''), true);

            if ($binary === false || $binary === '') {
                return null;
            }

            $mimeType = (string) ($video['mime_type'] ?? 'video/mp4');
            $extension = $this->extensionForMimeType($mimeType);
            $fileName = 'ai-generated-videos/' . $aiRequest->user_id . '/' . Str::uuid() . '.' . $extension;

            Storage::disk('public')->put($fileName, $binary);

            MediaFile::create([
                'user_id' => $aiRequest->user_id,
                'file_name' => basename($fileName),
                'original_name' => 'generated-video.' . $extension,
                'file_path' => $fileName,
                'disk' => 'public',
                'mime_type' => $mimeType,
                'file_size' => strlen($binary),
                'collection' => 'chat',
                'purpose' => 'output',
                'metadata' => [
                    'ai_request_id' => $aiRequest->id,
                    'model' => $aiRequest->model_used,
                ],
            ]);

            return [
                'url' => '/storage/' . $fileName,
                'file_size' => strlen($binary),
            ];
        }

        $gcsUris = $parsed['gcs_uris'] ?? [];
        if (! empty($gcsUris) && is_string($gcsUris[0])) {
            return [
                'url' => $this->gcsUriToHttpsUrl($gcsUris[0]),
                'gcs_uri' => $gcsUris[0],
            ];
        }

        return null;
    }

    private function extensionForMimeType(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'video/quicktime', 'video/mov' => 'mov',
            'video/mpeg', 'video/mpg' => 'mpg',
            default => 'mp4',
        };
    }

    private function gcsUriToHttpsUrl(string $gcsUri): string
    {
        if (! str_starts_with($gcsUri, 'gs://')) {
            return $gcsUri;
        }

        $path = substr($gcsUri, 5);
        [$bucket, $object] = array_pad(explode('/', $path, 2), 2, '');

        return 'https://storage.googleapis.com/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($object));
    }

    private function redactedOperation(array $operation): array
    {
        return $this->redactedPayload($operation) ?? [];
    }

    private function redactedPayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return $this->redactLargePayloadValue($payload);
    }

    private function redactLargePayloadValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $childKey => $childValue) {
                $redacted[$childKey] = $this->redactLargePayloadValue($childValue, is_string($childKey) ? $childKey : null);
            }

            return $redacted;
        }

        if (! is_string($value)) {
            return $value;
        }

        $normalizedKey = strtolower((string) $key);
        if (in_array($normalizedKey, ['bytesbase64encoded', 'data'], true) && strlen($value) > 1024) {
            return [
                'redacted' => true,
                'type' => 'base64',
                'length' => strlen($value),
            ];
        }

        if (strlen($value) > 8192) {
            return [
                'redacted' => true,
                'type' => 'string',
                'length' => strlen($value),
                'preview' => mb_substr($value, 0, 500),
            ];
        }

        return $value;
    }

    private function summarizeArtifacts(array $parsed): array
    {
        $inlineVideos = [];

        foreach (($parsed['inline_videos'] ?? []) as $video) {
            $data = $video['data'] ?? null;
            $inlineVideos[] = [
                'mime_type' => $video['mime_type'] ?? null,
                'base64_length' => is_string($data) ? strlen($data) : null,
            ];
        }

        return [
            'gcs_uris' => $parsed['gcs_uris'] ?? [],
            'inline_videos' => $inlineVideos,
        ];
    }

    private function processingTimeMs(AiRequest $aiRequest, Carbon $completedAt): ?int
    {
        if (! $aiRequest->started_at) {
            return null;
        }

        return max(0, (int) round($aiRequest->started_at->diffInMilliseconds($completedAt)));
    }

    private function failRequest(
        AiRequest $aiRequest,
        ConversationMessage $message,
        string $error,
        string $code,
        ?array $responsePayload = null,
    ): void {
        $completedAt = now();
        $aiRequest->update([
            'status' => 'failed',
            'error_message' => $error,
            'error_code' => $code,
            'response_payload' => $responsePayload !== null ? $this->redactedPayload($responsePayload) : $aiRequest->response_payload,
            'completed_at' => $completedAt,
            'processing_time_ms' => $this->processingTimeMs($aiRequest, $completedAt),
        ]);

        $message->update([
            'content' => $error,
            'status' => 'error',
        ]);

        if ($aiRequest->credits_consumed > 0 && $aiRequest->user) {
            (new UsageService())->refund($aiRequest->user, $aiRequest->credits_consumed, 'ai_failure', [
                'ai_request_id' => $aiRequest->id,
                'error' => $error,
            ]);

            $aiRequest->update(['credits_consumed' => 0]);
        }
    }
}