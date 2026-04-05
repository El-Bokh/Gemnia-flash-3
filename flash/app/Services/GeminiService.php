<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $textModel;
    protected string $imageModel;
    protected string $activeModel;
    protected string $mode; // 'text' or 'image'

    public function __construct(string $mode = 'text')
    {
        $this->apiKey = Setting::getValue('gemini_api_key', '');

        $this->textModel = Setting::getValue('gemini_text_model', 'gemini-2.5-flash');
        $this->imageModel = Setting::getValue('gemini_image_model', 'gemini-3.1-flash-image-preview');

        $this->mode = in_array($mode, ['text', 'image']) ? $mode : 'text';
        $this->activeModel = $this->mode === 'image' ? $this->imageModel : $this->textModel;
    }

    public function getModel(): string
    {
        return $this->activeModel;
    }

    public function getMode(): string
    {
        return $this->mode;
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
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'content' => null,
                'images'  => [],
                'error'   => 'Gemini API key is not configured.',
            ];
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

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->activeModel}:generateContent?key={$this->apiKey}";

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
            $response = Http::timeout($timeout)->post($url, [
                'contents'         => $contents,
                'generationConfig' => $generationConfig,
            ]);

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message', 'Unknown Gemini API error');
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'error'  => $errorMsg,
                    'model'  => $this->activeModel,
                    'mode'   => $this->mode,
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
            Log::error('Gemini API exception', ['message' => $e->getMessage()]);
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
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'Gemini API key is not configured.'];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->textModel}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(10)->post($url, [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'Hi, respond with OK']]],
                ],
            ]);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text', '');
                return ['success' => true, 'message' => "Connected successfully. Text model: {$this->textModel}, Image model: {$this->imageModel}. Response: " . \Illuminate\Support\Str::limit($text, 60)];
            }

            return ['success' => false, 'message' => 'API error: ' . $response->json('error.message', 'Unknown error')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }
}
