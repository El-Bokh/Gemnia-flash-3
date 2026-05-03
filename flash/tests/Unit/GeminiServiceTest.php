<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\GeminiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    private string $serviceAccountPath;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(Setting::CACHE_KEY);
        Cache::forget('gemini_vertex_access_token');

        $this->serviceAccountPath = storage_path('framework/testing/gemini-service-account.json');
        $directory = dirname($this->serviceAccountPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->serviceAccountPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'client_email' => 'test-service-account@example.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nTEST-KEY\n-----END PRIVATE KEY-----\n",
        ], JSON_THROW_ON_ERROR));

        config()->set('services.vertex_ai.credentials_path', $this->serviceAccountPath);
        config()->set('services.vertex_ai.project_id', 'test-project');
        config()->set('services.vertex_ai.region', 'us-central1');

        Cache::put(Setting::CACHE_KEY, [
            'gemini_auth_method' => ['typed_value' => 'service_account'],
            'gemini_text_model' => ['typed_value' => 'gemini-2.5-flash'],
            'gemini_image_model' => ['typed_value' => 'gemini-3.1-flash-image-preview'],
            'gemini_video_model' => ['typed_value' => 'veo-3.0-generate-001'],
        ], 3600);

        Cache::put('gemini_vertex_access_token', 'test-token', 3600);
    }

    protected function tearDown(): void
    {
        Cache::forget(Setting::CACHE_KEY);
        Cache::forget('gemini_vertex_access_token');

        if (isset($this->serviceAccountPath) && is_file($this->serviceAccountPath)) {
            unlink($this->serviceAccountPath);
        }

        parent::tearDown();
    }

    public function test_inpaint_with_mask_sends_explicit_reference_types_and_parses_vertex_response(): void
    {
        Http::fake([
            'https://us-central1-aiplatform.googleapis.com/*' => Http::response([
                'predictions' => [
                    [
                        '_self' => [
                            'bytesBase64Encoded' => base64_encode('generated-image-binary'),
                            'mimeType' => 'image/png',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GeminiService('image');

        $result = $service->inpaintWithMask(
            ['data' => base64_encode('source-image-binary'), 'mime_type' => 'image/jpeg'],
            ['data' => base64_encode('mask-image-binary'), 'mime_type' => 'image/png'],
            'Change the selected part to blue while keeping the rest of the image as is',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('imagen-3.0-capability-001', $result['model_used']);
        $this->assertSame('imagen', $result['engine_provider']);
        $this->assertNull($result['error']);
        $this->assertCount(1, $result['images']);
        $this->assertSame(base64_encode('generated-image-binary'), $result['images'][0]['data']);
        $this->assertSame('image/png', $result['images'][0]['mime_type']);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://us-central1-aiplatform.googleapis.com/v1/projects/test-project/locations/us-central1/publishers/google/models/imagen-3.0-capability-001:predict') {
                return false;
            }

            $payload = json_decode($request->body(), true);

            return ($request->header('Authorization')[0] ?? null) === 'Bearer test-token'
                && is_array($payload)
                && ($payload['parameters']['editMode'] ?? null) === 'EDIT_MODE_INPAINT_INSERTION'
                && ($payload['parameters']['sampleCount'] ?? null) === 1
                && ($payload['instances'][0]['referenceImages'][0]['referenceType'] ?? null) === 'REFERENCE_TYPE_RAW'
                && ($payload['instances'][0]['referenceImages'][0]['referenceImage']['mimeType'] ?? null) === 'image/jpeg'
                && ($payload['instances'][0]['referenceImages'][1]['referenceType'] ?? null) === 'REFERENCE_TYPE_MASK'
                && ($payload['instances'][0]['referenceImages'][1]['referenceImage']['mimeType'] ?? null) === 'image/png'
                && ($payload['instances'][0]['referenceImages'][1]['maskImageConfig']['maskMode'] ?? null) === 'MASK_MODE_USER_PROVIDED'
                && (float) ($payload['instances'][0]['referenceImages'][1]['maskImageConfig']['dilation'] ?? -1) === 0.025;
        });
    }

    public function test_inpaint_with_mask_can_omit_edit_mode_for_object_recolor(): void
    {
        Http::fake([
            'https://us-central1-aiplatform.googleapis.com/*' => Http::response([
                'predictions' => [
                    [
                        '_self' => [
                            'bytesBase64Encoded' => base64_encode('recolored-image-binary'),
                            'mimeType' => 'image/png',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GeminiService('image');

        $result = $service->inpaintWithMask(
            ['data' => base64_encode('source-image-binary'), 'mime_type' => 'image/jpeg'],
            ['data' => base64_encode('mask-image-binary'), 'mime_type' => 'image/png'],
            'Recolor selected real objects to red without filling the mask shape.',
            null,
            0.0,
        );

        $this->assertTrue($result['success']);

        Http::assertSent(function (Request $request): bool {
            $payload = json_decode($request->body(), true);

            return is_array($payload)
                && ! array_key_exists('editMode', $payload['parameters'] ?? [])
                && ($payload['parameters']['sampleCount'] ?? null) === 1
                && ($payload['parameters']['outputOptions']['mimeType'] ?? null) === 'image/png'
                && ($payload['instances'][0]['referenceImages'][0]['referenceType'] ?? null) === 'REFERENCE_TYPE_RAW'
                && ($payload['instances'][0]['referenceImages'][1]['referenceType'] ?? null) === 'REFERENCE_TYPE_MASK'
                && (float) ($payload['instances'][0]['referenceImages'][1]['maskImageConfig']['dilation'] ?? -1) === 0.0;
        });
    }
}