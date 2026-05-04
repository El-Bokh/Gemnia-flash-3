<?php

namespace App\Services;

use App\Models\Setting;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private const IMAGE_REQUEST_TIMEOUT_SECONDS = 240;
    private const VIDEO_REQUEST_TIMEOUT_SECONDS = 60;
    private const IMAGEN_MASK_EDIT_MODEL = 'imagen-3.0-capability-001';
    private const IMAGEN_USER_MASK_DILATION = 0.025;

    protected string $apiKey;
    protected string $textModel;
    protected string $configuredImageModel;
    protected string $imageModel;
    protected string $videoModel;
    protected string $activeModel;
    protected string $mode; // 'text', 'image', 'product', or 'video'
    protected string $authMethod; // 'api_key' or 'service_account'
    protected string $projectId;
    protected string $region;
    protected ?string $videoStorageUri = null;
    protected ?array $serviceAccount = null;
    protected ?string $explicitAspectRatio = null;

    public function __construct(string $mode = 'text', ?string $aspectRatio = null)
    {
        $this->apiKey = Setting::getValue('gemini_api_key', '');
        $this->authMethod = Setting::getValue('gemini_auth_method', 'service_account');

        $this->textModel = Setting::getValue('gemini_text_model', 'gemini-2.5-flash');
        $this->configuredImageModel = Setting::getValue('gemini_image_model', 'gemini-3.1-flash-image-preview');
        $this->imageModel = $this->resolveImageModelAlias($this->configuredImageModel);
        $this->videoModel = trim((string) Setting::getValue('gemini_video_model', 'veo-3.0-generate-001'));

        $this->mode = in_array($mode, ['text', 'image', 'product', 'video']) ? $mode : 'text';
        $this->activeModel = match ($this->mode) {
            'image', 'product' => $this->imageModel,
            'video' => $this->videoModel,
            default => $this->textModel,
        };
        $this->explicitAspectRatio = $aspectRatio;

        // Vertex AI config
        $this->projectId = config('services.vertex_ai.project_id', '');
        $this->region = config('services.vertex_ai.region', 'us-central1');
        $this->videoStorageUri = config('services.vertex_ai.video_storage_uri');

        // Load service account if using that auth method
        if ($this->authMethod === 'service_account') {
            $this->loadServiceAccount();
        }
    }

    public function getModel(): string
    {
        return $this->activeModel;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getAuthMethod(): string
    {
        return $this->authMethod;
    }

    /**
     * Send a chat message with conversation history to Gemini.
     */
    public function chat(array $history): array
    {
        $lastMsg = array_pop($history);
        $currentParts = $lastMsg ? [['text' => $lastMsg['content']]] : [['text' => '']];
        return $this->chatWithParts($history, $currentParts);
    }

    /**
     * Send a multimodal chat message (text + optional image) with conversation history.
     * Returns text content and any generated images as base64.
     *
     * @return array{success: bool, content: string|null, images: array, error: string|null}
     */
    public function chatWithParts(array $history, array $currentParts): array
    {
        $authCheck = $this->checkAuth();
        if ($authCheck !== null) {
            return $authCheck;
        }

        // Image / Product mode: use the configured image model.
        // Product mode sends uploaded reference images + prompt to the image model
        // so Gemini can generate new images based on them (merge, edit, etc.).
        if (in_array($this->mode, ['image', 'product'])) {
            return $this->isImagenModel()
                ? $this->imagenGenerate($currentParts)
                : $this->geminiNativeImageGenerate($history, $currentParts);
        }

        $contents = [];

        foreach ($history as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : $msg['role'];
            // Support multimodal history — use 'parts' if provided, fallback to text
            $parts = isset($msg['parts']) ? $msg['parts'] : [['text' => $msg['content'] ?? '']];
            $contents[] = [
                'role'  => $role,
                'parts' => $parts,
            ];
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => $currentParts,
        ];

        $url = $this->buildEndpointUrl($this->activeModel, 'generateContent');

        // Build generation config — product mode needs more tokens and longer timeout
        $generationConfig = [
            'temperature'     => 0.7,
            'maxOutputTokens' => 8192,
        ];

        $timeout = $this->mode === 'product' ? 120 : 60;

        try {
            $request = Http::timeout($timeout);
            $request = $this->applyAuth($request);

            $body = [
                'contents'         => $contents,
                'generationConfig' => $generationConfig,
            ];

            $response = $request->post($url, $body);

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message', '');
                if (empty($errorMsg)) {
                    $errorMsg = 'Gemini API error (HTTP ' . $response->status() . ')';
                }
                Log::error('Gemini API error', [
                    'status'      => $response->status(),
                    'error'       => $errorMsg,
                    'body'        => \Illuminate\Support\Str::limit($response->body(), 500),
                    'model'       => $this->activeModel,
                    'mode'        => $this->mode,
                    'auth_method' => $this->authMethod,
                ]);
                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => $errorMsg,
                ];
            }

            // Parse multipart response — may contain text and/or images
            $parts = $response->json('candidates.0.content.parts', []);
            $textParts = [];
            $images = [];

            foreach ($parts as $part) {
                // Skip thinking parts (interim reasoning images)
                if (! empty($part['thought'])) {
                    continue;
                }

                if (isset($part['text'])) {
                    $textParts[] = $part['text'];
                }

                if (isset($part['inlineData'])) {
                    $images[] = [
                        'data'      => $part['inlineData']['data'],
                        'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                    ];
                }
                // Also handle snake_case variant from API
                if (isset($part['inline_data'])) {
                    $images[] = [
                        'data'      => $part['inline_data']['data'],
                        'mime_type' => $part['inline_data']['mime_type'] ?? 'image/png',
                    ];
                }
            }

            $text = implode("\n", $textParts);

            return [
                'success' => true,
                'content' => $text ?: null,
                'images'  => $images,
                'error'   => null,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Gemini API timeout/connection error', [
                'message'     => $e->getMessage(),
                'auth_method' => $this->authMethod,
                'mode'        => $this->mode,
            ]);
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'Image generation timed out. Please try again with a simpler prompt.',
            ];
        } catch (\Exception $e) {
            Log::error('Gemini API exception', [
                'message'     => $e->getMessage(),
                'auth_method' => $this->authMethod,
            ]);
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'Something went wrong while generating the image. Please try again.',
            ];
        }
    }

    /**
     * Quick connectivity test — sends a trivial prompt.
     */
    public function testConnection(): array
    {
        $authCheck = $this->checkAuth();
        if ($authCheck !== null) {
            return ['success' => false, 'message' => $authCheck['error']];
        }

        $url = $this->buildEndpointUrl($this->textModel, 'generateContent');

        try {
            $request = Http::timeout(10);
            $request = $this->applyAuth($request);

            $response = $request->post($url, [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'Hi, respond with OK']]],
                ],
            ]);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text', '');
                $method = $this->authMethod === 'service_account' ? 'Service Account (Vertex AI)' : 'API Key';
                $imageModel = $this->configuredImageModel === $this->imageModel
                    ? $this->imageModel
                    : $this->configuredImageModel . ' => ' . $this->imageModel;
                return [
                    'success' => true,
                    'message' => "Connected via {$method}. Text model: {$this->textModel}, Image model: {$imageModel}, Video model: {$this->videoModel}. Response: " . \Illuminate\Support\Str::limit($text, 60),
                ];
            }

            return ['success' => false, 'message' => 'API error: ' . $response->json('error.message', 'Unknown error')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────
    //  Veo (Vertex AI video generation)
    // ──────────────────────────────────────────────

    public function startVideoGeneration(string $prompt, array $options = [], ?array $referenceImage = null): array
    {
        $authCheck = $this->checkAuth();
        if ($authCheck !== null) {
            return [
                'success' => false,
                'error' => $authCheck['error'],
            ];
        }

        if ($this->authMethod !== 'service_account') {
            return [
                'success' => false,
                'error' => 'Veo video generation requires Vertex AI service account authentication.',
            ];
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            return [
                'success' => false,
                'error' => 'No prompt provided for video generation.',
            ];
        }

        $videoOptions = $this->normalizeVideoOptions($options);
        $instance = ['prompt' => $prompt];

        if ($referenceImage !== null) {
            $imageData = $referenceImage['data'] ?? $referenceImage['bytesBase64Encoded'] ?? null;
            if (is_string($imageData) && $imageData !== '') {
                $instance['image'] = [
                    'bytesBase64Encoded' => $imageData,
                    'mimeType' => $this->normalizeImageMimeType($referenceImage['mime_type'] ?? $referenceImage['mimeType'] ?? 'image/jpeg'),
                ];
            }
        }

        $parameters = [
            'sampleCount' => 1,
            'durationSeconds' => $videoOptions['duration_seconds'],
            'aspectRatio' => $videoOptions['aspect_ratio'],
            'resolution' => $videoOptions['resolution'],
            'generateAudio' => $videoOptions['generate_audio'],
            'enhancePrompt' => true,
            'personGeneration' => $videoOptions['person_generation'],
        ];

        if ($referenceImage !== null) {
            $parameters['resizeMode'] = $videoOptions['resize_mode'];
        }

        if (! empty($videoOptions['negative_prompt'])) {
            $parameters['negativePrompt'] = $videoOptions['negative_prompt'];
        }

        if (is_string($this->videoStorageUri) && str_starts_with($this->videoStorageUri, 'gs://')) {
            $parameters['storageUri'] = rtrim($this->videoStorageUri, '/') . '/';
        }

        $body = [
            'instances' => [$instance],
            'parameters' => $parameters,
        ];

        $url = $this->buildEndpointUrl($this->videoModel, 'predictLongRunning');

        try {
            $request = Http::timeout(self::VIDEO_REQUEST_TIMEOUT_SECONDS);
            $request = $this->applyAuth($request);
            $response = $request->post($url, $body);

            if (! $response->successful()) {
                $errorMsg = $this->extractVeoErrorMessage($response->status(), $response->body(), 'Unknown Veo API error');
                Log::error('Veo start operation failed', [
                    'status' => $response->status(),
                    'error' => $errorMsg,
                    'model' => $this->videoModel,
                    'body' => mb_substr($response->body(), 0, 2000),
                ]);

                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'status_code' => $response->status(),
                    'retry_without_reference' => $referenceImage !== null && $this->isGoogleAutomatedQueryBlock($response->status(), $response->body()),
                    'request_payload' => $this->redactVideoRequestPayload($body),
                    'response_payload' => $response->json() ?: [
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 2000),
                    ],
                ];
            }

            $operationName = $response->json('name');
            if (! is_string($operationName) || $operationName === '') {
                return [
                    'success' => false,
                    'error' => 'Veo did not return an operation name.',
                    'request_payload' => $this->redactVideoRequestPayload($body),
                    'response_payload' => $response->json(),
                ];
            }

            Log::info('Veo video operation started', [
                'model' => $this->videoModel,
                'operation_name' => $operationName,
                'aspect_ratio' => $videoOptions['aspect_ratio'],
                'duration_seconds' => $videoOptions['duration_seconds'],
                'resolution' => $videoOptions['resolution'],
            ]);

            return [
                'success' => true,
                'operation_name' => $operationName,
                'model' => $this->videoModel,
                'request_payload' => $this->redactVideoRequestPayload($body),
                'response_payload' => $response->json(),
                'options' => $videoOptions,
            ];
        } catch (\Throwable $e) {
            Log::error('Veo start operation exception', [
                'model' => $this->videoModel,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Video generation could not be started. Please try again.',
                'request_payload' => $this->redactVideoRequestPayload($body),
            ];
        }
    }

    private function extractVeoErrorMessage(int $statusCode, string $body, string $fallback): string
    {
        if ($this->isGoogleAutomatedQueryBlock($statusCode, $body)) {
            return 'Veo rejected the inline reference image with a Google automated-query protection page.';
        }

        $decoded = json_decode($body, true);
        $message = data_get($decoded, 'error.message');

        return is_string($message) && $message !== '' ? $message : $fallback;
    }

    private function isGoogleAutomatedQueryBlock(int $statusCode, string $body): bool
    {
        return $statusCode === 417
            && str_contains($body, 'automated queries')
            && str_contains($body, 'Google');
    }

    public function fetchVideoOperation(string $operationName): array
    {
        $authCheck = $this->checkAuth();
        if ($authCheck !== null) {
            return [
                'success' => false,
                'error' => $authCheck['error'],
            ];
        }

        $url = $this->buildEndpointUrl($this->videoModel, 'fetchPredictOperation');

        try {
            $request = Http::timeout(self::VIDEO_REQUEST_TIMEOUT_SECONDS);
            $request = $this->applyAuth($request);
            $response = $request->post($url, ['operationName' => $operationName]);

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message', 'Unknown Veo operation error');
                Log::error('Veo fetch operation failed', [
                    'status' => $response->status(),
                    'error' => $errorMsg,
                    'operation_name' => $operationName,
                    'body' => mb_substr($response->body(), 0, 2000),
                ]);

                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'response_payload' => $response->json() ?: ['body' => mb_substr($response->body(), 0, 2000)],
                ];
            }

            $operation = $response->json();

            return [
                'success' => true,
                'done' => (bool) ($operation['done'] ?? false),
                'operation' => $operation,
            ];
        } catch (\Throwable $e) {
            Log::error('Veo fetch operation exception', [
                'operation_name' => $operationName,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Video generation status could not be checked. Please try again.',
            ];
        }
    }

    public function parseVideoOperationResult(array $operation): array
    {
        if (! empty($operation['error'])) {
            return [
                'success' => false,
                'error' => $operation['error']['message'] ?? 'Veo video generation failed.',
                'error_code' => $operation['error']['code'] ?? null,
            ];
        }

        $gcsUris = [];
        $inlineVideos = [];
        $this->collectVideoArtifacts($operation['response'] ?? $operation, $gcsUris, $inlineVideos);

        $gcsUris = array_values(array_unique(array_filter($gcsUris)));

        if (empty($gcsUris) && empty($inlineVideos)) {
            return [
                'success' => false,
                'error' => 'Veo completed but returned no video artifacts.',
            ];
        }

        return [
            'success' => true,
            'gcs_uris' => $gcsUris,
            'inline_videos' => $inlineVideos,
        ];
    }

    public function normalizeVideoOptions(array $options): array
    {
        $duration = (int) ($options['duration_seconds'] ?? $options['durationSeconds'] ?? 8);
        if (! in_array($duration, [4, 6, 8], true)) {
            $duration = 8;
        }

        $aspectRatio = (string) ($options['aspect_ratio'] ?? $options['aspectRatio'] ?? $this->explicitAspectRatio ?? '16:9');
        if (! in_array($aspectRatio, ['16:9', '9:16'], true)) {
            $aspectRatio = '16:9';
        }

        $resolution = (string) ($options['resolution'] ?? '720p');
        if (! in_array($resolution, ['720p', '1080p'], true)) {
            $resolution = '720p';
        }

        $resizeMode = (string) ($options['resize_mode'] ?? $options['resizeMode'] ?? 'pad');
        if (! in_array($resizeMode, ['pad', 'crop'], true)) {
            $resizeMode = 'pad';
        }

        $personGeneration = (string) ($options['person_generation'] ?? $options['personGeneration'] ?? 'allowAll');
        if ($personGeneration === 'allow_all') {
            $personGeneration = 'allowAll';
        }
        if (! in_array($personGeneration, ['dont_allow', 'allow_adult', 'allowAll'], true)) {
            $personGeneration = 'allowAll';
        }

        return [
            'duration_seconds' => $duration,
            'aspect_ratio' => $aspectRatio,
            'resolution' => $resolution,
            'generate_audio' => filter_var($options['generate_audio'] ?? $options['generateAudio'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'resize_mode' => $resizeMode,
            'person_generation' => $personGeneration,
            'negative_prompt' => trim((string) ($options['negative_prompt'] ?? $options['negativePrompt'] ?? '')),
        ];
    }

    // ──────────────────────────────────────────────
    //  Imagen (Vertex AI image generation)
    // ──────────────────────────────────────────────

    private function isImagenModel(): bool
    {
        return str_starts_with($this->imageModel, 'imagen');
    }

    /**
     * Accept friendly marketing names in settings, but always call the exact API model ID.
     */
    private function resolveImageModelAlias(string $model): string
    {
        $normalizedModel = strtolower(trim($model));

        return match ($normalizedModel) {
            'nano-banana-2', 'nano banana 2', 'nano-banana-2 preview', 'nano banana 2 preview',
            'nano-banana-pro', 'nano banana pro', 'nano-banana-pro preview', 'nano banana pro preview',
            'gemini-3-pro-image-preview',
            'nano-banana', 'nano banana', 'nano-banana-1', 'nano banana 1' => 'gemini-3.1-flash-image-preview',
            default => trim($model),
        };
    }

    /**
     * Detect aspect ratio from user prompt text.
     * Supports formats: "3:4", "16:9", "aspect ratio 3:4", "نسبة 3:4", etc.
     * Falls back to '1:1' if not detected.
     */
    private function detectAspectRatio(string $prompt): string
    {
        $validRatios = ['1:1', '3:4', '4:3', '9:16', '16:9'];

        // 1. Check if user explicitly mentioned an aspect ratio in the prompt text — this always wins.
        if (preg_match('/(?:aspect\s*ratio|ratio|نسبة)?\s*(\d{1,2})\s*[:x×]\s*(\d{1,2})/iu', $prompt, $matches)) {
            $detected = $matches[1] . ':' . $matches[2];
            if (in_array($detected, $validRatios, true)) {
                return $detected;
            }
        }

        // Check for keywords like "portrait" → 3:4, "landscape" → 4:3, "widescreen" → 16:9
        $lower = mb_strtolower($prompt);
        if (str_contains($lower, 'portrait') || str_contains($lower, 'بورتريه') || str_contains($lower, 'طولي')) {
            return '3:4';
        }
        if (str_contains($lower, 'widescreen') || str_contains($lower, 'wide') || str_contains($lower, 'عريض') || str_contains($lower, 'سينمائي')) {
            return '16:9';
        }
        if (str_contains($lower, 'landscape') || str_contains($lower, 'أفقي')) {
            return '4:3';
        }

        // 2. Fall back to the frontend-selected aspect ratio.
        if ($this->explicitAspectRatio && in_array($this->explicitAspectRatio, $validRatios, true)) {
            return $this->explicitAspectRatio;
        }

        return '1:1';
    }

    /**
     * Preserve the requested photographic style while preventing fake camera UI,
     * timestamps, watermarks, symbols, or phone-screen overlays from being drawn.
     */
    private function sanitizeImagePrompt(string $prompt): string
    {
        $trimmed = trim($prompt);

        if ($trimmed === '') {
            return $trimmed;
        }

        $guardrail = ' Generate a natural-looking photo with realistic lighting and colors. Do not over-stylize, do not add cinematic color grading or dramatic HDR effects. No phone UI, camera interface, shutter button, status bar, timestamp, watermark, logo, text overlay, symbols, collage frame, or decorative border.';

        return $trimmed . $guardrail;
    }

    private function shouldSkipPromptEnhancement(string $prompt): bool
    {
        $trimmed = trim($prompt);

        if ($trimmed === '') {
            return true;
        }

        // Only skip for very long, highly technical prompts that already have
        // explicit quality/style keywords — never skip for normal user prompts.
        // Strip the guardrail suffix before counting words.
        $stripped = preg_replace('/\s*Generate a natural-looking photo.*$/s', '', $trimmed);
        $stripped = preg_replace('/\s*Generate only the photo itself.*$/s', '', $stripped);

        $isEnglishLike = preg_match('/^[\x20-\x7E\r\n\t]+$/', $stripped) === 1;
        $wordCount = preg_match_all('/\S+/u', $stripped);
        $hasExplicitQuality = preg_match(
            '/photorealistic|ultra\s*detailed|8K|cinematic\s*lighting|35mm\s*film|hyper.?realistic/i',
            $stripped
        ) === 1;

        return $isEnglishLike && $wordCount >= 40 && $hasExplicitQuality;
    }

    private function nativeImageSize(): string
    {
        return app()->environment('production') ? '2K' : '1K';
    }

    /**
     * Use Gemini's NATIVE image generation (generateContent with responseModalities).
     * This is the same method the Gemini app uses — it produces significantly
     * higher quality images than the Imagen predict API.
     *
      * Flow:
      * 1. Send the raw user prompt to the configured Gemini image model.
      * 2. Request image-only output with the desired aspect ratio.
      * 3. Return the exact failure from that model if it does not succeed.
     */
    private function geminiNativeImageGenerate(array $history, array $currentParts): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        // Extract the raw text from current parts
        $rawPrompt = '';
        $inlineImages = [];
        foreach ($currentParts as $part) {
            if (isset($part['text'])) {
                $rawPrompt .= $part['text'] . ' ';
            }
            if (isset($part['inline_data'])) {
                $inlineImages[] = $part;
            }
        }
        $rawPrompt = trim($rawPrompt);

        if (empty($rawPrompt)) {
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'No prompt provided for image generation.',
            ];
        }

        if (! str_starts_with($this->imageModel, 'gemini')) {
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => "Configured image model {$this->imageModel} is not a Gemini native image model.",
            ];
        }

        // Detect aspect ratio from prompt
        $aspectRatio = $this->detectAspectRatio($rawPrompt);

        $imageGenModel = $this->imageModel;

        // Check if this is an image-editing request (has inline images from user or history)
        $hasReferenceImages = ! empty($inlineImages);
        if (! $hasReferenceImages) {
            foreach ($history as $turn) {
                foreach ($turn['parts'] ?? [] as $p) {
                    if (isset($p['inline_data'])) {
                        $hasReferenceImages = true;
                        break 2;
                    }
                }
            }
        }

        $nativePrompt = $hasReferenceImages ? trim($rawPrompt) : $this->sanitizeImagePrompt($rawPrompt);

        // For image editing, preserve the user's edit intent; for new images, enhance cinematically
        if ($hasReferenceImages) {
            // Light translation only — keep editing instructions intact
            $nativePrompt = $this->translateForImageEdit($nativePrompt);
        } else {
            $nativePrompt = $this->enhancePromptForImageGeneration($nativePrompt, $imageGenModel);
        }

        $shouldIncludeHistoryInlineImages = empty($inlineImages);

        // Build contents — include history with images for editing context
        $contents = [];

        if ($hasReferenceImages) {
            foreach ($history as $turn) {
                $turnParts = [];
                foreach ($turn['parts'] ?? [] as $p) {
                    if (isset($p['text']) || ($shouldIncludeHistoryInlineImages && isset($p['inline_data']))) {
                        $turnParts[] = $p;
                    }
                }
                if (! empty($turnParts)) {
                    $contents[] = [
                        'role'  => $turn['role'] ?? 'user',
                        'parts' => $turnParts,
                    ];
                }
            }
        }

        // Build the user parts — preserve the user prompt while blocking UI overlays.
        $userParts = [['text' => $nativePrompt]];
        foreach ($inlineImages as $imgPart) {
            $userParts[] = $imgPart;
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => $userParts,
        ];

        // Match the production flow: IMAGE-only output with explicit imageConfig.
        // ImageConfig only supports: aspectRatio, imageSize (per API docs).
        // personGeneration goes in safetySettings or model config, not imageConfig.
        $imageGenConfig = [
            'aspectRatio' => $aspectRatio,
            'imageSize'   => $this->nativeImageSize(),
        ];

        if ($hasReferenceImages) {
            $generationConfig = [
                'responseModalities' => ['IMAGE'],
                'temperature'        => 0.3,
                'imageConfig'        => $imageGenConfig,
            ];
        } else {
            $generationConfig = [
                'responseModalities' => ['IMAGE'],
                'temperature'        => 1.0,
                'imageConfig'        => $imageGenConfig,
            ];
        }

        Log::info('Trying Gemini native image generation', [
            'prompt'           => $nativePrompt,
            'configured_model' => $this->configuredImageModel,
            'model'            => $imageGenModel,
            'aspect_ratio'     => $aspectRatio,
            'is_editing'       => $hasReferenceImages,
            'history_turns'    => count($history),
            'auth_method'      => $this->authMethod,
        ]);

        try {
            $requestAuthMethod = $this->authMethod;

            [$response, $url] = $this->sendNativeImageRequest(
                $imageGenModel,
                $contents,
                $generationConfig,
                $requestAuthMethod
            );

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message', 'Unknown error');
                $responseBody = $response->body();
                $status = $response->status();
                Log::error('Gemini native image model failed', [
                    'model'         => $imageGenModel,
                    'status'        => $status,
                    'error'         => $errorMsg,
                    'is_editing'    => $hasReferenceImages,
                    'auth_method'   => $requestAuthMethod,
                    'response_body' => mb_substr($responseBody, 0, 2000),
                ]);

                // HTTP 417 (or any HTML response containing the Google "Sorry..."
                // anti-automation page) means the upstream is rejecting the
                // request because of inline reference images. Tell the user to
                // use the inpainting editor (which goes through Vertex Imagen
                // and is not subject to the same block).
                $looksLikeAntiAutomation = $status === 417
                    || str_contains($responseBody, 'sending automated queries')
                    || str_contains($responseBody, 'Sorry...');

                $userError = $looksLikeAntiAutomation && $hasReferenceImages
                    ? 'Image-editing requests with attached photos are being temporarily blocked by the upstream model. Please open the image and use the brush editor (“Edit”) to make targeted changes.'
                    : 'Image generation failed. Please try again with a different prompt.';

                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => $userError,
                ];
            }

            // Parse response
            $parts = $response->json('candidates.0.content.parts', []);
            $images = [];

            foreach ($parts as $part) {
                if (! empty($part['thought'])) continue;

                if (isset($part['inlineData'])) {
                    $images[] = [
                        'data'      => $part['inlineData']['data'],
                        'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                    ];
                }
                if (isset($part['inline_data'])) {
                    $images[] = [
                        'data'      => $part['inline_data']['data'],
                        'mime_type' => $part['inline_data']['mime_type'] ?? 'image/png',
                    ];
                }
            }

            if (empty($images)) {
                Log::error('Gemini native image model returned no images', [
                    'model'       => $imageGenModel,
                    'is_editing'  => $hasReferenceImages,
                    'part_shapes' => array_map(
                        static fn (array $part): array => array_keys($part),
                        $parts,
                    ),
                ]);

                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => 'No image was generated. Please try again with a different prompt.',
                ];
            }

            Log::info("Image generated successfully with {$imageGenModel}");
            return [
                'success' => true,
                'content' => null,
                'images'  => $images,
                'error'   => null,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Gemini native image timeout/connection error', [
                'model'   => $imageGenModel,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'Image generation timed out. Please try again with a simpler prompt.',
            ];
        } catch (\Exception $e) {
            Log::error('Gemini native image exception', [
                'model'   => $imageGenModel,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'Something went wrong while generating the image. Please try again.',
            ];
        }
    }

    /**
     * Use the text model to translate/enhance a user prompt into a detailed,
     * cinematic English image-generation prompt optimized for the target image model.
     *
     * This is the "brain" step: Gemini Flash acts as a Creative Prompt Engineer,
     * expanding simple user input into a hyper-detailed professional description.
     */
    private function enhancePromptForImageGeneration(string $userPrompt, string $targetModel): string
    {
        if ($this->shouldSkipPromptEnhancement($userPrompt)) {
            Log::info('Prompt enhancement skipped for detailed prompt', [
                'target_model' => $targetModel,
                'original'     => $userPrompt,
            ]);

            return $userPrompt;
        }

        $cacheKey = 'image_prompt_enhance:' . md5($targetModel . '|' . $userPrompt);
        $cachedPrompt = Cache::get($cacheKey);

        if (is_string($cachedPrompt) && $cachedPrompt !== '') {
            Log::info('Prompt enhancement cache hit', [
                'target_model' => $targetModel,
                'original'     => $userPrompt,
            ]);

            return $cachedPrompt;
        }

        $systemInstruction = <<<'SYSTEM'
You are an image prompt assistant. Rewrite the user's request into a clear, detailed English prompt for an AI image generator.

RULES:
1. KEEP EVERY ELEMENT the user mentions. Never remove, minimize, or make anything "faint" or "barely visible". Every element must be prominent and clearly visible.
2. TRANSLATE non-English input to English first, then enhance.
3. ADD NATURAL DETAIL: Describe the scene with enough detail to guide the generator, but keep it natural and realistic — not over-stylized:
   - Lighting: Match the scene naturally (daylight, indoor, evening, etc.)
   - Setting: Briefly describe the environment
   - Composition: camera angle, distance, framing if relevant
4. CULTURAL ACCURACY for Arabian/Middle Eastern scenes: white Thobe, red-and-white Shemagh with black Agal, traditional camel saddle
5. DO NOT add heavy stylization keywords like "ultra detailed", "8K", "cinematic lighting", "35mm film", "hyper-realistic". Keep the style natural and let the model decide quality.
6. PRESERVE COMPOSITION WORDS EXACTLY: if the user says medium distance, full body, side view, low angle, far in the sky, or camera flash, keep that framing and scale exactly.
7. PRESERVE SECONDARY SUBJECTS: if the prompt includes objects like jets, weapons, buildings, moonlight, or dust trails, keep them visible in the requested size and location.
8. OUTPUT: One paragraph in English only. No quotes, no explanation, no markdown.
SYSTEM;

        try {
            $url = $this->buildEndpointUrl($this->textModel, 'generateContent');

            $request = Http::timeout(20);
            $request = $this->applyAuth($request);

            $requestBody = [
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [['text' => $userPrompt]],
                    ],
                ],
                'systemInstruction' => [
                    'parts' => [['text' => $systemInstruction]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.7,
                    'maxOutputTokens' => 512,
                    'thinkingConfig'  => ['thinkingBudget' => 0],
                ],
            ];

            $response = $request->post($url, $requestBody);

            if ($response->successful()) {
                $enhanced = trim($response->json('candidates.0.content.parts.0.text', ''));
                // Strip any surrounding quotes the model might add
                $enhanced = trim($enhanced, "\"'`");
                if (! empty($enhanced)) {
                    Cache::put($cacheKey, $enhanced, now()->addHours(12));
                    Log::info('Prompt enhanced for image generation', [
                        'target_model' => $targetModel,
                        'original'     => $userPrompt,
                        'enhanced'     => $enhanced,
                    ]);
                    return $enhanced;
                }
            } else {
                Log::warning('Prompt enhancement API failed', [
                    'status' => $response->status(),
                    'error'  => $response->json('error.message', 'Unknown'),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Prompt enhancement failed, using original', [
                'target_model' => $targetModel,
                'error'        => $e->getMessage(),
            ]);
        }

        // Fallback: return original prompt if enhancement fails
        return $userPrompt;
    }

    /**
     * Translate an image-editing prompt to English without altering the editing intent.
     */
    private function translateForImageEdit(string $userPrompt): string
    {
        // If already English-like, return as-is
        if (preg_match('/^[\x20-\x7E\n\r\t]+$/', $userPrompt)) {
            return $userPrompt;
        }
        $lines = preg_split('/\r\n|\r|\n/u', $userPrompt) ?: [$userPrompt];
        $translatedLines = [];

        foreach ($lines as $line) {
            if ($line === '') {
                $translatedLines[] = $line;
                continue;
            }

            if (preg_match('/^[\x20-\x7E\t ]+$/', $line)) {
                $translatedLines[] = $line;
                continue;
            }

            if (preg_match('/^(?<prefix>[\x20-\x7E\t ]*:\s*)(?<suffix>.*)$/u', $line, $matches) === 1
                && preg_match('/^[\x20-\x7E\t ]*:\s*$/', $matches['prefix']) === 1
            ) {
                $translatedSuffix = $this->translateImageEditSegment($matches['suffix']);
                $translatedLines[] = $matches['prefix'] . $translatedSuffix;
                continue;
            }

            $translatedLines[] = $this->translateImageEditSegment($line);
        }

        $translatedPrompt = trim(implode("\n", $translatedLines));

        if ($translatedPrompt !== '') {
            Log::info('Image edit prompt translated', [
                'original'   => $userPrompt,
                'translated' => $translatedPrompt,
            ]);

            return $translatedPrompt;
        }

        return $userPrompt;
    }

    private function translateImageEditSegment(string $segment): string
    {
        $segment = trim($segment);

        if ($segment === '' || preg_match('/^[\x20-\x7E\n\r\t]+$/', $segment)) {
            return $segment;
        }

        $systemInstruction = <<<'SYSTEM'
You are a translator for image-editing prompts.
RULES:
1. Translate the text to English accurately.
2. Preserve the exact editing intent and constraints.
3. Do not add creative details, styling, or extra explanation.
4. Return only the translated text.
SYSTEM;

        try {
            $url = $this->buildEndpointUrl($this->textModel, 'generateContent');
            $request = Http::timeout(15);
            $request = $this->applyAuth($request);

            $response = $request->post($url, [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $segment]]],
                ],
                'systemInstruction' => [
                    'parts' => [['text' => $systemInstruction]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.2,
                    'maxOutputTokens' => 256,
                    'thinkingConfig'  => ['thinkingBudget' => 0],
                ],
            ]);

            if ($response->successful()) {
                $translated = trim($response->json('candidates.0.content.parts.0.text', ''));
                $translated = trim($translated, "\"'`");
                if ($translated !== '') {
                    return $translated;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Image edit translation failed, using original segment', [
                'error' => $e->getMessage(),
            ]);
        }

        return $segment;
    }

    public function inpaintWithMask(
        array $sourceImage,
        array $maskImage,
        string $prompt,
        ?string $editMode = 'EDIT_MODE_INPAINT_INSERTION',
        float $maskDilation = 0.0,
        array $extra = [],
    ): array
    {
        $authCheck = $this->checkAuth();
        if ($authCheck !== null) {
            return $authCheck;
        }

        if ($this->authMethod !== 'service_account') {
            return [
                'success' => false,
                'content' => null,
                'images' => [],
                'error' => 'Explicit mask inpainting requires Vertex AI service account authentication.',
                'model_used' => $this->imageModel,
                'engine_provider' => 'gemini',
                'prompt_used' => $prompt,
            ];
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            return [
                'success' => false,
                'content' => null,
                'images' => [],
                'error' => 'No prompt provided for inpainting.',
                'model_used' => self::IMAGEN_MASK_EDIT_MODEL,
                'engine_provider' => 'imagen',
                'prompt_used' => $prompt,
            ];
        }

        $sourceImageData = trim((string) ($sourceImage['data'] ?? ''));
        $maskImageData = trim((string) ($maskImage['data'] ?? ''));

        if ($sourceImageData === '' || $maskImageData === '') {
            return [
                'success' => false,
                'content' => null,
                'images' => [],
                'error' => 'Source image or mask image is missing.',
                'model_used' => self::IMAGEN_MASK_EDIT_MODEL,
                'engine_provider' => 'imagen',
                'prompt_used' => $prompt,
            ];
        }

        // Caller is expected to have rewritten the prompt to a full final-image
        // English description. We still translate as a safety net for raw Arabic
        // input bypassing the rewrite layer.
        $translatedPrompt = $this->translateForImageEdit($prompt);
        $model = self::IMAGEN_MASK_EDIT_MODEL;
        $url = $this->buildEndpointUrl($model, 'predict');

        $negativePrompt = trim((string) ($extra['negative_prompt']
            ?? 'blurry, low quality, deformed, distorted, extra limbs, extra fingers, '
                . 'duplicate people, duplicate face, mutated face, low resolution, '
                . 'jpeg artifacts, watermark, text, logo, unchanged, identical to original'));

        $guidanceScale = (float) ($extra['guidance_scale'] ?? 75);
        $baseSteps = (int) ($extra['base_steps'] ?? 75);
        $personGeneration = (string) ($extra['person_generation'] ?? 'allow_adult');
        $safetySetting = (string) ($extra['safety_setting'] ?? 'block_only_high');
        $sampleCount = max(1, (int) ($extra['sample_count'] ?? 1));

        $instance = [
            'prompt' => $translatedPrompt,
            'referenceImages' => [
                [
                    'referenceId' => 1,
                    'referenceType' => 'REFERENCE_TYPE_RAW',
                    'referenceImage' => [
                        'bytesBase64Encoded' => $sourceImageData,
                        'mimeType' => $sourceImage['mime_type'] ?? 'image/png',
                    ],
                ],
                [
                    'referenceId' => 2,
                    'referenceType' => 'REFERENCE_TYPE_MASK',
                    'referenceImage' => [
                        'bytesBase64Encoded' => $maskImageData,
                        'mimeType' => $maskImage['mime_type'] ?? 'image/png',
                    ],
                    'maskImageConfig' => [
                        'maskMode' => 'MASK_MODE_USER_PROVIDED',
                        'dilation' => $maskDilation,
                    ],
                ],
            ],
        ];

        $parameters = [
            'sampleCount' => $sampleCount,
            'guidanceScale' => $guidanceScale,
            'negativePrompt' => $negativePrompt,
            'personGeneration' => $personGeneration,
            'safetySetting' => $safetySetting,
            'editConfig' => [
                'baseSteps' => $baseSteps,
            ],
            'outputOptions' => [
                'mimeType' => 'image/png',
            ],
        ];

        if ($editMode !== null) {
            $parameters['editMode'] = $editMode;
        }

        $requestBody = [
            'instances' => [$instance],
            'parameters' => $parameters,
        ];

        Log::info('Trying Imagen explicit-mask inpainting', [
            'model' => $model,
            'prompt' => $translatedPrompt,
            'mask_dilation' => $maskDilation,
            'edit_mode' => $editMode,
            'guidance_scale' => $guidanceScale,
            'base_steps' => $baseSteps,
            'person_generation' => $personGeneration,
            'safety_setting' => $safetySetting,
        ]);

        try {
            $request = Http::timeout(self::IMAGE_REQUEST_TIMEOUT_SECONDS);
            $request = $this->applyAuth($request);

            $response = $request->post($url, $requestBody);

            $rawResponseBody = $response->body();

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message', 'Unknown Imagen API error');

                Log::error('Imagen explicit-mask inpainting failed', [
                    'model' => $model,
                    'status' => $response->status(),
                    'error' => $errorMsg,
                    'response_body' => mb_substr($rawResponseBody, 0, 2000),
                ]);

                return [
                    'success' => false,
                    'content' => null,
                    'images' => [],
                    'error' => 'Image generation failed. Please try again with a different prompt.',
                    'model_used' => $model,
                    'engine_provider' => 'imagen',
                    'prompt_used' => $translatedPrompt,
                    'request_body' => $requestBody,
                    'response_body' => $rawResponseBody,
                    'http_status' => $response->status(),
                ];
            }

            $predictions = $response->json('predictions', []);
            $images = [];
            $raiReasons = [];

            foreach ($predictions as $prediction) {
                $imagePayload = $prediction['_self'] ?? $prediction;
                if (isset($imagePayload['bytesBase64Encoded'])) {
                    $images[] = [
                        'data' => $imagePayload['bytesBase64Encoded'],
                        'mime_type' => $imagePayload['mimeType'] ?? 'image/png',
                    ];
                }
                if (isset($imagePayload['raiFilteredReason'])) {
                    $raiReasons[] = $imagePayload['raiFilteredReason'];
                }
            }

            if (empty($images)) {
                Log::error('Imagen explicit-mask inpainting returned no images', [
                    'model' => $model,
                    'rai_reasons' => $raiReasons,
                    'prediction_keys' => array_map(
                        static fn (array $prediction): array => array_keys($prediction),
                        $predictions,
                    ),
                ]);

                $errorMessage = ! empty($raiReasons)
                    ? 'Image was filtered by safety policy: ' . implode('; ', $raiReasons)
                    : 'No image was generated. Please try again with a different prompt or a larger mask.';

                return [
                    'success' => false,
                    'content' => null,
                    'images' => [],
                    'error' => $errorMessage,
                    'model_used' => $model,
                    'engine_provider' => 'imagen',
                    'prompt_used' => $translatedPrompt,
                    'rai_filtered' => ! empty($raiReasons),
                    'rai_reasons' => $raiReasons,
                    'request_body' => $requestBody,
                    'response_body' => $rawResponseBody,
                    'http_status' => $response->status(),
                ];
            }

            return [
                'success' => true,
                'content' => null,
                'images' => $images,
                'error' => null,
                'model_used' => $model,
                'engine_provider' => 'imagen',
                'prompt_used' => $translatedPrompt,
                'rai_filtered' => false,
                'rai_reasons' => [],
                'request_body' => $requestBody,
                'response_body' => $rawResponseBody,
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('Imagen explicit-mask inpainting exception', [
                'model' => $model,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'content' => null,
                'images' => [],
                'error' => 'Something went wrong while generating the image. Please try again.',
                'model_used' => $model,
                'engine_provider' => 'imagen',
                'prompt_used' => $translatedPrompt,
                'request_body' => $requestBody,
                'response_body' => null,
                'http_status' => 0,
            ];
        }
    }

    /**
     * Rewrite a user's natural-language inpainting/edit instruction into a single
     * full final-image English prompt suitable for Imagen explicit-mask editing.
     *
     * The rewrite must:
     *  - describe the FINAL image (not the instruction),
     *  - clearly state the new desired content,
     *  - never mention the removed/original entity (no "man", "person", "old object", etc.),
     *  - preserve lighting, shadows, camera angle, perspective, scale, texture, photographic style,
     *  - leave everything outside the masked area unchanged,
     *  - use neutral location words like "in the edited region" or "in the masked area".
     */
    public function rewriteForInpaintingMask(string $userPrompt): string
    {
        $userPrompt = trim($userPrompt);
        if ($userPrompt === '') {
            return $userPrompt;
        }

        $authCheck = $this->checkAuth();
        if ($authCheck !== null) {
            return $userPrompt;
        }

        $cacheKey = 'inpaint_prompt_rewrite:v2:' . md5($userPrompt);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $systemInstruction = <<<'SYSTEM'
You convert natural-language image-edit instructions into a single English prompt
for an inpainting model that uses an explicit user-provided mask. The mask defines
the area to be edited; everything outside the mask stays unchanged.

OUTPUT RULES:
1. Output ONE clean English paragraph. No quotes, no markdown, no explanation,
   no list, no preface like "A photo of".
2. Describe the FINAL full image after the edit, not the instruction.
3. Clearly and concretely describe the NEW desired content that should appear
   inside the masked area. You MUST use specific nouns when the user asks for them
   (for example: "adult Arab woman", "red leather handbag", "blue baseball cap",
   "wooden dining table", "ankh symbol", "natural sky background"). Do not
   abstract the new content into vague words.
4. NEVER reference the original/removed/previous entity. Forbidden phrasings include
   (but are not limited to): "the man", "the woman", "original man", "old hat",
   "previous subject", "removed person", "replacing the man", "in place of the man",
   "where the man was", "instead of the X", "the prior X". The model must not see
   the prior content's identity. This rule applies ONLY to the entity being removed
   or replaced; nouns describing the NEW desired content are encouraged.
5. Use neutral location phrasing like "naturally positioned in the masked area",
   "naturally integrated in the edited region", or "occupying the masked area".
6. Preserve and explicitly mention: original lighting, shadows, camera angle,
   perspective, scale, texture, materials, depth of field, and photographic style
   of the rest of the scene.
7. State that everything outside the edited region remains unchanged.
8. For RECOLOR requests: preserve the original shape, geometry, texture, material,
   edges, highlights, folds, and lighting; only the color changes to the requested
   color. You MAY name the object whose color is being changed if that object
   itself is being kept (e.g. "the bag is now red") — that is not a removal.
9. For REPLACE requests: describe the new subject/object naturally integrated and
   anchored to the surrounding surface, with matching perspective and shadow,
   WITHOUT naming the entity that was there before.
10. For REMOVAL requests: describe the natural background/fill that should
    plausibly exist behind where something was, again WITHOUT naming what was there.
11. Translate any non-English input to English first.

CORRECT EXAMPLE (replace request):
"The original scene remains unchanged, with an adult Arab woman wearing traditional
clothing naturally positioned in the masked area, matching the original lighting,
shadows, camera angle, perspective, scale, and photographic style."

WRONG EXAMPLE (mentions the removed entity):
"An Arab woman replacing the man where the man was."
SYSTEM;

        try {
            $url = $this->buildEndpointUrl($this->textModel, 'generateContent');
            $request = Http::timeout(20);
            $request = $this->applyAuth($request);

            $response = $request->post($url, [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
                ],
                'systemInstruction' => [
                    'parts' => [['text' => $systemInstruction]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.4,
                    'maxOutputTokens' => 512,
                    'thinkingConfig'  => ['thinkingBudget' => 0],
                ],
            ]);

            if ($response->successful()) {
                $rewritten = trim($response->json('candidates.0.content.parts.0.text', ''));
                $rewritten = trim($rewritten, "\"'`");
                if ($rewritten !== '') {
                    Cache::put($cacheKey, $rewritten, now()->addHours(6));
                    Log::info('Inpainting prompt rewritten', [
                        'original' => $userPrompt,
                        'rewritten' => $rewritten,
                    ]);
                    return $rewritten;
                }
            } else {
                Log::warning('Inpainting prompt rewrite API failed', [
                    'status' => $response->status(),
                    'error' => $response->json('error.message', 'Unknown'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Inpainting prompt rewrite failed, using original', [
                'error' => $e->getMessage(),
            ]);
        }

        return $userPrompt;
    }

    private function imagenGenerate(array $currentParts): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        // Extract prompt text from parts
        $prompt = '';
        $referenceImage = null;
        foreach ($currentParts as $part) {
            if (isset($part['text'])) {
                $prompt .= $part['text'] . ' ';
            }
            if (isset($part['inline_data'])) {
                $referenceImage = $part['inline_data'];
            }
        }
        $prompt = trim($prompt);

        if (empty($prompt)) {
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'No prompt provided for image generation.',
            ];
        }

        // Detect aspect ratio from user prompt before enhancement
        $aspectRatio = $this->detectAspectRatio($prompt);
        $hasReferenceImage = $referenceImage !== null;

        if ($hasReferenceImage) {
            $prompt = $this->translateForImageEdit(trim($prompt));
        } else {
            $prompt = $this->sanitizeImagePrompt($prompt);
            $prompt = $this->enhancePromptForImageGeneration($prompt, $this->imageModel);
        }

        return $this->runImagenPredict($prompt, $aspectRatio, $referenceImage, $this->imageModel);
    }

    private function runImagenPredict(string $prompt, string $aspectRatio, ?array $referenceImage, string $model): array
    {
        Log::info('Trying Imagen image generation', [
            'configured_model' => $this->configuredImageModel,
            'model'            => $model,
            'prompt'           => $prompt,
            'aspect_ratio'     => $aspectRatio,
            'is_editing'       => $referenceImage !== null,
        ]);

        $url = $this->buildEndpointUrl($model, 'predict');
        $instance = ['prompt' => $prompt];

        if ($referenceImage) {
            $instance['image'] = [
                'bytesBase64Encoded' => $referenceImage['data'],
            ];
        }

        $parameters = [
            'sampleCount'       => 1,
            'aspectRatio'       => $aspectRatio,
            'personGeneration'  => 'allow_all',
            'safetyFilterLevel' => 'block_some',
            'addWatermark'      => false,
            'language'          => 'en',
            'enhancePrompt'     => true,
        ];

        try {
            $request = Http::timeout(self::IMAGE_REQUEST_TIMEOUT_SECONDS);
            $request = $this->applyAuth($request);

            $response = $request->post($url, [
                'instances'  => [$instance],
                'parameters' => $parameters,
            ]);

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message', 'Unknown Imagen API error');
                $responseBody = $response->body();
                Log::error('Imagen API error', [
                    'status'        => $response->status(),
                    'error'         => $errorMsg,
                    'model'         => $model,
                    'response_body' => mb_substr($responseBody, 0, 2000),
                ]);
                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => 'Image generation failed. Please try again with a different prompt.',
                ];
            }

            $predictions = $response->json('predictions', []);
            $images = [];

            foreach ($predictions as $prediction) {
                if (isset($prediction['bytesBase64Encoded'])) {
                    $images[] = [
                        'data'      => $prediction['bytesBase64Encoded'],
                        'mime_type' => $prediction['mimeType'] ?? 'image/png',
                    ];
                }
            }

            if (empty($images)) {
                Log::error('Imagen returned no images', [
                    'model' => $model,
                ]);

                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => 'No image was generated. Please try again with a different prompt.',
                ];
            }

            Log::info('Imagen image generated successfully', [
                'model'       => $model,
                'image_count' => count($images),
            ]);

            return [
                'success' => true,
                'content' => null,
                'images'  => $images,
                'error'   => null,
            ];
        } catch (\Exception $e) {
            Log::error('Imagen API exception', [
                'model'   => $model,
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'Something went wrong while generating the image. Please try again.',
            ];
        }
    }

    private function fallbackImageEditViaVisionRewrite(string $editPrompt, string $aspectRatio, ?array $referenceImage, ?string $previousError = null): array
    {
        if ($referenceImage === null) {
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'Image generation failed. Please try again with a different prompt.',
            ];
        }

        Log::warning('Falling back to vision-guided text-to-image rewrite', [
            'aspect_ratio'   => $aspectRatio,
            'previous_error' => $previousError,
        ]);

        $rewrittenPrompt = $this->buildStandaloneImageEditPrompt($editPrompt, $referenceImage);

        if ($rewrittenPrompt === null) {
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'Image generation failed. Please try again with a different prompt.',
            ];
        }

        if ($this->authMethod === 'service_account') {
            return $this->runImagenPredict($rewrittenPrompt, $aspectRatio, null, $this->fallbackImagenModel());
        }

        $generator = new self('image', $aspectRatio);
        return $generator->chatWithParts([], [['text' => $rewrittenPrompt]]);
    }

    private function buildStandaloneImageEditPrompt(string $editPrompt, array $referenceImage): ?string
    {
        $visionInstruction = <<<PROMPT
Analyze the attached reference image and convert the user's edit request into one standalone English image-generation prompt.

Rules:
- Preserve the same main subject, composition, framing, perspective, lighting, texture, and overall visual style from the reference image.
- Apply only the requested change.
- Keep the output suitable for text-to-image generation.
- Output exactly one English paragraph and nothing else.

Requested edit: {$editPrompt}
PROMPT;

        try {
            $visionService = new self('text');
            $result = $visionService->chatWithParts([], [
                ['text' => $visionInstruction],
                ['inline_data' => $referenceImage],
            ]);

            $rewrittenPrompt = trim((string) ($result['content'] ?? ''));
            $rewrittenPrompt = trim($rewrittenPrompt, "\"'`");

            if ($result['success'] && $rewrittenPrompt !== '') {
                Log::info('Built standalone image-edit prompt from reference image', [
                    'original_edit' => $editPrompt,
                    'rewritten'     => $rewrittenPrompt,
                ]);

                return $rewrittenPrompt;
            }

            Log::warning('Vision rewrite for image edit returned empty content', [
                'success' => $result['success'] ?? false,
                'error'   => $result['error'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Vision rewrite for image edit failed', [
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function shouldFallbackToImagen(int $status, string $responseBody): bool
    {
        $normalizedBody = ltrim($responseBody);

        return $status === 417
            || str_contains(strtolower($responseBody), 'automated queries')
            || (str_starts_with(strtolower($normalizedBody), '<html') && str_contains(strtolower($responseBody), 'google'));
    }

    private function extractInlineImage(array $parts): ?array
    {
        foreach (array_reverse($parts) as $part) {
            if (isset($part['inline_data']) && is_array($part['inline_data'])) {
                return $part['inline_data'];
            }
        }

        return null;
    }

    private function extractInlineImageFromHistory(array $history): ?array
    {
        foreach (array_reverse($history) as $turn) {
            foreach (array_reverse($turn['parts'] ?? []) as $part) {
                if (isset($part['inline_data']) && is_array($part['inline_data'])) {
                    return $part['inline_data'];
                }
            }
        }

        return null;
    }

    private function fallbackImagenModel(): string
    {
        return 'imagen-4.0-ultra-generate-001';
    }

    // ──────────────────────────────────────────────
    //  PRIVATE: Auth & Endpoint helpers
    // ──────────────────────────────────────────────

    private function checkAuth(): ?array
    {
        if ($this->mode === 'video' && $this->authMethod !== 'service_account') {
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'Veo video generation requires Vertex AI service account authentication.',
            ];
        }

        if ($this->authMethod === 'service_account') {
            if ($this->serviceAccount === null) {
                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => 'Service account JSON file not found or invalid.',
                ];
            }
            if (empty($this->projectId)) {
                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => 'Vertex AI project ID is not configured.',
                ];
            }
        } else {
            if (empty($this->apiKey)) {
                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => 'Gemini API key is not configured.',
                ];
            }
        }

        return null;
    }

    private function buildEndpointUrl(string $model, string $action): string
    {
        if ($this->authMethod === 'service_account') {
            // Vertex AI endpoint
            return "https://{$this->region}-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->region}/publishers/google/models/{$model}:{$action}";
        }

        // Standard Gemini API endpoint
        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:{$action}?key={$this->apiKey}";
    }

    private function redactVideoRequestPayload(array $body): array
    {
        $redacted = $body;

        foreach ($redacted['instances'] ?? [] as &$instance) {
            if (isset($instance['image']['bytesBase64Encoded'])) {
                $instance['image']['bytesBase64Encoded'] = '[base64 image omitted]';
            }
        }
        unset($instance);

        return $redacted;
    }

    private function collectVideoArtifacts(mixed $node, array &$gcsUris, array &$inlineVideos): void
    {
        if (! is_array($node)) {
            return;
        }

        if (isset($node['gcsUris']) && is_array($node['gcsUris'])) {
            foreach ($node['gcsUris'] as $uri) {
                if (is_string($uri)) {
                    $gcsUris[] = $uri;
                }
            }
        }

        if (isset($node['gcsUri']) && is_string($node['gcsUri'])) {
            $mimeType = (string) ($node['mimeType'] ?? $node['mime_type'] ?? '');
            if ($mimeType === '' || str_starts_with($mimeType, 'video/') || preg_match('/\.(mp4|mov|mpeg|mpg|avi|wmv|flv)$/i', $node['gcsUri'])) {
                $gcsUris[] = $node['gcsUri'];
            }
        }

        if (isset($node['bytesBase64Encoded']) && is_string($node['bytesBase64Encoded'])) {
            $mimeType = (string) ($node['mimeType'] ?? $node['mime_type'] ?? 'video/mp4');
            if ($mimeType === '' || str_starts_with($mimeType, 'video/')) {
                $inlineVideos[] = [
                    'data' => $node['bytesBase64Encoded'],
                    'mime_type' => $mimeType ?: 'video/mp4',
                ];
            }
        }

        foreach ($node as $child) {
            $this->collectVideoArtifacts($child, $gcsUris, $inlineVideos);
        }
    }

    private function normalizeImageMimeType(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));

        return match ($mimeType) {
            'image/jpg' => 'image/jpeg',
            'image/png', 'image/jpeg' => $mimeType,
            default => 'image/jpeg',
        };
    }

    private function sendNativeImageRequest(string $model, array $contents, array $generationConfig, string $authMode): array
    {
        // Use a shorter timeout than PHP's max_execution_time (180s) so we can
        // fail fast and return a clean error to the client instead of letting
        // PHP kill the script mid-flight when Google hangs the connection.
        $request = Http::timeout(120)->connectTimeout(10);

        if ($authMode === 'service_account') {
            $url = "https://aiplatform.googleapis.com/v1beta1/projects/{$this->projectId}/locations/global/publishers/google/models/{$model}:generateContent";
            $request = $request->withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
            ]);
        } else {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
            if (! empty($this->apiKey)) {
                $url .= "?key={$this->apiKey}";
            }
        }

        return [
            $request->post($url, [
                'contents'         => $contents,
                'generationConfig' => $generationConfig,
            ]),
            $url,
        ];
    }

    private function applyAuth($request)
    {
        if ($this->authMethod === 'service_account') {
            $accessToken = $this->getAccessToken();
            return $request->withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ]);
        }

        return $request;
    }

    private function loadServiceAccount(): void
    {
        $path = config('services.vertex_ai.credentials_path');

        // If path is empty or not found, try the default location
        if (empty($path) || ! file_exists($path)) {
            $path = storage_path('app/google/service-account.json');
        }

        // If still a relative path, resolve it from the base path
        if ($path && ! file_exists($path) && ! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:/', $path)) {
            $path = base_path($path);
        }

        if (! $path || ! file_exists($path)) {
            Log::warning('Gemini service account file not found', ['path' => $path]);
            return;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (! $data || empty($data['client_email']) || empty($data['private_key'])) {
            Log::warning('Gemini service account JSON is invalid');
            return;
        }

        $this->serviceAccount = $data;

        // Auto-detect project ID from service account if not set in env
        if (empty($this->projectId) && ! empty($data['project_id'])) {
            $this->projectId = $data['project_id'];
        }
    }

    private function getAccessToken(): string
    {
        $cacheKey = 'gemini_vertex_access_token';

        return Cache::remember($cacheKey, 3300, function () {
            return $this->requestAccessToken();
        });
    }

    private function requestAccessToken(): string
    {
        // Use Google's server time to avoid clock-skew issues
        $now = $this->getGoogleTime();
        $payload = [
            'iss'   => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $jwt = JWT::encode($payload, $this->serviceAccount['private_key'], 'RS256');

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);

        if (! $response->successful()) {
            Log::error('Failed to obtain Vertex AI access token', [
                'status' => $response->status(),
                'error'  => $response->json(),
            ]);
            throw new \RuntimeException('Failed to obtain access token for Vertex AI.');
        }

        return $response->json('access_token');
    }

    /**
     * Get the current timestamp from Google's servers to avoid clock-skew issues.
     * Falls back to local time() if the request fails.
     */
    private function getGoogleTime(): int
    {
        try {
            $response = Http::timeout(5)->head('https://www.googleapis.com');
            $dateHeader = $response->header('Date');
            if ($dateHeader) {
                $ts = strtotime($dateHeader);
                if ($ts !== false) {
                    return $ts;
                }
            }
        } catch (\Throwable $e) {
            // Silently fall back to local time
        }

        return time();
    }
}
