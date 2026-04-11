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
        $this->imageModel = Setting::getValue('gemini_image_model', 'imagen-3.0-generate-002');

        $this->mode = in_array($mode, ['text', 'image']) ? $mode : 'text';
        $this->activeModel = $this->mode === 'image' ? $this->imageModel : $this->textModel;

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

        // Vertex AI + image mode → use Imagen predict API
        if ($this->mode === 'image' && $this->authMethod === 'service_account' && $this->isImagenModel()) {
            return $this->imagenGenerate($currentParts);
        }

        $contents = [];

        foreach ($history as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : $msg['role'];
            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => $currentParts,
        ];

        $url = $this->buildEndpointUrl($this->activeModel, 'generateContent');

        // Build generation config based on mode
        $generationConfig = [
            'temperature'     => 0.7,
            'maxOutputTokens' => 8192,
        ];

        if ($this->mode === 'image') {
            $generationConfig['responseModalities'] = ['Text', 'Image'];
        }

        $timeout = $this->mode === 'image' ? 120 : 60;

        try {
            $request = Http::timeout($timeout);
            $request = $this->applyAuth($request);

            $response = $request->post($url, [
                'contents'         => $contents,
                'generationConfig' => $generationConfig,
            ]);

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message', 'Unknown Gemini API error');
                Log::error('Gemini API error', [
                    'status'      => $response->status(),
                    'error'       => $errorMsg,
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
                return [
                    'success' => true,
                    'message' => "Connected via {$method}. Text model: {$this->textModel}, Image model: {$this->imageModel}. Response: " . \Illuminate\Support\Str::limit($text, 60),
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
     * Use the text model to translate/enhance a user prompt into a detailed
     * English image-generation prompt optimized for Imagen.
     */
    private function enhancePromptForImagen(string $userPrompt): string
    {
        try {
            $url = $this->buildEndpointUrl($this->textModel, 'generateContent');

            $request = Http::timeout(15);
            $request = $this->applyAuth($request);

            $response = $request->post($url, [
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [['text' =>
                            "Task: Translate and convert this image request into an English prompt for an image generation AI.\n\n" .
                            "Rules:\n" .
                            "1. First, accurately translate the user's words to understand EXACTLY what they want.\n" .
                            "2. Keep the SAME subject — if they say galaxy, write galaxy. If they say cat, write cat. NEVER change the subject.\n" .
                            "3. Add descriptive details: lighting, composition, style, quality.\n" .
                            "4. Do NOT add people/humans unless the user explicitly asks for them.\n" .
                            "5. Output ONLY the prompt in one short paragraph (max 2 sentences). No quotes, no explanation.\n\n" .
                            "User request: {$userPrompt}"
                        ]],
                    ],
                ],
                'generationConfig' => [
                    'temperature'     => 0.2,
                    'maxOutputTokens' => 200,
                    'thinkingConfig'  => ['thinkingBudget' => 0],
                ],
            ]);

            if ($response->successful()) {
                $enhanced = trim($response->json('candidates.0.content.parts.0.text', ''));
                if (! empty($enhanced)) {
                    return $enhanced;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Prompt enhancement failed, using original', ['error' => $e->getMessage()]);
        }

        // Fallback: return original prompt if enhancement fails
        return $userPrompt;
    }

    private function imagenGenerate(array $currentParts): array
    {
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

        // Translate & enhance the prompt to English using the text model
        // Imagen works best with detailed English prompts
        $prompt = $this->enhancePromptForImagen($prompt);

        Log::info('Imagen final prompt', ['prompt' => $prompt]);

        $url = $this->buildEndpointUrl($this->imageModel, 'predict');

        $instance = ['prompt' => $prompt];

        // If user uploaded a reference image, include it
        if ($referenceImage) {
            $instance['image'] = [
                'bytesBase64Encoded' => $referenceImage['data'],
            ];
        }

        $parameters = [
            'sampleCount' => 1,
            'aspectRatio' => '1:1',
            'personGeneration' => 'allow_all',
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
                    'error'   => $errorMsg,
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
                return [
                    'success' => false,
                    'content' => null,
                    'images'  => [],
                    'error'   => 'Imagen returned no images.',
                ];
            }

            return [
                'success' => true,
                'content' => null,
                'images'  => $images,
                'error'   => null,
            ];
        } catch (\Exception $e) {
            Log::error('Imagen API exception', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'Failed to generate image: ' . $e->getMessage(),
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
