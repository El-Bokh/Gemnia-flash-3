<?php

namespace App\Services;

use App\Models\Setting;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $textModel;
    protected string $configuredImageModel;
    protected string $imageModel;
    protected string $activeModel;
    protected string $mode; // 'text' or 'image'
    protected string $authMethod; // 'api_key' or 'service_account'
    protected string $projectId;
    protected string $region;
    protected ?array $serviceAccount = null;

    public function __construct(string $mode = 'text')
    {
        $this->apiKey = Setting::getValue('gemini_api_key', '');
        $this->authMethod = Setting::getValue('gemini_auth_method', 'service_account');

        $this->textModel = Setting::getValue('gemini_text_model', 'gemini-2.5-flash');
        $this->configuredImageModel = Setting::getValue('gemini_image_model', 'gemini-3.1-flash-image-preview');
        $this->imageModel = $this->resolveImageModelAlias($this->configuredImageModel);

        $this->mode = in_array($mode, ['text', 'image', 'product']) ? $mode : 'text';
        $this->activeModel = in_array($this->mode, ['image', 'product']) ? $this->imageModel : $this->textModel;

        // Vertex AI config
        $this->projectId = config('services.vertex_ai.project_id', '');
        $this->region = config('services.vertex_ai.region', 'us-central1');

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
        } catch (\Exception $e) {
            Log::error('Gemini API exception', [
                'message'     => $e->getMessage(),
                'auth_method' => $this->authMethod,
            ]);
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'Failed to connect to Gemini: ' . $e->getMessage(),
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
                    'message' => "Connected via {$method}. Text model: {$this->textModel}, Image model: {$imageModel}. Response: " . \Illuminate\Support\Str::limit($text, 60),
                ];
            }

            return ['success' => false, 'message' => 'API error: ' . $response->json('error.message', 'Unknown error')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
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
            'nano-banana-2', 'nano banana 2', 'nano-banana-2 preview', 'nano banana 2 preview' => 'gemini-3.1-flash-image-preview',
            'nano-banana-pro', 'nano banana pro', 'nano-banana-pro preview', 'nano banana pro preview' => 'gemini-3-pro-image-preview',
            'nano-banana', 'nano banana', 'nano-banana-1', 'nano banana 1' => 'gemini-2.5-flash-image',
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

        // Match patterns like "aspect ratio 3:4", "ratio 16:9", "نسبة 3:4", or standalone "3:4"
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

        $guardrail = ' Generate only the photo itself, without any phone UI, camera interface, shutter button, status bar, timestamp, watermark, logo, text, symbols, collage frame, or decorative border.';

        return $trimmed . $guardrail;
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

        $nativePrompt = $this->sanitizeImagePrompt($rawPrompt);
        $nativePrompt = $this->enhancePromptForImageGeneration($nativePrompt, $imageGenModel);

        // Build the user parts — preserve the user prompt while blocking UI overlays.
        $userParts = [['text' => $nativePrompt]];
        foreach ($inlineImages as $imgPart) {
            $userParts[] = $imgPart;
        }

        $contents = [
            [
                'role'  => 'user',
                'parts' => $userParts,
            ],
        ];

        // IMAGE only — prevents the model from rendering textual overlays into the image.
        $generationConfig = [
            'responseModalities' => ['IMAGE'],
            'temperature'        => 1.0,
            'imageConfig'        => [
                'aspectRatio'       => $aspectRatio,
                'personGeneration'  => 'ALLOW_ALL',
                'imageSize'         => '2K',
                'imageOutputOptions' => [
                    'mimeType' => 'image/png',
                ],
            ],
        ];

        Log::info('Trying Gemini native image generation', [
            'prompt'           => $nativePrompt,
            'configured_model' => $this->configuredImageModel,
            'model'            => $imageGenModel,
            'aspect_ratio'     => $aspectRatio,
        ]);

        try {
            $request = Http::timeout(120);

            if ($this->authMethod === 'service_account' && $this->serviceAccount !== null) {
                $url = "https://aiplatform.googleapis.com/v1beta1/projects/{$this->projectId}/locations/global/publishers/google/models/{$imageGenModel}:generateContent";
                $request = $this->applyAuth($request);
            } else {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$imageGenModel}:generateContent";
                if (! empty($this->apiKey)) {
                    $url .= "?key={$this->apiKey}";
                }
            }

            $response = $request->post($url, [
                'contents'         => $contents,
                'generationConfig' => $generationConfig,
            ]);

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message', 'Unknown error');
                Log::error('Gemini native image model failed', [
                    'model'  => $imageGenModel,
                    'status' => $response->status(),
                    'error'  => $errorMsg,
                ]);

                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => "{$imageGenModel} failed: {$errorMsg}",
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
                    'model' => $imageGenModel,
                ]);

                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => "{$imageGenModel} returned no images.",
                ];
            }

            Log::info("Image generated successfully with {$imageGenModel}");
            return [
                'success' => true,
                'content' => null,
                'images'  => $images,
                'error'   => null,
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
                'error'   => "{$imageGenModel} exception: {$e->getMessage()}",
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
        $systemInstruction = <<<'SYSTEM'
You are a cinematic image prompt engineer. Transform the user's request into a vivid, detailed English prompt optimized for a photorealistic AI image generator.

RULES:
1. KEEP EVERY ELEMENT the user mentions. Never remove, minimize, or make anything "faint" or "barely visible". Every element must be prominent and clearly visible.
2. TRANSLATE non-English input to English first, then enhance.
3. ADD CINEMATIC QUALITY: Describe the scene as a cinematic 35mm film photograph with rich detail:
   - Lighting: Match the scene (golden hour, flash at night, studio, moonlight)
   - Textures: fabric weave, skin detail, sand particles, metal reflections
   - Atmosphere: mood, weather, time of day
   - Composition: camera angle, distance, framing
4. CULTURAL ACCURACY for Arabian/Middle Eastern scenes: white Thobe, red-and-white Shemagh with black Agal, traditional camel saddle
5. END with quality keywords: "photorealistic, ultra detailed, 8K, cinematic lighting, 35mm film"
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

        $prompt = $this->sanitizeImagePrompt($prompt);

        // Translate & enhance the prompt to English using the text model
        // Imagen works best with detailed English prompts
        $prompt = $this->enhancePromptForImageGeneration($prompt, $this->imageModel);

        Log::info('Trying Imagen image generation', [
            'configured_model' => $this->configuredImageModel,
            'model'            => $this->imageModel,
            'prompt'           => $prompt,
            'aspect_ratio'     => $aspectRatio,
        ]);

        $url = $this->buildEndpointUrl($this->imageModel, 'predict');

        $instance = ['prompt' => $prompt];

        // If user uploaded a reference image, include it
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
            'language'           => 'en',
        ];

        try {
            $request = Http::timeout(120);
            $request = $this->applyAuth($request);

            $response = $request->post($url, [
                'instances'  => [$instance],
                'parameters' => $parameters,
            ]);

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message', 'Unknown Imagen API error');
                Log::error('Imagen API error', [
                    'status' => $response->status(),
                    'error'  => $errorMsg,
                    'model'  => $this->imageModel,
                ]);
                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => "{$this->imageModel} failed: {$errorMsg}",
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
                    'model' => $this->imageModel,
                ]);

                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => "{$this->imageModel} returned no images.",
                ];
            }

            Log::info('Imagen image generated successfully', [
                'model'       => $this->imageModel,
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
                'model'   => $this->imageModel,
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => "{$this->imageModel} exception: {$e->getMessage()}",
            ];
        }
    }

    // ──────────────────────────────────────────────
    //  PRIVATE: Auth & Endpoint helpers
    // ──────────────────────────────────────────────

    private function checkAuth(): ?array
    {
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
        $now = time();
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
}
