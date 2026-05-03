<?php

namespace App\Jobs;

use App\Models\AiRequest;
use App\Models\ConversationMessage;
use App\Services\GeminiService;
use App\Services\UsageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StartVeoVideoGeneration implements ShouldQueue
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
        $videoOptions = $metadata['video_options'] ?? [];
        $referenceImage = $this->loadReferenceImage($aiRequest->input_image_path);
        $prompt = trim((string) ($aiRequest->processed_prompt ?: $aiRequest->user_prompt));
        $usedReferenceImage = $referenceImage !== null;
        $referenceStartFailure = null;

        $gemini = new GeminiService('video', $videoOptions['aspect_ratio'] ?? null);
        $result = $gemini->startVideoGeneration($prompt, $videoOptions, $referenceImage);

        if (! ($result['success'] ?? false) && $referenceImage !== null && ($result['retry_without_reference'] ?? false)) {
            $referenceStartFailure = $result;
            $prompt = $this->buildPromptOnlyReferenceFallback($prompt);
            $referenceImage = null;
            $usedReferenceImage = false;

            Log::warning('Retrying Veo start without inline reference image after Google 417 block', [
                'ai_request_id' => $aiRequest->id,
                'message_id' => $message->id,
                'status_code' => $result['status_code'] ?? null,
                'input_image_path' => $aiRequest->input_image_path,
            ]);

            $result = $gemini->startVideoGeneration($prompt, $videoOptions, null);
        }

        if (! ($result['success'] ?? false)) {
            $responsePayload = $result['response_payload'] ?? null;
            if ($referenceStartFailure !== null) {
                $responsePayload = [
                    'reference_attempt' => $referenceStartFailure['response_payload'] ?? null,
                    'fallback_attempt' => $responsePayload,
                ];
            }

            $this->failRequest(
                $aiRequest,
                $message,
                $result['error'] ?? 'Video generation could not be started.',
                'VEO_START_FAILED',
                $responsePayload,
            );
            return;
        }

        $metadata = array_merge($metadata, [
            'vertex_operation_name' => $result['operation_name'],
            'video_status' => 'operation_started',
            'video_poll_count' => 0,
            'video_started_at' => now()->toIso8601String(),
            'video_reference_image_used' => $usedReferenceImage,
        ]);

        if ($referenceStartFailure !== null) {
            $metadata['video_reference_fallback'] = [
                'reason' => 'google_417_automated_query_block',
                'original_status_code' => $referenceStartFailure['status_code'] ?? null,
                'retried_without_reference_image' => true,
            ];
        }

        $aiRequest->update([
            'status' => 'processing',
            'processed_prompt' => $prompt !== trim((string) $aiRequest->user_prompt) ? $prompt : $aiRequest->processed_prompt,
            'model_used' => $result['model'] ?? $gemini->getModel(),
            'engine_provider' => 'vertex_ai',
            'request_payload' => $result['request_payload'] ?? $aiRequest->request_payload,
            'response_payload' => ['start' => $result['response_payload'] ?? null],
            'metadata' => $metadata,
            'started_at' => $aiRequest->started_at ?? now(),
        ]);

        $message->update([
            'content' => '',
            'status' => 'processing',
            'metadata' => array_merge($message->metadata ?? [], [
                'vertex_operation_name' => $result['operation_name'],
            ]),
        ]);

        PollVeoVideoGeneration::dispatch($aiRequest->id, $message->id)
            ->delay(now()->addSeconds((int) config('services.vertex_ai.video_poll_interval_seconds', 15)));
    }

    private function buildPromptOnlyReferenceFallback(string $prompt): string
    {
        if (preg_match('/^Requested edit:\s*(.+)$/mi', $prompt, $matches)) {
            $prompt = 'Create a short animated video based on the final edited image. Final scene request: ' . trim($matches[1]);
        }

        $prompt = str_replace(
            'Use the provided reference image as the visual anchor.',
            'Use the described source image and previous prompt as the visual anchor.',
            $prompt,
        );

        $prompt = $this->sanitizePromptOnlyFallbackForVeoPolicy($prompt);

        return trim($prompt . "\nGenerate the video from this textual visual context only. Preserve the same subject, scene, composition, and requested motion as closely as possible.");
    }

    private function sanitizePromptOnlyFallbackForVeoPolicy(string $prompt): string
    {
        $neutralized = preg_replace([
            '/\b(?:young|small)\s+(?:boy|girl)\b/i',
            '/\b(?:child|kid|boy|girl)\b/i',
        ], 'student', $prompt);

        return str_replace(
            ['بنت صغيرة', 'ولد', 'طفل', 'طفلة'],
            ['طالبة', 'طالب', 'طالب', 'طالبة'],
            $neutralized ?? $prompt,
        );
    }

    private function loadReferenceImage(?string $inputImagePath): ?array
    {
        if (! $inputImagePath) {
            return null;
        }

        $relativePath = ltrim(str_replace('/storage/', '', $inputImagePath), '/');
        $fullPath = Storage::disk('public')->path($relativePath);

        if (! is_file($fullPath)) {
            return null;
        }

        $mimeType = strtolower((string) (mime_content_type($fullPath) ?: 'image/jpeg'));
        if ($mimeType === 'image/jpg') {
            $mimeType = 'image/jpeg';
        }

        if (! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            Log::warning('Skipping unsupported Veo reference image mime type', [
                'ai_request_id' => $this->aiRequestId,
                'mime_type' => $mimeType,
            ]);
            return null;
        }

        $bytes = file_get_contents($fullPath);
        if ($bytes === false) {
            return null;
        }

        return [
            'data' => base64_encode($bytes),
            'mime_type' => $mimeType,
        ];
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
            'response_payload' => $responsePayload ?? $aiRequest->response_payload,
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

    private function processingTimeMs(AiRequest $aiRequest, Carbon $completedAt): ?int
    {
        if (! $aiRequest->started_at) {
            return null;
        }

        return max(0, (int) round($aiRequest->started_at->diffInMilliseconds($completedAt)));
    }
}