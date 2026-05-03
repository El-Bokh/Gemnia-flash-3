<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\StartVeoVideoGeneration;
use App\Models\AiRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\CreditLedger;
use App\Models\MediaFile;
use App\Models\Product;
use App\Models\VisualStyle;
use App\Services\GeminiService;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    /**
     * GET /api/conversations
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()
            ->conversations()
            ->with('messages')
            ->latest('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $conversations,
        ]);
    }

    /**
     * POST /api/conversations
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
        ]);

        $conversation = $request->user()->conversations()->create([
            'title' => $data['title'] ?? 'New Chat',
        ]);

        $conversation->load('messages');

        return response()->json([
            'success' => true,
            'data'    => $conversation,
        ], 201);
    }

    /**
     * GET /api/conversations/{conversation}
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $conversation->load('messages'),
        ]);
    }

    /**
     * PUT /api/conversations/{conversation}
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'title'  => ['sometimes', 'string', 'max:255'],
            'pinned' => ['sometimes', 'boolean'],
        ]);

        $conversation->update($data);

        return response()->json([
            'success' => true,
            'data'    => $conversation,
        ]);
    }

    /**
     * DELETE /api/conversations/{conversation}
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted.',
        ]);
    }

    /**
     * POST /api/conversations/{conversation}/messages
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        try {
            return $this->processSendMessage($request, $conversation);
        } catch (\Throwable $e) {
            Log::error('sendMessage unhandled error', [
                'conversation_id' => $conversation->id,
                'user_id'         => $request->user()->id,
                'error'           => $e->getMessage(),
                'file'            => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/conversations/{conversation}/inpaint
     */
    public function inpaintImage(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'image' => ['required', 'image', 'max:10240'],
            'mask_image' => ['required', 'image', 'max:5120'],
            'rendered_image' => ['nullable', 'image', 'max:10240'],
            'source_message_id' => ['nullable', 'integer'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,3:4,4:3,9:16,16:9'],
        ]);

        $sourceMessage = null;
        if (! empty($data['source_message_id'])) {
            $sourceMessage = $conversation->messages()
                ->where('id', $data['source_message_id'])
                ->whereNotNull('image_url')
                ->first();

            if (! $sourceMessage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Source image message was not found.',
                ], 422);
            }
        }

        $usageService = new UsageService();
        $featureSlug = 'inpainting';
        $featureCheck = $usageService->checkFeatureLimit($request->user(), $featureSlug);

        if (! $featureCheck['allowed']) {
            $messages = [
                'no_subscription'       => 'You need an active subscription to use this feature.',
                'feature_not_available' => 'This feature is not available in your current plan. Please upgrade.',
                'feature_disabled'      => 'This feature is not available in your current plan. Please upgrade.',
                'feature_limit_reached' => "You've reached your {$featureCheck['period']} limit for this feature ({$featureCheck['used']}/{$featureCheck['limit']}). Please upgrade or wait.",
            ];

            return response()->json([
                'success'    => false,
                'message'    => $messages[$featureCheck['reason']] ?? 'Feature limit reached.',
                'error_code' => $featureCheck['reason'],
                'quota'      => [
                    'remaining'     => 0,
                    'feature_used'  => $featureCheck['used'],
                    'feature_limit' => $featureCheck['limit'],
                    'feature_period'=> $featureCheck['period'],
                ],
            ], 402);
        }

        $featureModel = \App\Models\Feature::where('slug', $featureSlug)->first();
        $creditCost = $featureCheck['credits_per_use'] ?? 1;
        $consumption = $usageService->consume($request->user(), $creditCost, 'inpainting', [
            'feature_id' => $featureModel?->id,
        ]);

        if (! $consumption['success']) {
            $code = $consumption['reason'] === 'no_subscription' ? 'no_subscription' : 'insufficient_credits';

            return response()->json([
                'success' => false,
                'message' => $consumption['reason'] === 'no_subscription'
                    ? 'You need an active subscription to use this feature.'
                    : 'Your credits have been exhausted. Please upgrade your plan.',
                'error_code' => $code,
                'quota' => ['remaining' => $consumption['remaining']],
            ], 402);
        }

        $imageFile = $request->file('image');
        $maskFile = $request->file('mask_image');
        $renderedImageFile = $request->file('rendered_image');

        $sourcePath = $imageFile->store('chat-inpainting/' . $request->user()->id, 'public');
        $maskPath = $maskFile->store('chat-inpainting-masks/' . $request->user()->id, 'public');
        $sourceUrl = '/storage/' . $sourcePath;
        $maskUrl = '/storage/' . $maskPath;

        MediaFile::create([
            'user_id'       => $request->user()->id,
            'file_name'     => basename($sourcePath),
            'original_name' => $imageFile->getClientOriginalName(),
            'file_path'     => $sourcePath,
            'disk'          => 'public',
            'mime_type'     => $imageFile->getMimeType(),
            'file_size'     => $imageFile->getSize(),
            'collection'    => 'chat',
            'purpose'       => 'input',
        ]);

        MediaFile::create([
            'user_id'       => $request->user()->id,
            'file_name'     => basename($maskPath),
            'original_name' => $maskFile->getClientOriginalName(),
            'file_path'     => $maskPath,
            'disk'          => 'public',
            'mime_type'     => $maskFile->getMimeType(),
            'file_size'     => $maskFile->getSize(),
            'collection'    => 'chat',
            'purpose'       => 'mask',
        ]);

        $preparedSource = $this->prepareImageForApi($imageFile->getRealPath(), $imageFile->getMimeType() ?: 'image/jpeg');
        $preparedMask = $this->prepareImageForApi($maskFile->getRealPath(), $maskFile->getMimeType() ?: 'image/png');
        $aspectRatio = $data['aspect_ratio'] ?? $this->inferAspectRatioFromImage($imageFile->getRealPath());
        $userPrompt = trim($data['content']);
        $inpaintPrompt = $this->buildInpaintingPrompt($userPrompt);
        $useSemanticObjectRecolor = $this->isObjectAwareRecolorPrompt($userPrompt);
        $activeInpaintPrompt = $useSemanticObjectRecolor
            ? $this->buildObjectAwareRecolorPrompt($userPrompt)
            : $inpaintPrompt;

        $userMsg = $conversation->messages()->create([
            'role'       => 'user',
            'content'    => $userPrompt,
            'image_url'  => $sourceUrl,
            'status'     => 'sent',
            'metadata'   => [
                'edit_mode' => 'inpainting',
                'source_message_id' => $sourceMessage?->id,
                'mask_image_url' => $maskUrl,
            ],
        ]);

        if ($conversation->messages()->where('role', 'user')->count() === 1) {
            $conversation->update([
                'title' => Str::limit($userPrompt, 40),
            ]);
        }

        $conversation->touch();

        $currentParts = [
            ['text' => $activeInpaintPrompt],
            [
                'inline_data' => [
                    'mime_type' => $preparedSource['mime_type'],
                    'data' => $preparedSource['data'],
                ],
            ],
            [
                'inline_data' => [
                    'mime_type' => $preparedMask['mime_type'],
                    'data' => $preparedMask['data'],
                ],
            ],
        ];

        $gemini = new GeminiService('image', $aspectRatio);
        $processedPrompt = $activeInpaintPrompt;
        $modelUsed = $gemini->getModel();
        $engineProvider = 'gemini';
        $localRenderedImageFile = $useSemanticObjectRecolor && $renderedImageFile ? $renderedImageFile : null;
        $maskDilation = $useSemanticObjectRecolor ? 0.0 : 0.025;
        $startedAt = now();

        if ($localRenderedImageFile) {
            $result = [
                'success' => true,
                'content' => null,
                'images' => [],
                'error' => null,
            ];
            $modelUsed = 'browser-canvas-recolor';
            $engineProvider = 'local';
        } elseif ($gemini->getAuthMethod() === 'service_account') {
            $result = $useSemanticObjectRecolor
                ? $gemini->inpaintWithMask($preparedSource, $preparedMask, $activeInpaintPrompt, null, 0.0)
                : $gemini->inpaintWithMask($preparedSource, $preparedMask, $activeInpaintPrompt, 'EDIT_MODE_INPAINT_INSERTION', $maskDilation);

            if ($result['success']) {
                $processedPrompt = $result['prompt_used'] ?? $processedPrompt;
                $modelUsed = $result['model_used'] ?? $modelUsed;
                $engineProvider = $result['engine_provider'] ?? $engineProvider;
            } else {
                Log::warning('Explicit-mask inpainting failed, falling back to Gemini native image editing', [
                    'user_id' => $request->user()->id,
                    'conversation_id' => $conversation->id,
                    'error' => $result['error'] ?? 'Unknown error',
                    'edit_strategy' => $useSemanticObjectRecolor ? 'object_aware_recolor' : 'masked_inpainting',
                ]);

                $result = $gemini->chatWithParts([], $currentParts);
            }
        } else {
            $result = $gemini->chatWithParts([], $currentParts);
        }

        $completedAt = now();
        $processingTimeMs = (int) round($startedAt->diffInMilliseconds($completedAt));

        $aiRequest = AiRequest::create([
            'user_id'            => $request->user()->id,
            'subscription_id'    => $consumption['subscription']?->id,
            'type'               => 'inpainting',
            'status'             => $result['success'] ? 'completed' : 'failed',
            'user_prompt'        => $userPrompt,
            'processed_prompt'   => $processedPrompt,
            'model_used'         => $modelUsed,
            'engine_provider'    => $engineProvider,
            'credits_consumed'   => $creditCost,
            'input_image_path'   => $sourceUrl,
            'mask_image_path'    => $maskUrl,
            'processing_time_ms' => $processingTimeMs,
            'ip_address'         => $request->ip(),
            'user_agent'         => $request->userAgent(),
            'request_payload'    => [
                'prompt' => $processedPrompt,
                'has_source_image' => true,
                'has_mask_image' => true,
                'source_message_id' => $sourceMessage?->id,
                'mask_transport' => $engineProvider === 'local' ? 'local_canvas' : ($engineProvider === 'imagen' ? 'explicit_mask' : 'semantic_mask'),
                'edit_strategy' => $engineProvider === 'local' ? 'local_mask_recolor' : ($useSemanticObjectRecolor ? 'object_aware_recolor' : 'masked_inpainting'),
                'mask_dilation' => $maskDilation,
            ],
            'metadata'           => [
                'source' => 'conversation',
                'generation_mode' => 'inpainting',
                'user_message_id' => $userMsg->id,
                'source_message_id' => $sourceMessage?->id,
                'engine_provider' => $engineProvider,
                'edit_strategy' => $engineProvider === 'local' ? 'local_mask_recolor' : ($useSemanticObjectRecolor ? 'object_aware_recolor' : 'masked_inpainting'),
            ],
            'error_message'      => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
            'started_at'         => $startedAt,
            'completed_at'       => $completedAt,
        ]);

        if ($result['success']) {
            $generatedImageUrl = null;

            if ($localRenderedImageFile) {
                $mimeType = $localRenderedImageFile->getMimeType() ?: 'image/png';
                $ext = str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg') ? 'jpg' : 'png';
                $fileName = 'ai-generated/' . $request->user()->id . '/' . Str::uuid() . '.' . $ext;
                $imageBytes = file_get_contents($localRenderedImageFile->getRealPath());

                Storage::disk('public')->put($fileName, $imageBytes);

                MediaFile::create([
                    'user_id'       => $request->user()->id,
                    'file_name'     => basename($fileName),
                    'original_name' => 'recolored-image.' . $ext,
                    'file_path'     => $fileName,
                    'disk'          => 'public',
                    'mime_type'     => $mimeType,
                    'file_size'     => strlen($imageBytes),
                    'collection'    => 'chat',
                    'purpose'       => 'output',
                ]);

                $generatedImageUrl = '/storage/' . $fileName;

                $aiRequest->update([
                    'output_image_path' => $generatedImageUrl,
                ]);
            } elseif (! empty($result['images'])) {
                $firstImage = $result['images'][0];
                $ext = str_contains($firstImage['mime_type'], 'png') ? 'png' : 'jpg';
                $fileName = 'ai-generated/' . $request->user()->id . '/' . Str::uuid() . '.' . $ext;
                $imageBytes = base64_decode($firstImage['data']);

                Storage::disk('public')->put($fileName, $imageBytes);

                MediaFile::create([
                    'user_id'       => $request->user()->id,
                    'file_name'     => basename($fileName),
                    'original_name' => 'inpainted-image.' . $ext,
                    'file_path'     => $fileName,
                    'disk'          => 'public',
                    'mime_type'     => $firstImage['mime_type'],
                    'file_size'     => strlen($imageBytes),
                    'collection'    => 'chat',
                    'purpose'       => 'output',
                ]);

                $generatedImageUrl = '/storage/' . $fileName;

                $aiRequest->update([
                    'output_image_path' => $generatedImageUrl,
                ]);
            }

            $aiMsg = $conversation->messages()->create([
                'ai_request_id' => $aiRequest->id,
                'role'          => 'assistant',
                'content'       => $result['content'] ?? ($generatedImageUrl ? '' : 'No response generated.'),
                'image_url'     => $generatedImageUrl,
                'status'        => 'sent',
                'metadata'      => [
                    'edit_mode' => 'inpainting',
                    'source_message_id' => $sourceMessage?->id,
                    'mask_image_url' => $maskUrl,
                    'source_image_url' => $sourceUrl,
                    'edit_strategy' => $engineProvider === 'local' ? 'local_mask_recolor' : ($useSemanticObjectRecolor ? 'object_aware_recolor' : 'masked_inpainting'),
                ],
            ]);
        } else {
            $usageService->refund($request->user(), $creditCost, 'ai_failure', [
                'ai_request_id' => $aiRequest->id,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
            $consumption['remaining'] += $creditCost;
            $aiRequest->update(['credits_consumed' => 0]);

            $aiMsg = $conversation->messages()->create([
                'ai_request_id' => $aiRequest->id,
                'role'          => 'assistant',
                'content'       => $result['error'] ?? 'Image inpainting failed.',
                'status'        => 'error',
                'metadata'      => [
                    'edit_mode' => 'inpainting',
                    'source_message_id' => $sourceMessage?->id,
                    'mask_image_url' => $maskUrl,
                    'source_image_url' => $sourceUrl,
                ],
            ]);
        }

        $responseConversation = $conversation->fresh()->load('messages');
        $quotaStats = $usageService->computeWarningLevel(
            $consumption['remaining'],
            $consumption['subscription']->credits_total ?? 0
        );

        return response()->json([
            'success' => true,
            'data' => [
                'user_message' => $userMsg,
                'ai_message' => $aiMsg,
                'conversation' => $responseConversation,
            ],
            'quota' => [
                'remaining' => $consumption['remaining'],
                'warning' => $quotaStats,
            ],
        ]);
    }

    private function processSendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'content'     => ['required', 'string', 'max:5000'],
            'image_style' => ['nullable', 'string', 'max:100'],
            'product'     => ['nullable', 'string', 'max:100'],
            'image'       => ['nullable', 'image', 'max:10240'], // 10MB
            'product_reference_sheet' => ['nullable', 'image', 'max:10240'],
            'images'      => ['nullable', 'array', 'max:10'],
            'images.*'    => ['image', 'max:10240'],
            'mode'        => ['nullable', 'string', 'in:text,image,product,video'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,3:4,4:3,9:16,16:9'],
            'duration_seconds' => ['nullable', 'integer', 'in:4,6,8'],
            'resolution' => ['nullable', 'string', 'in:720p,1080p'],
            'generate_audio' => ['nullable', 'boolean'],
        ]);

        $mode = $data['mode'] ?? 'text';
        $imageFollowUpContext = null;
        $videoFollowUpContext = null;
        $videoOptions = null;
        $productSlug = $data['product'] ?? null;
        $product = null;
        $productTemplateImageUrl = null;

        if ($productSlug) {
            $product = Product::where('slug', $productSlug)->where('is_active', true)->first();
            $productTemplateImageUrl = $this->productTemplateImageUrl($product);
            $mode = $this->normalizeModeForProductTemplate($mode, $product);
        }

        if ($mode === 'video') {
            $videoAspectRatio = $data['aspect_ratio'] ?? '16:9';
            if (! in_array($videoAspectRatio, ['16:9', '9:16'], true)) {
                $videoAspectRatio = '16:9';
            }

            $videoOptions = [
                'duration_seconds' => (int) ($data['duration_seconds'] ?? 8),
                'aspect_ratio' => $videoAspectRatio,
                'resolution' => $data['resolution'] ?? '720p',
                'generate_audio' => $request->boolean('generate_audio', true),
            ];
        }

        if ($mode === 'image') {
            if (! $product && $this->shouldTreatImagePromptAsText($data['content'])) {
                $mode = 'text';
            } elseif (! $product && ! $request->hasFile('image') && ! $request->hasFile('images')) {
                $imageFollowUpContext = $this->resolveImageFollowUpContext(
                    $conversation,
                    $request->user()->id,
                    $data['content'],
                );
            }
        }

        if ($mode === 'video' && ! $request->hasFile('image')) {
            $videoFollowUpContext = $this->resolveVideoFollowUpContext(
                $conversation,
                $request->user()->id,
                $data['content'],
            );
        }

        $hasReferenceInput = $request->hasFile('image')
            || $request->hasFile('product_reference_sheet')
            || $request->hasFile('images')
            || $productTemplateImageUrl !== null
            || ! empty($imageFollowUpContext['reference_image_url'])
            || ! empty($videoFollowUpContext['reference_image_url']);

        // ── Quota enforcement (concurrency-safe) ──
        $usageService = new UsageService();

        // Check feature-level limits first
        $featureSlug = $this->resolveFeatureSlug($mode, $hasReferenceInput);
        $featureCheck = $usageService->checkFeatureLimit($request->user(), $featureSlug);

        if (! $featureCheck['allowed']) {
            $messages = [
                'no_subscription'       => 'You need an active subscription to use this feature.',
                'feature_not_available' => 'This feature is not available in your current plan. Please upgrade.',
                'feature_disabled'      => 'This feature is not available in your current plan. Please upgrade.',
                'feature_limit_reached' => "You've reached your {$featureCheck['period']} limit for this feature ({$featureCheck['used']}/{$featureCheck['limit']}). Please upgrade or wait.",
            ];

            return response()->json([
                'success'    => false,
                'message'    => $messages[$featureCheck['reason']] ?? 'Feature limit reached.',
                'error_code' => $featureCheck['reason'],
                'quota'      => [
                    'remaining'     => 0,
                    'feature_used'  => $featureCheck['used'],
                    'feature_limit' => $featureCheck['limit'],
                    'feature_period'=> $featureCheck['period'],
                ],
            ], 402);
        }

        if ($mode === 'video' && $videoOptions !== null) {
            $constraints = $this->normalizeFeatureConstraints($featureCheck['constraints'] ?? []);
            $maxDuration = (int) ($constraints['max_duration_seconds'] ?? 0);
            $maxResolution = (string) ($constraints['max_resolution'] ?? '');

            if ($maxDuration > 0 && $videoOptions['duration_seconds'] > $maxDuration) {
                return response()->json([
                    'success' => false,
                    'message' => "Your plan supports video generation up to {$maxDuration} seconds.",
                    'error_code' => 'video_duration_not_allowed',
                ], 402);
            }

            if ($maxResolution === '720p' && $videoOptions['resolution'] === '1080p') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your plan supports video generation up to 720p resolution.',
                    'error_code' => 'video_resolution_not_allowed',
                ], 402);
            }
        }

        $featureModel = \App\Models\Feature::where('slug', $featureSlug)->first();
        $creditCost = $featureCheck['credits_per_use'] ?? 1;
        $consumption = $usageService->consume($request->user(), $creditCost, 'chat_message', [
            'feature_id' => $featureModel?->id,
        ]);

        if (! $consumption['success']) {
            $code    = $consumption['reason'] === 'no_subscription' ? 'no_subscription' : 'insufficient_credits';
            $message = $consumption['reason'] === 'no_subscription'
                ? 'You need an active subscription to use this feature.'
                : 'Your credits have been exhausted. Please upgrade your plan.';

            return response()->json([
                'success' => false,
                'message' => $message,
                'error_code'  => $code,
                'quota'   => [
                    'remaining' => $consumption['remaining'],
                ],
            ], 402);
        }

        // Handle uploaded image
        $imageUrl = null;
        $imageStoragePath = null;
        $imageBase64 = null;
        $imageMime = null;
        $clientProductReferenceSheet = null;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = $file->store('chat-uploads/' . $request->user()->id, 'public');

            MediaFile::create([
                'user_id'       => $request->user()->id,
                'file_name'     => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'file_path'     => $path,
                'disk'          => 'public',
                'mime_type'     => $file->getMimeType(),
                'file_size'     => $file->getSize(),
                'collection'    => 'chat',
                'purpose'       => 'input',
            ]);

            $imageUrl = '/storage/' . $path;
            $imageStoragePath = $path;
            $preparedImage = $this->prepareImageForApi($file->getRealPath(), $file->getMimeType() ?: 'image/jpeg');
            $imageBase64 = $preparedImage['data'];
            $imageMime = $preparedImage['mime_type'];
        }

        if ($request->hasFile('product_reference_sheet')) {
            $referenceSheetFile = $request->file('product_reference_sheet');
            $preparedSheet = $this->prepareImageForApi($referenceSheetFile->getRealPath(), $referenceSheetFile->getMimeType() ?: 'image/jpeg');
            if ($preparedSheet['data'] !== '') {
                $clientProductReferenceSheet = [
                    'mime_type' => $preparedSheet['mime_type'],
                    'data' => $preparedSheet['data'],
                ];
            }
        }

        // Handle multiple product images
        $productImagesData = [];
        if ($mode === 'product' && $request->hasFile('images')) {
            $uploadedImages = $request->file('images');
            foreach ($uploadedImages as $file) {
                $path = $file->store('chat-uploads/' . $request->user()->id, 'public');

                MediaFile::create([
                    'user_id'       => $request->user()->id,
                    'file_name'     => basename($path),
                    'original_name' => $file->getClientOriginalName(),
                    'file_path'     => $path,
                    'disk'          => 'public',
                    'mime_type'     => $file->getMimeType(),
                    'file_size'     => $file->getSize(),
                    'collection'    => 'chat',
                    'purpose'       => 'input',
                ]);

                // Resize for Gemini API to avoid payload-too-large errors
                $preparedImage = $this->prepareImageForApi($file->getRealPath(), $file->getMimeType() ?: 'image/jpeg');

                $productImagesData[] = [
                    'url'          => '/storage/' . $path,
                    'storage_path' => $path,
                    'base64'       => $preparedImage['data'],
                    'mime'         => $preparedImage['mime_type'],
                ];
            }

            // Use first image URL for the message display
            if (! empty($productImagesData)) {
                $imageUrl = $productImagesData[0]['url'];
            }
        }

        // Collect all product image URLs
        $productImageUrls = ! empty($productImagesData)
            ? array_map(fn ($img) => $img['url'], $productImagesData)
            : null;

        // Store user message
        $userMsg = $conversation->messages()->create([
            'role'           => 'user',
            'content'        => $data['content'],
            'image_url'      => $imageUrl,
            'image_style'    => $data['image_style'] ?? null,
            'product_images' => $productImageUrls,
            'status'         => 'sent',
        ]);

        // Auto-title from first user message
        if ($conversation->messages()->where('role', 'user')->count() === 1) {
            $conversation->update([
                'title' => Str::limit($data['content'], 40),
            ]);
        }

        $conversation->touch();

        // Build the prompt — inject hidden base + style prompts
        $userContent = $data['content'];
        $styleSlug = $data['image_style'] ?? null;
        $baseImagePrompt = $imageFollowUpContext['prompt'] ?? $userContent;
        $geminiPrompt = $userContent;
        $style = null;

        if ($mode === 'image') {
            $parts = [];

            // Inject hidden style prompt if a style is selected
            if ($styleSlug) {
                $style = VisualStyle::where('slug', $styleSlug)->where('is_active', true)->first();
                if ($style && $style->prompt_prefix) {
                    $parts[] = $style->prompt_prefix;
                }
            }

            // Inject product hidden_prompt if a product is selected
            if ($product) {
                $parts[] = $this->buildProductTemplatePrompt(
                    $product,
                    $baseImagePrompt,
                    $imageBase64 !== null || ! empty($productImagesData),
                    $productTemplateImageUrl !== null,
                );
            } else {
                // User prompt (keep it clean - enhancePromptForImagen will handle quality keywords)
                $parts[] = $baseImagePrompt;
            }

            // Style suffix (if any)
            if ($style && $style->prompt_suffix) {
                $parts[] = $style->prompt_suffix;
            }

            // Product negative prompt (append as negative instruction)
            if ($product && $product->negative_prompt) {
                $parts[] = 'Avoid: ' . $product->negative_prompt;
            }

            $geminiPrompt = implode(', ', $parts);
        } elseif ($mode === 'product' && $product) {
            $geminiPrompt = $this->buildProductTemplatePrompt(
                $product,
                $userContent,
                ! empty($productImagesData),
                $productTemplateImageUrl !== null,
            );
        } elseif ($styleSlug) {
            // Text mode with style — just wrap with prefix/suffix
            $style = VisualStyle::where('slug', $styleSlug)->where('is_active', true)->first();
            if ($style) {
                $parts = [];
                if ($style->prompt_prefix) {
                    $parts[] = $style->prompt_prefix;
                }
                // Inject product hidden_prompt in text mode too
                if ($product && $product->hidden_prompt) {
                    $parts[] = $product->hidden_prompt;
                }
                $parts[] = $userContent;
                if ($style->prompt_suffix) {
                    $parts[] = $style->prompt_suffix;
                }
                $geminiPrompt = implode("\n\n", $parts);
            }
        } elseif ($product && $product->hidden_prompt) {
            // Product only (no style) — inject hidden_prompt
            $geminiPrompt = $product->hidden_prompt . "\n\n" . $userContent;
        }

        // Build conversation history for Gemini — include recent images for multimodal context
        $historyMessages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->where('id', '<', $userMsg->id)
            ->orderBy('created_at')
            ->get();

        // When the current request already includes a reference image, do not also
        // attach older inline images from history. That inflates payload size and
        // has been triggering Google 417 anti-automation responses on follow-ups.
        $shouldAttachRecentHistoryImages = ! (
            $mode === 'image'
            && ($imageBase64 !== null || $imageFollowUpContext !== null)
        );

        $recentImageMsgIds = $shouldAttachRecentHistoryImages
            ? $historyMessages
                ->filter(fn ($m) => $m->role === 'user' && ($m->image_url || $m->product_images))
                ->sortByDesc('id')
                ->take(3)
                ->pluck('id')
                ->toArray()
            : [];

        $history = [];
        foreach ($historyMessages as $m) {
            $parts = [['text' => $m->content ?? '']];

            // Include images from recent user messages so follow-ups have visual context
            if (in_array($m->id, $recentImageMsgIds)) {
                $imagePaths = [];
                if ($m->product_images && is_array($m->product_images)) {
                    foreach ($m->product_images as $url) {
                        $imagePaths[] = str_replace('/storage/', '', $url);
                    }
                } elseif ($m->image_url) {
                    $imagePaths[] = str_replace('/storage/', '', $m->image_url);
                }

                foreach ($imagePaths as $imgPath) {
                    $fullPath = Storage::disk('public')->path($imgPath);
                    if (file_exists($fullPath)) {
                        $preparedImage = $this->prepareImageForApi($fullPath, mime_content_type($fullPath) ?: 'image/jpeg');
                        $parts[] = [
                            'inline_data' => [
                                'mime_type' => $preparedImage['mime_type'],
                                'data'      => $preparedImage['data'],
                            ],
                        ];
                    }
                }
            }

            $history[] = [
                'role'    => $m->role,
                'content' => $m->content ?? '',
                'parts'   => $parts,
            ];
        }

        // Add current message with enhanced prompt
        $currentParts = [['text' => $geminiPrompt]];
        $productReferenceSheet = $product ? $clientProductReferenceSheet : null;
        $productReferenceSheetSource = $productReferenceSheet !== null ? 'client_combined_sheet' : null;
        $productReferencePaths = array_values(array_filter(array_merge(
            $imageStoragePath ? [$imageStoragePath] : [],
            array_map(fn ($img) => $img['storage_path'] ?? null, $productImagesData),
        )));

        if ($productReferenceSheet === null && $product && $productTemplateImageUrl !== null && ! empty($productReferencePaths)) {
            $productReferenceSheet = $this->buildProductTemplateReferenceSheet($productReferencePaths, $productTemplateImageUrl);
            $productReferenceSheetSource = $productReferenceSheet !== null ? 'server_combined_sheet' : null;
        }

        // If user uploaded an image, include it as inline data for Gemini multimodal
        if ($productReferenceSheet !== null) {
            $currentParts[] = [
                'inline_data' => [
                    'mime_type' => $productReferenceSheet['mime_type'],
                    'data'      => $productReferenceSheet['data'],
                ],
            ];
        } elseif ($imageBase64 && $imageMime) {
            $currentParts[] = [
                'inline_data' => [
                    'mime_type' => $imageMime,
                    'data'      => $imageBase64,
                ],
            ];
        }

        // Product mode: include all product images as inline data
        if ($productReferenceSheet === null && $mode === 'product' && ! empty($productImagesData)) {
            foreach ($productImagesData as $pImg) {
                $currentParts[] = [
                    'inline_data' => [
                        'mime_type' => $pImg['mime'],
                        'data'      => $pImg['base64'],
                    ],
                ];
            }
        }

        if ($productReferenceSheet === null && in_array($mode, ['image', 'product'], true) && $productTemplateImageUrl !== null) {
            $this->appendInlineImageFromUrl($currentParts, $productTemplateImageUrl);
        }

        if (
            $mode === 'image'
            && ! $imageBase64
            && ! $imageMime
            && ! empty($imageFollowUpContext['reference_image_url'])
        ) {
            $this->appendInlineImageFromUrl($currentParts, $imageFollowUpContext['reference_image_url']);
        }

        if ($mode === 'video') {
            $videoPrompt = $videoFollowUpContext['prompt'] ?? $geminiPrompt;
            $videoReferenceImageUrl = $imageUrl ?: ($videoFollowUpContext['reference_image_url'] ?? null);
            $gemini = new GeminiService('video', $videoOptions['aspect_ratio'] ?? '16:9');
            $aiRequest = AiRequest::create([
                'user_id'          => $request->user()->id,
                'subscription_id'  => $consumption['subscription']?->id,
                'visual_style_id'  => isset($style) ? $style->id : null,
                'product_id'       => $product?->id,
                'type'             => $this->resolveAiRequestType($mode, $styleSlug, $imageBase64 !== null, $videoReferenceImageUrl !== null),
                'status'           => 'pending',
                'user_prompt'      => $userContent,
                'processed_prompt' => $videoPrompt !== $userContent ? $videoPrompt : null,
                'hidden_prompt'    => $product?->hidden_prompt,
                'negative_prompt'  => $product?->negative_prompt,
                'model_used'       => $gemini->getModel(),
                'engine_provider'  => 'vertex_ai',
                'credits_consumed' => $creditCost,
                'input_image_path' => $videoReferenceImageUrl,
                'ip_address'       => $request->ip(),
                'user_agent'       => $request->userAgent(),
                'request_payload'  => [
                    'prompt' => $videoPrompt,
                    'parameters' => $videoOptions,
                    'has_reference_image' => $videoReferenceImageUrl !== null,
                    'reference_ai_request_id' => $videoFollowUpContext['reference_ai_request_id'] ?? null,
                    'reference_message_id' => $videoFollowUpContext['reference_message_id'] ?? null,
                ],
                'metadata'         => [
                    'source' => 'conversation',
                    'generation_mode' => $videoReferenceImageUrl ? 'image_to_video' : 'text_to_video',
                    'video_options' => $videoOptions,
                    'user_message_id' => $userMsg->id,
                    'reference_ai_request_id' => $videoFollowUpContext['reference_ai_request_id'] ?? null,
                    'reference_message_id' => $videoFollowUpContext['reference_message_id'] ?? null,
                    'reference_source' => $videoFollowUpContext['source'] ?? null,
                    'source_video_url' => $videoFollowUpContext['source_video_url'] ?? null,
                    'source_image_url' => $videoReferenceImageUrl,
                ],
            ]);

            $aiMsg = $conversation->messages()->create([
                'ai_request_id' => $aiRequest->id,
                'role'          => 'assistant',
                'content'       => '',
                'status'        => 'processing',
                'metadata'      => [
                    'ai_request_uuid' => $aiRequest->uuid,
                    'video_options' => $videoOptions,
                ],
            ]);

            StartVeoVideoGeneration::dispatch($aiRequest->id, $aiMsg->id);

            $responseConversation = $conversation->fresh()->load('messages');
            $quotaStats = $usageService->computeWarningLevel(
                $consumption['remaining'],
                $consumption['subscription']->credits_total ?? 0
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'user_message' => $userMsg,
                    'ai_message'   => $aiMsg,
                    'conversation' => $responseConversation,
                ],
                'quota' => [
                    'remaining' => $consumption['remaining'],
                    'warning'   => $quotaStats,
                ],
            ], 202);
        }

        $gemini = new GeminiService($mode, $data['aspect_ratio'] ?? null);
        $startedAt = now();
        $result = $gemini->chatWithParts($history, $currentParts);
        $completedAt = now();
        $processingTimeMs = (int) round($startedAt->diffInMilliseconds($completedAt));

        // ── Create AiRequest tracking record ──
        $aiRequest = AiRequest::create([
            'user_id'          => $request->user()->id,
            'subscription_id'  => $consumption['subscription']?->id,
            'visual_style_id'  => isset($style) ? $style->id : null,
            'product_id'       => $product?->id,
            'type'             => $this->resolveAiRequestType($mode, $styleSlug, $imageBase64 !== null, $hasReferenceInput),
            'status'           => $result['success'] ? 'completed' : 'failed',
            'user_prompt'      => $userContent,
            'processed_prompt' => $geminiPrompt !== $userContent ? $geminiPrompt : null,
            'hidden_prompt'    => $product?->hidden_prompt,
            'negative_prompt'  => $product?->negative_prompt,
            'model_used'       => $gemini->getModel(),
            'engine_provider'  => 'gemini',
            'credits_consumed' => $creditCost,
            'input_image_path' => $imageUrl,
            'processing_time_ms' => $processingTimeMs,
            'ip_address'       => $request->ip(),
            'user_agent'       => $request->userAgent(),
            'request_payload'  => [
                'mode' => $mode,
                'prompt' => $geminiPrompt,
                'has_uploaded_image' => $imageBase64 !== null || ! empty($productImagesData),
                'has_reference_input' => $hasReferenceInput,
                'product_template_slug' => $product?->slug,
                'product_template_image_url' => $productTemplateImageUrl,
                'product_template_reference_used' => $productTemplateImageUrl !== null,
                'product_template_reference_layout' => $productReferenceSheetSource ?? ($productTemplateImageUrl !== null ? 'separate_images' : null),
            ],
            'error_message'    => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
            'started_at'       => $startedAt,
            'completed_at'     => $completedAt,
            'metadata'         => [
                'product_template_image_url' => $productTemplateImageUrl,
                'product_template_reference_used' => $productTemplateImageUrl !== null,
                'product_template_reference_layout' => $productReferenceSheetSource ?? ($productTemplateImageUrl !== null ? 'separate_images' : null),
            ],
        ]);

        if ($result['success']) {
            // Save generated images to storage
            $generatedImageUrl = null;

            if (! empty($result['images'])) {
                $firstImage = $result['images'][0];
                $ext = str_contains($firstImage['mime_type'], 'png') ? 'png' : 'jpg';
                $fileName = 'ai-generated/' . $request->user()->id . '/' . Str::uuid() . '.' . $ext;

                Storage::disk('public')->put($fileName, base64_decode($firstImage['data']));

                MediaFile::create([
                    'user_id'       => $request->user()->id,
                    'file_name'     => basename($fileName),
                    'original_name' => 'generated-image.' . $ext,
                    'file_path'     => $fileName,
                    'disk'          => 'public',
                    'mime_type'     => $firstImage['mime_type'],
                    'file_size'     => strlen(base64_decode($firstImage['data'])),
                    'collection'    => 'chat',
                    'purpose'       => 'output',
                ]);

                $generatedImageUrl = '/storage/' . $fileName;

                // Update AiRequest with output image and fix type
                $updateData = ['output_image_path' => $generatedImageUrl];
                if ($aiRequest->type === 'chat' || $aiRequest->type === 'styled_chat') {
                    $updateData['type'] = 'text_to_image';
                }
                $aiRequest->update($updateData);
            }

            $aiMsg = $conversation->messages()->create([
                'ai_request_id' => $aiRequest->id,
                'role'      => 'assistant',
                'content'   => $result['content'] ?? ($generatedImageUrl ? '' : 'No response generated.'),
                'image_url' => $generatedImageUrl,
                'status'    => 'sent',
            ]);
        } else {
            // ── Refund credit on AI failure ──
            $usageService->refund($request->user(), $creditCost, 'ai_failure', [
                'ai_request_id' => $aiRequest->id,
                'error'         => $result['error'] ?? 'Unknown error',
            ]);

            // Update the consumption remaining after refund
            $consumption['remaining'] = $consumption['remaining'] + $creditCost;

            $aiRequest->update(['credits_consumed' => 0]);

            Log::warning('AI request failed — credit refunded', [
                'user_id'       => $request->user()->id,
                'ai_request_id' => $aiRequest->id,
            ]);

            $aiMsg = $conversation->messages()->create([
                'ai_request_id' => $aiRequest->id,
                'role'    => 'assistant',
                'content' => $result['error'] ?? 'Image generation failed.',
                'status'  => 'error',
            ]);
        }

        $responseConversation = $conversation->fresh()->load('messages');

        // Use unified warning level calculation
        $quotaStats = $usageService->computeWarningLevel(
            $consumption['remaining'],
            $consumption['subscription']->credits_total ?? 0
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'user_message' => $userMsg,
                'ai_message'   => $aiMsg,
                    'conversation' => $responseConversation,
            ],
            'quota' => [
                'remaining' => $consumption['remaining'],
                'warning'   => $quotaStats,
            ],
        ]);
    }

    /**
     * POST /api/conversations/{conversation}/messages/{message}/regenerate
     *
     * Regenerate the AI response for a given assistant message.
     * Finds the original user prompt and re-sends to AI.
     */
    public function regenerateMessage(Request $request, Conversation $conversation, $messageId): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        // Find the AI message
        $aiMessage = $conversation->messages()->where('id', $messageId)->where('role', 'assistant')->first();
        if (! $aiMessage) {
            return response()->json(['success' => false, 'message' => 'Message not found.'], 404);
        }

        // Find the user message before this AI response
        $userMessage = $conversation->messages()
            ->reorder()
            ->where('role', 'user')
            ->where('id', '<', $aiMessage->id)
            ->orderByDesc('id')
            ->first();

        if (! $userMessage) {
            return response()->json(['success' => false, 'message' => 'Original prompt not found.'], 404);
        }

        // ── Quota enforcement ──
        $usageService = new UsageService();

        $featureSlug = $aiMessage->video_url ? 'video_generation' : ($aiMessage->image_url ? 'text_to_image' : 'chat');
        $featureCheck = $usageService->checkFeatureLimit($request->user(), $featureSlug);
        if (! $featureCheck['allowed']) {
            return response()->json([
                'success'    => false,
                'message'    => 'Feature limit reached.',
                'error_code' => $featureCheck['reason'],
            ], 402);
        }

        $originalAiRequest = $this->findRelatedAiRequest($request->user()->id, $userMessage, $aiMessage);

        // Rebuild prompt using the original processed prompt whenever available.
        $content = $userMessage->content;
        $imageStyle = $userMessage->image_style;
        $mode = $this->inferRegenerateMode($originalAiRequest, $userMessage, $aiMessage);
        $geminiPrompt = $originalAiRequest?->processed_prompt ?: $content;

        if ($mode === 'video') {
            $regenerateVideoOptions = data_get($originalAiRequest?->metadata, 'video_options', [
                'duration_seconds' => 8,
                'aspect_ratio' => '16:9',
                'resolution' => '720p',
                'generate_audio' => true,
            ]);
            $constraints = $this->normalizeFeatureConstraints($featureCheck['constraints'] ?? []);
            $maxDuration = (int) ($constraints['max_duration_seconds'] ?? 0);
            $maxResolution = (string) ($constraints['max_resolution'] ?? '');

            if ($maxDuration > 0 && (int) ($regenerateVideoOptions['duration_seconds'] ?? 8) > $maxDuration) {
                return response()->json([
                    'success' => false,
                    'message' => "Your plan supports video generation up to {$maxDuration} seconds.",
                    'error_code' => 'video_duration_not_allowed',
                ], 402);
            }

            if ($maxResolution === '720p' && ($regenerateVideoOptions['resolution'] ?? '720p') === '1080p') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your plan supports video generation up to 720p resolution.',
                    'error_code' => 'video_resolution_not_allowed',
                ], 402);
            }
        }

        $featureModel = \App\Models\Feature::where('slug', $featureSlug)->first();
        $creditCost = $featureCheck['credits_per_use'] ?? 1;
        $consumption = $usageService->consume($request->user(), $creditCost, 'regenerate', [
            'feature_id' => $featureModel?->id,
        ]);
        if (! $consumption['success']) {
            $code = $consumption['reason'] === 'no_subscription' ? 'no_subscription' : 'insufficient_credits';
            return response()->json([
                'success'    => false,
                'message'    => 'Credits exhausted.',
                'error_code' => $code,
                'quota'      => ['remaining' => $consumption['remaining']],
            ], 402);
        }

        if ($originalAiRequest?->type === 'inpainting') {
            return $this->regenerateInpaintingMessage(
                $request,
                $conversation,
                $aiMessage,
                $userMessage,
                $originalAiRequest,
                $usageService,
                $consumption,
                $creditCost,
            );
        }

        $style = null;
        if ($mode === 'image' && $geminiPrompt === $content) {
            $parts = [];
            if ($imageStyle) {
                $style = VisualStyle::where('slug', $imageStyle)->where('is_active', true)->first();
                if ($style && $style->prompt_prefix) {
                    $parts[] = $style->prompt_prefix;
                }
            }
            $parts[] = $content;
            if ($style && $style->prompt_suffix) {
                $parts[] = $style->prompt_suffix;
            }
            $geminiPrompt = implode(', ', $parts);
        }

        // Build history exactly like the original send flow.
        // The original user prompt must not be duplicated in both history and currentParts.
        $history = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->where('id', '<', $userMessage->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $currentParts = [['text' => $geminiPrompt]];

        // If the original request had an input image, re-include it.
        $inputImageUrl = $originalAiRequest?->input_image_path ?: $userMessage->image_url;

        $regenerateVideoFollowUpContext = null;
        if ($mode === 'video' && ! $inputImageUrl) {
            $regenerateVideoFollowUpContext = $this->resolveVideoFollowUpContext(
                $conversation,
                $request->user()->id,
                $content,
                $userMessage->id,
            );

            if ($regenerateVideoFollowUpContext) {
                $geminiPrompt = $regenerateVideoFollowUpContext['prompt'];
                $inputImageUrl = $regenerateVideoFollowUpContext['reference_image_url'] ?? null;
                $currentParts = [['text' => $geminiPrompt]];
            }
        }

        if ($inputImageUrl) {
            $imagePath = str_replace('/storage/', '', $inputImageUrl);
            $fullPath = Storage::disk('public')->path($imagePath);
            if (file_exists($fullPath)) {
                $currentParts[] = [
                    'inline_data' => [
                        'mime_type' => mime_content_type($fullPath),
                        'data'      => base64_encode(file_get_contents($fullPath)),
                    ],
                ];
            }
        }

        if ($mode === 'video') {
            $videoOptions = data_get($originalAiRequest?->metadata, 'video_options', [
                'duration_seconds' => 8,
                'aspect_ratio' => '16:9',
                'resolution' => '720p',
                'generate_audio' => true,
            ]);

            $gemini = new GeminiService('video', $videoOptions['aspect_ratio'] ?? '16:9');
            $aiRequest = AiRequest::create([
                'user_id'            => $request->user()->id,
                'subscription_id'    => $consumption['subscription']?->id,
                'type'               => $inputImageUrl ? 'image_to_video' : 'text_to_video',
                'status'             => 'pending',
                'user_prompt'        => $content,
                'processed_prompt'   => $geminiPrompt !== $content ? $geminiPrompt : null,
                'model_used'         => $gemini->getModel(),
                'engine_provider'    => 'vertex_ai',
                'credits_consumed'   => $creditCost,
                'input_image_path'   => $inputImageUrl,
                'ip_address'         => $request->ip(),
                'user_agent'         => $request->userAgent(),
                'request_payload'    => [
                    'prompt' => $geminiPrompt,
                    'parameters' => $videoOptions,
                    'has_reference_image' => $inputImageUrl !== null,
                    'reference_ai_request_id' => $regenerateVideoFollowUpContext['reference_ai_request_id'] ?? data_get($originalAiRequest?->metadata, 'reference_ai_request_id'),
                    'reference_message_id' => $regenerateVideoFollowUpContext['reference_message_id'] ?? data_get($originalAiRequest?->metadata, 'reference_message_id'),
                ],
                'metadata'           => [
                    'source' => 'regenerate',
                    'original_ai_request_id' => $originalAiRequest?->id,
                    'original_message_id' => $aiMessage->id,
                    'generation_mode' => $inputImageUrl ? 'image_to_video' : 'text_to_video',
                    'video_options' => $videoOptions,
                    'reference_ai_request_id' => $regenerateVideoFollowUpContext['reference_ai_request_id'] ?? data_get($originalAiRequest?->metadata, 'reference_ai_request_id'),
                    'reference_message_id' => $regenerateVideoFollowUpContext['reference_message_id'] ?? data_get($originalAiRequest?->metadata, 'reference_message_id'),
                    'reference_source' => $regenerateVideoFollowUpContext['source'] ?? data_get($originalAiRequest?->metadata, 'reference_source'),
                    'source_video_url' => $regenerateVideoFollowUpContext['source_video_url'] ?? data_get($originalAiRequest?->metadata, 'source_video_url'),
                    'source_image_url' => $inputImageUrl,
                ],
            ]);

            $newAiMsg = $conversation->messages()->create([
                'ai_request_id' => $aiRequest->id,
                'role'          => 'assistant',
                'content'       => '',
                'status'        => 'processing',
                'metadata'      => [
                    'ai_request_uuid' => $aiRequest->uuid,
                    'video_options' => $videoOptions,
                ],
            ]);

            StartVeoVideoGeneration::dispatch($aiRequest->id, $newAiMsg->id);
            $aiMessage->delete();

            $responseConversation = $conversation->fresh()->load('messages');
            $quotaStats = $usageService->computeWarningLevel(
                $consumption['remaining'],
                $consumption['subscription']->credits_total ?? 0
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'ai_message'   => $newAiMsg,
                    'conversation' => $responseConversation,
                ],
                'quota' => [
                    'remaining' => $consumption['remaining'],
                    'warning'   => $quotaStats,
                ],
            ], 202);
        }

        $gemini = new GeminiService($mode);
        $startedAt = now();
        $result = $gemini->chatWithParts($history, $currentParts);
        $completedAt = now();
        $processingTimeMs = (int) round($startedAt->diffInMilliseconds($completedAt));

        $aiRequest = AiRequest::create([
            'user_id'            => $request->user()->id,
            'subscription_id'    => $consumption['subscription']?->id,
            'visual_style_id'    => $style?->id,
            'type'               => 'regenerate',
            'status'             => $result['success'] ? 'completed' : 'failed',
            'user_prompt'        => $content,
            'processed_prompt'   => $geminiPrompt !== $content ? $geminiPrompt : null,
            'model_used'         => $gemini->getModel(),
            'engine_provider'    => 'gemini',
            'credits_consumed'   => $creditCost,
            'processing_time_ms' => $processingTimeMs,
            'ip_address'         => $request->ip(),
            'user_agent'         => $request->userAgent(),
            'error_message'      => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
            'metadata'           => [
                'source' => 'regenerate',
                'original_ai_request_id' => $originalAiRequest?->id,
                'original_message_id' => $aiMessage->id,
            ],
            'started_at'         => $startedAt,
            'completed_at'       => $completedAt,
        ]);

        if ($result['success']) {
            $generatedImageUrl = null;
            if (! empty($result['images'])) {
                $firstImage = $result['images'][0];
                $ext = str_contains($firstImage['mime_type'], 'png') ? 'png' : 'jpg';
                $fileName = 'ai-generated/' . $request->user()->id . '/' . Str::uuid() . '.' . $ext;
                Storage::disk('public')->put($fileName, base64_decode($firstImage['data']));

                MediaFile::create([
                    'user_id'       => $request->user()->id,
                    'file_name'     => basename($fileName),
                    'original_name' => 'generated-image.' . $ext,
                    'file_path'     => $fileName,
                    'disk'          => 'public',
                    'mime_type'     => $firstImage['mime_type'],
                    'file_size'     => strlen(base64_decode($firstImage['data'])),
                    'collection'    => 'chat',
                    'purpose'       => 'output',
                ]);

                $generatedImageUrl = '/storage/' . $fileName;

                $aiRequest->update([
                    'output_image_path' => $generatedImageUrl,
                    'type' => 'text_to_image',
                ]);
            }

            $newAiMsg = $conversation->messages()->create([
                'ai_request_id' => $aiRequest->id,
                'role'      => 'assistant',
                'content'   => $result['content'] ?? ($generatedImageUrl ? '' : 'No response generated.'),
                'image_url' => $generatedImageUrl,
                'status'    => 'sent',
            ]);

            $aiMessage->delete();
        } else {
            $usageService->refund($request->user(), $creditCost, 'ai_failure', [
                'ai_request_id' => $aiRequest->id,
                'error'         => $result['error'] ?? 'Unknown error',
            ]);
            $consumption['remaining'] += $creditCost;
            $aiRequest->update(['credits_consumed' => 0]);

            $newAiMsg = $conversation->messages()->create([
                'ai_request_id' => $aiRequest->id,
                'role'    => 'assistant',
                'content' => $result['error'] ?? 'عذراً، حدث خطأ أثناء إعادة التوليد. يرجى المحاولة مرة أخرى.',
                'status'  => 'error',
            ]);
        }

        $responseConversation = $conversation->fresh()->load('messages');

        $quotaStats = $usageService->computeWarningLevel(
            $consumption['remaining'],
            $consumption['subscription']->credits_total ?? 0
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'ai_message'   => $newAiMsg,
                'conversation' => $responseConversation,
            ],
            'quota' => [
                'remaining' => $consumption['remaining'],
                'warning'   => $quotaStats,
            ],
        ]);
    }

    private function regenerateInpaintingMessage(
        Request $request,
        Conversation $conversation,
        ConversationMessage $aiMessage,
        ConversationMessage $userMessage,
        AiRequest $originalAiRequest,
        UsageService $usageService,
        array $consumption,
        int $creditCost,
    ): JsonResponse {
        $sourceUrl = $originalAiRequest->input_image_path ?: $userMessage->image_url ?: data_get($aiMessage->metadata, 'source_image_url');
        $maskUrl = $originalAiRequest->mask_image_path ?: data_get($aiMessage->metadata, 'mask_image_url');
        $sourcePath = $this->storagePathFromUrl((string) $sourceUrl);
        $maskPath = $this->storagePathFromUrl((string) $maskUrl);
        $sourceFullPath = $sourcePath ? Storage::disk('public')->path($sourcePath) : null;
        $maskFullPath = $maskPath ? Storage::disk('public')->path($maskPath) : null;

        if (! $sourceFullPath || ! $maskFullPath || ! file_exists($sourceFullPath) || ! file_exists($maskFullPath)) {
            $usageService->refund($request->user(), $creditCost, 'ai_failure', [
                'original_ai_request_id' => $originalAiRequest->id,
                'error' => 'Original inpainting source image or mask is missing.',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Original inpainting source image or mask is missing.',
            ], 404);
        }

        $preparedSource = $this->prepareImageForApi($sourceFullPath, mime_content_type($sourceFullPath) ?: 'image/jpeg');
        $preparedMask = $this->prepareImageForApi($maskFullPath, mime_content_type($maskFullPath) ?: 'image/png');
        $userPrompt = trim($userMessage->content ?? $originalAiRequest->user_prompt ?? '');
        $useSemanticObjectRecolor = $this->isObjectAwareRecolorPrompt($userPrompt);

        if ($this->isLocalMaskRecolorRequest($originalAiRequest)) {
            return $this->regenerateLocalMaskRecolorMessage(
                $request,
                $conversation,
                $aiMessage,
                $userMessage,
                $originalAiRequest,
                $usageService,
                $consumption,
                $creditCost,
                (string) $sourceUrl,
                (string) $maskUrl,
                $userPrompt,
            );
        }

        $processedPrompt = $useSemanticObjectRecolor
            ? $this->buildObjectAwareRecolorPrompt($userPrompt)
            : $this->buildInpaintingPrompt($userPrompt);

        $currentParts = [
            ['text' => $processedPrompt],
            [
                'inline_data' => [
                    'mime_type' => $preparedSource['mime_type'],
                    'data' => $preparedSource['data'],
                ],
            ],
            [
                'inline_data' => [
                    'mime_type' => $preparedMask['mime_type'],
                    'data' => $preparedMask['data'],
                ],
            ],
        ];

        $gemini = new GeminiService('image', $this->inferAspectRatioFromImage($sourceFullPath));
        $modelUsed = $gemini->getModel();
        $engineProvider = 'gemini';
        $maskDilation = $useSemanticObjectRecolor ? 0.0 : 0.025;
        $startedAt = now();

        if ($gemini->getAuthMethod() === 'service_account') {
            $result = $useSemanticObjectRecolor
                ? $gemini->inpaintWithMask($preparedSource, $preparedMask, $processedPrompt, null, 0.0)
                : $gemini->inpaintWithMask($preparedSource, $preparedMask, $processedPrompt, 'EDIT_MODE_INPAINT_INSERTION', $maskDilation);

            if ($result['success']) {
                $processedPrompt = $result['prompt_used'] ?? $processedPrompt;
                $modelUsed = $result['model_used'] ?? $modelUsed;
                $engineProvider = $result['engine_provider'] ?? $engineProvider;
            } else {
                Log::warning('Explicit-mask inpainting regeneration failed, falling back to Gemini native image editing', [
                    'user_id' => $request->user()->id,
                    'conversation_id' => $conversation->id,
                    'original_ai_request_id' => $originalAiRequest->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                $result = $gemini->chatWithParts([], $currentParts);
            }
        } else {
            $result = $gemini->chatWithParts([], $currentParts);
        }

        $completedAt = now();
        $processingTimeMs = (int) round($startedAt->diffInMilliseconds($completedAt));
        $newAiRequest = AiRequest::create([
            'user_id'            => $request->user()->id,
            'subscription_id'    => $consumption['subscription']?->id,
            'type'               => 'inpainting',
            'status'             => $result['success'] ? 'completed' : 'failed',
            'user_prompt'        => $userPrompt,
            'processed_prompt'   => $processedPrompt,
            'model_used'         => $modelUsed,
            'engine_provider'    => $engineProvider,
            'credits_consumed'   => $creditCost,
            'input_image_path'   => $sourceUrl,
            'mask_image_path'    => $maskUrl,
            'processing_time_ms' => $processingTimeMs,
            'ip_address'         => $request->ip(),
            'user_agent'         => $request->userAgent(),
            'request_payload'    => [
                'prompt' => $processedPrompt,
                'has_source_image' => true,
                'has_mask_image' => true,
                'mask_transport' => $engineProvider === 'imagen' ? 'explicit_mask' : 'semantic_mask',
                'edit_strategy' => $useSemanticObjectRecolor ? 'object_aware_recolor' : 'masked_inpainting',
                'mask_dilation' => $maskDilation,
            ],
            'metadata'           => [
                'source' => 'regenerate',
                'original_ai_request_id' => $originalAiRequest->id,
                'original_message_id' => $aiMessage->id,
                'generation_mode' => 'inpainting',
                'engine_provider' => $engineProvider,
                'edit_strategy' => $useSemanticObjectRecolor ? 'object_aware_recolor' : 'masked_inpainting',
            ],
            'error_message'      => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
            'started_at'         => $startedAt,
            'completed_at'       => $completedAt,
        ]);

        if ($result['success']) {
            $generatedImageUrl = null;

            if (! empty($result['images'])) {
                $firstImage = $result['images'][0];
                $ext = str_contains($firstImage['mime_type'], 'png') ? 'png' : 'jpg';
                $fileName = 'ai-generated/' . $request->user()->id . '/' . Str::uuid() . '.' . $ext;
                $imageBytes = base64_decode($firstImage['data']);

                Storage::disk('public')->put($fileName, $imageBytes);

                MediaFile::create([
                    'user_id'       => $request->user()->id,
                    'file_name'     => basename($fileName),
                    'original_name' => 'inpainted-image.' . $ext,
                    'file_path'     => $fileName,
                    'disk'          => 'public',
                    'mime_type'     => $firstImage['mime_type'],
                    'file_size'     => strlen($imageBytes),
                    'collection'    => 'chat',
                    'purpose'       => 'output',
                ]);

                $generatedImageUrl = '/storage/' . $fileName;
                $newAiRequest->update(['output_image_path' => $generatedImageUrl]);
            }

            $newAiMsg = $conversation->messages()->create([
                'ai_request_id' => $newAiRequest->id,
                'role'          => 'assistant',
                'content'       => $result['content'] ?? ($generatedImageUrl ? '' : 'No response generated.'),
                'image_url'     => $generatedImageUrl,
                'status'        => 'sent',
                'metadata'      => [
                    'edit_mode' => 'inpainting',
                    'source_message_id' => $userMessage->id,
                    'mask_image_url' => $maskUrl,
                    'source_image_url' => $sourceUrl,
                    'edit_strategy' => $useSemanticObjectRecolor ? 'object_aware_recolor' : 'masked_inpainting',
                ],
            ]);

            $aiMessage->delete();
        } else {
            $usageService->refund($request->user(), $creditCost, 'ai_failure', [
                'ai_request_id' => $newAiRequest->id,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
            $consumption['remaining'] += $creditCost;
            $newAiRequest->update(['credits_consumed' => 0]);

            $newAiMsg = $conversation->messages()->create([
                'ai_request_id' => $newAiRequest->id,
                'role'          => 'assistant',
                'content'       => $result['error'] ?? 'Image inpainting failed.',
                'status'        => 'error',
                'metadata'      => [
                    'edit_mode' => 'inpainting',
                    'source_message_id' => $userMessage->id,
                    'mask_image_url' => $maskUrl,
                    'source_image_url' => $sourceUrl,
                ],
            ]);
        }

        $responseConversation = $conversation->fresh()->load('messages');
        $quotaStats = $usageService->computeWarningLevel(
            $consumption['remaining'],
            $consumption['subscription']->credits_total ?? 0
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'ai_message'   => $newAiMsg,
                'conversation' => $responseConversation,
            ],
            'quota' => [
                'remaining' => $consumption['remaining'],
                'warning'   => $quotaStats,
            ],
        ]);
    }

    private function regenerateLocalMaskRecolorMessage(
        Request $request,
        Conversation $conversation,
        ConversationMessage $aiMessage,
        ConversationMessage $userMessage,
        AiRequest $originalAiRequest,
        UsageService $usageService,
        array $consumption,
        int $creditCost,
        string $sourceUrl,
        string $maskUrl,
        string $userPrompt,
    ): JsonResponse {
        $outputPath = $this->storagePathFromUrl((string) $originalAiRequest->output_image_path);
        $outputFullPath = $outputPath ? Storage::disk('public')->path($outputPath) : null;

        if (! $outputFullPath || ! file_exists($outputFullPath)) {
            $usageService->refund($request->user(), $creditCost, 'ai_failure', [
                'original_ai_request_id' => $originalAiRequest->id,
                'error' => 'Original local recolor output image is missing.',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Original local recolor output image is missing.',
            ], 404);
        }

        $startedAt = now();
        $mimeType = mime_content_type($outputFullPath) ?: 'image/png';
        $ext = str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg') ? 'jpg' : 'png';
        $fileName = 'ai-generated/' . $request->user()->id . '/' . Str::uuid() . '.' . $ext;
        $imageBytes = file_get_contents($outputFullPath);

        if ($imageBytes === false) {
            $usageService->refund($request->user(), $creditCost, 'ai_failure', [
                'original_ai_request_id' => $originalAiRequest->id,
                'error' => 'Could not read original local recolor output image.',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not read original local recolor output image.',
            ], 500);
        }

        Storage::disk('public')->put($fileName, $imageBytes);
        $generatedImageUrl = '/storage/' . $fileName;
        $completedAt = now();

        $newAiRequest = AiRequest::create([
            'user_id'            => $request->user()->id,
            'subscription_id'    => $consumption['subscription']?->id,
            'type'               => 'inpainting',
            'status'             => 'completed',
            'user_prompt'        => $userPrompt,
            'processed_prompt'   => $originalAiRequest->processed_prompt ?: $userPrompt,
            'model_used'         => 'browser-canvas-recolor',
            'engine_provider'    => 'local',
            'credits_consumed'   => $creditCost,
            'input_image_path'   => $sourceUrl,
            'output_image_path'  => $generatedImageUrl,
            'mask_image_path'    => $maskUrl,
            'processing_time_ms' => (int) round($startedAt->diffInMilliseconds($completedAt)),
            'ip_address'         => $request->ip(),
            'user_agent'         => $request->userAgent(),
            'request_payload'    => [
                'prompt' => $originalAiRequest->processed_prompt ?: $userPrompt,
                'has_source_image' => true,
                'has_mask_image' => true,
                'mask_transport' => 'local_canvas',
                'edit_strategy' => 'local_mask_recolor',
                'source_message_id' => $userMessage->id,
            ],
            'metadata'           => [
                'source' => 'regenerate',
                'original_ai_request_id' => $originalAiRequest->id,
                'original_message_id' => $aiMessage->id,
                'generation_mode' => 'inpainting',
                'engine_provider' => 'local',
                'edit_strategy' => 'local_mask_recolor',
            ],
            'started_at'         => $startedAt,
            'completed_at'       => $completedAt,
        ]);

        MediaFile::create([
            'user_id'       => $request->user()->id,
            'file_name'     => basename($fileName),
            'original_name' => 'recolored-image.' . $ext,
            'file_path'     => $fileName,
            'disk'          => 'public',
            'mime_type'     => $mimeType,
            'file_size'     => strlen($imageBytes),
            'collection'    => 'chat',
            'purpose'       => 'output',
        ]);

        $newAiMsg = $conversation->messages()->create([
            'ai_request_id' => $newAiRequest->id,
            'role'          => 'assistant',
            'content'       => '',
            'image_url'     => $generatedImageUrl,
            'status'        => 'sent',
            'metadata'      => [
                'edit_mode' => 'inpainting',
                'source_message_id' => $userMessage->id,
                'mask_image_url' => $maskUrl,
                'source_image_url' => $sourceUrl,
                'edit_strategy' => 'local_mask_recolor',
            ],
        ]);

        $aiMessage->delete();

        $responseConversation = $conversation->fresh()->load('messages');
        $quotaStats = $usageService->computeWarningLevel(
            $consumption['remaining'],
            $consumption['subscription']->credits_total ?? 0
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'ai_message'   => $newAiMsg,
                'conversation' => $responseConversation,
            ],
            'quota' => [
                'remaining' => $consumption['remaining'],
                'warning'   => $quotaStats,
            ],
        ]);
    }

    private function findRelatedAiRequest(int $userId, ConversationMessage $userMessage, ConversationMessage $aiMessage): ?AiRequest
    {
        if ($aiMessage->ai_request_id) {
            return AiRequest::find($aiMessage->ai_request_id);
        }

        if ($aiMessage->video_url) {
            $byOutputVideo = AiRequest::query()
                ->where('user_id', $userId)
                ->where('output_video_path', $aiMessage->video_url)
                ->latest('id')
                ->first();

            if ($byOutputVideo) {
                return $byOutputVideo;
            }
        }

        if ($aiMessage->image_url) {
            $byOutputImage = AiRequest::query()
                ->where('user_id', $userId)
                ->where('output_image_path', $aiMessage->image_url)
                ->latest('id')
                ->first();

            if ($byOutputImage) {
                return $byOutputImage;
            }
        }

        return AiRequest::query()
            ->where('user_id', $userId)
            ->where('user_prompt', $userMessage->content)
            ->whereBetween('created_at', [
                $userMessage->created_at->copy()->subMinutes(5),
                $aiMessage->created_at->copy()->addMinutes(5),
            ])
            ->latest('id')
            ->first();
    }

    private function inferRegenerateMode(?AiRequest $originalAiRequest, ConversationMessage $userMessage, ConversationMessage $aiMessage): string
    {
        if ($originalAiRequest) {
            if ($originalAiRequest->output_video_path) {
                return 'video';
            }

            if (in_array($originalAiRequest->type, ['text_to_video', 'image_to_video'], true)) {
                return 'video';
            }

            if ($originalAiRequest->output_image_path) {
                return 'image';
            }

            if (in_array($originalAiRequest->type, ['text_to_image', 'image_to_image'], true)) {
                return 'image';
            }
        }

        if ($aiMessage->video_url) {
            return 'video';
        }

        if ($aiMessage->image_url || $userMessage->image_style || $userMessage->image_url) {
            return 'image';
        }

        return 'text';
    }

    private function resolveFeatureSlug(string $mode, bool $hasReferenceInput): string
    {
        if ($mode === 'video') {
            return 'video_generation';
        }

        if ($mode === 'image' || $mode === 'product') {
            return $hasReferenceInput ? 'image_to_image' : 'text_to_image';
        }

        return 'chat';
    }

    private function normalizeModeForProductTemplate(string $mode, ?Product $product): string
    {
        if ($product && $mode === 'text') {
            return 'image';
        }

        return $mode;
    }

    private function productTemplateImageUrl(?Product $product): ?string
    {
        $thumbnail = trim((string) ($product?->thumbnail ?? ''));

        if ($thumbnail === '' || preg_match('/^https?:\/\//i', $thumbnail) === 1) {
            return null;
        }

        if (str_starts_with($thumbnail, '/storage/')) {
            return $thumbnail;
        }

        $path = ltrim($thumbnail, '/');

        if (str_starts_with($path, 'storage/')) {
            return '/' . $path;
        }

        return '/storage/' . $path;
    }

    private function buildProductTemplatePrompt(Product $product, string $userPrompt, bool $hasUploadedProductImage, bool $hasTemplateReference): string
    {
        $templatePrompt = $this->sanitizeProductTemplatePrompt((string) $product->hidden_prompt);

        $lines = [
            'Product template application task.',
            $hasUploadedProductImage
                ? 'Use the uploaded product reference image as the exact product to advertise. If a single split reference image is provided, the left/product-photo panel is the uploaded product. Preserve its product type, silhouette, proportions, color palette, material texture, handles, straps, hardware, labels, and distinctive details.'
                : 'Create a product showcase using the selected product template.',
            $hasTemplateReference
                ? 'Use the selected template reference image only for scene layout, camera angle, lighting mood, background, surface, framing, and composition. If a single split reference image is provided, the right/template panel is the selected template. Do not copy the item from the template over the uploaded product.'
                : 'Use the selected template prompt for scene layout, camera angle, lighting mood, background, surface, framing, and composition.',
        ];

        if ($templatePrompt !== '') {
            $lines[] = 'Template prompt: ' . $templatePrompt;
        }

        $lines[] = 'User request: ' . trim($userPrompt);
        $lines[] = 'Hard constraints: the final image must feature the uploaded product when one is provided, not a generic replacement. Do not invent, add, or alter brand names, logos, monograms, readable text, watermark, measurement marks, UI overlays, or typography anywhere in the image unless they already exist on the uploaded product. If props such as books, boxes, cards, labels, tags, packaging, posters, or papers appear, keep them blank and unbranded with no readable letters or symbols. Return exactly one clean product showcase image.';

        return trim(implode("\n", $lines));
    }

    private function sanitizeProductTemplatePrompt(string $templatePrompt): string
    {
        $sanitized = str_ireplace(
            ['luxury branding style', 'branding style', 'brand style', 'branded style'],
            ['luxury commercial style without brand marks', 'commercial style without brand marks', 'commercial style without brand marks', 'commercial style without brand marks'],
            $templatePrompt,
        );

        return trim($sanitized);
    }

    private function buildProductTemplateReferenceSheet(array $productImageStoragePaths, string $templateImageUrl): ?array
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        $templateStoragePath = $this->storagePathFromUrl($templateImageUrl);
        if (! $templateStoragePath) {
            return null;
        }

        $templateFullPath = Storage::disk('public')->path($templateStoragePath);
        if (! file_exists($templateFullPath)) {
            return null;
        }

        $productImages = [];
        foreach (array_slice($productImageStoragePaths, 0, 4) as $productImageStoragePath) {
            $fullPath = Storage::disk('public')->path(ltrim((string) $productImageStoragePath, '/\\'));
            $image = $this->loadGdImage($fullPath);
            if ($image) {
                $productImages[] = $image;
            }
        }

        $templateImage = $this->loadGdImage($templateFullPath);
        if (empty($productImages) || ! $templateImage) {
            $this->destroyGdImages($productImages);
            if ($templateImage) {
                imagedestroy($templateImage);
            }
            return null;
        }

        $canvasWidth = 1024;
        $canvasHeight = 512;
        $halfWidth = (int) ($canvasWidth / 2);
        $padding = 18;

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        $background = imagecolorallocate($canvas, 248, 248, 248);
        $divider = imagecolorallocate($canvas, 220, 220, 220);
        imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, $background);
        imagefilledrectangle($canvas, $halfWidth - 1, 0, $halfWidth + 1, $canvasHeight, $divider);

        $productPanelX = $padding;
        $productPanelY = $padding;
        $productPanelWidth = $halfWidth - ($padding * 2);
        $productPanelHeight = $canvasHeight - ($padding * 2);

        if (count($productImages) === 1) {
            $this->drawContainedGdImage($canvas, $productImages[0], $productPanelX, $productPanelY, $productPanelWidth, $productPanelHeight);
        } else {
            $columns = 2;
            $rows = (int) ceil(count($productImages) / $columns);
            $gap = 12;
            $cellWidth = (int) (($productPanelWidth - $gap) / $columns);
            $cellHeight = (int) (($productPanelHeight - ($gap * ($rows - 1))) / $rows);

            foreach ($productImages as $index => $productImage) {
                $column = $index % $columns;
                $row = (int) floor($index / $columns);
                $cellX = $productPanelX + ($column * ($cellWidth + $gap));
                $cellY = $productPanelY + ($row * ($cellHeight + $gap));
                $this->drawContainedGdImage($canvas, $productImage, $cellX, $cellY, $cellWidth, $cellHeight);
            }
        }

        $templatePanelX = $halfWidth + $padding;
        $templatePanelY = $padding;
        $templatePanelWidth = $halfWidth - ($padding * 2);
        $templatePanelHeight = $canvasHeight - ($padding * 2);
        $this->drawContainedGdImage($canvas, $templateImage, $templatePanelX, $templatePanelY, $templatePanelWidth, $templatePanelHeight);

        ob_start();
        imagejpeg($canvas, null, 88);
        $imageBytes = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($templateImage);
        $this->destroyGdImages($productImages);

        if (! is_string($imageBytes) || $imageBytes === '') {
            return null;
        }

        return [
            'mime_type' => 'image/jpeg',
            'data' => base64_encode($imageBytes),
        ];
    }

    private function loadGdImage(string $filePath): mixed
    {
        if (! file_exists($filePath)) {
            return null;
        }

        $imageBytes = file_get_contents($filePath);
        if ($imageBytes === false) {
            return null;
        }

        return @imagecreatefromstring($imageBytes) ?: null;
    }

    private function drawContainedGdImage(mixed $canvas, mixed $sourceImage, int $x, int $y, int $width, int $height): void
    {
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        if ($sourceWidth <= 0 || $sourceHeight <= 0 || $width <= 0 || $height <= 0) {
            return;
        }

        $scale = min($width / $sourceWidth, $height / $sourceHeight);
        $targetWidth = max(1, (int) floor($sourceWidth * $scale));
        $targetHeight = max(1, (int) floor($sourceHeight * $scale));
        $targetX = $x + (int) floor(($width - $targetWidth) / 2);
        $targetY = $y + (int) floor(($height - $targetHeight) / 2);

        imagecopyresampled(
            $canvas,
            $sourceImage,
            $targetX,
            $targetY,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );
    }

    private function destroyGdImages(array $images): void
    {
        foreach ($images as $image) {
            if ($image) {
                imagedestroy($image);
            }
        }
    }

    private function buildInpaintingPrompt(string $userPrompt): string
    {
        return trim(implode("\n", [
            'Requested edit: ' . $userPrompt,
            'Use image 1 as the source and image 2 as the black-and-white mask.',
            'Edit only the white mask region. Black mask pixels must remain visually unchanged.',
            'Complete the requested edit. If it asks to remove, replace, add, insert, or put an object, remove the masked object and place the requested object inside the same selected footprint, not larger.',
            'For remove or replace requests, the final white-mask area must not contain the removed selected object; do not keep, duplicate, or redraw the selected items inside the mask.',
            'For object replacement on a surface, reconstruct the underlying surface only inside the white mask, then anchor the new object naturally on that surface with matching perspective, light, and shadow.',
            'Do not alter, blur, repaint, relight, or reinterpret any background outside the white mask, including windows, shelves, desk edges, walls, books outside the mask, and surrounding objects.',
            'Return exactly one final edited image.',
        ]));
    }

    private function isObjectAwareRecolorPrompt(string $userPrompt): bool
    {
        $prompt = mb_strtolower($userPrompt);

        if ($this->detectTargetRecolor($userPrompt) === null) {
            return false;
        }

        if (preg_match('/\b(recolou?r|change\s+(?:the\s+)?colou?r|change\s+.*\bto\s+(?:red|blue|green|yellow|black|white|pink|purple|orange|brown|gray|grey|gold|silver)|paint|dye|make|turn)\b/i', $userPrompt) === 1) {
            return true;
        }

        return preg_match('/(غيّر|غير|بدّل|بدل|حوّل|حول|اجعل|خلي|خلّي|لوّن|لون|اصبغ|إصبغ|صبغ|ادهان|ادهن|لون|اللون|ألوان|الوان)/u', $prompt) === 1;
    }

    private function isLocalMaskRecolorRequest(AiRequest $aiRequest): bool
    {
        return $aiRequest->engine_provider === 'local'
            || data_get($aiRequest->request_payload, 'edit_strategy') === 'local_mask_recolor'
            || data_get($aiRequest->metadata, 'edit_strategy') === 'local_mask_recolor';
    }

    private function buildObjectAwareRecolorPrompt(string $userPrompt): string
    {
        $targetColor = $this->detectTargetRecolor($userPrompt);
        $targetColorName = $targetColor['name'] ?? trim($userPrompt);
        $recolorLine = $targetColor
            ? "Recolor the chair frame, chair seat, chair back, chair spindles, and the draped shawl touched by the mask to {$targetColorName}."
            : 'Recolor the chair frame, chair seat, chair back, chair spindles, and the draped shawl touched by the mask to the requested color.';

        return trim(implode("\n", [
            $recolorLine,
            'Preserve the original chair geometry, wood grain, slats, fabric folds, contours, texture, lighting, shadows, perspective, and all details.',
            'Do not fill the mask shape and do not replace the objects.',
        ]));
    }

    private function detectTargetRecolor(string $userPrompt): ?array
    {
        $prompt = mb_strtolower($userPrompt);
        $colors = [
            'red' => [
                'name' => 'red',
                'hex' => '#c40000',
                'avoid' => 'yellow, orange, brown, gold, or mustard',
                'patterns' => ['red', 'الأحمر', 'احمر', 'أحمر', 'حمراء', 'حمرا'],
            ],
            'blue' => [
                'name' => 'blue',
                'hex' => '#0057ff',
                'avoid' => 'cyan, teal, green, or purple',
                'patterns' => ['blue', 'الأزرق', 'ازرق', 'أزرق', 'زرقاء'],
            ],
            'yellow' => [
                'name' => 'yellow',
                'hex' => '#ffd000',
                'avoid' => 'orange, red, brown, or beige',
                'patterns' => ['yellow', 'الأصفر', 'اصفر', 'أصفر', 'صفراء'],
            ],
            'green' => [
                'name' => 'green',
                'hex' => '#1f9d45',
                'avoid' => 'yellow, teal, blue, or brown',
                'patterns' => ['green', 'الأخضر', 'اخضر', 'أخضر', 'خضراء'],
            ],
            'black' => [
                'name' => 'black',
                'hex' => '#111111',
                'avoid' => 'gray, brown, or navy',
                'patterns' => ['black', 'الأسود', 'اسود', 'أسود', 'سوداء'],
            ],
            'white' => [
                'name' => 'white',
                'hex' => '#f5f5f5',
                'avoid' => 'gray, beige, yellow, or cream',
                'patterns' => ['white', 'الأبيض', 'ابيض', 'أبيض', 'بيضاء'],
            ],
            'pink' => [
                'name' => 'pink',
                'hex' => '#ff4da6',
                'avoid' => 'red, purple, or orange',
                'patterns' => ['pink', 'وردي', 'زهري'],
            ],
            'purple' => [
                'name' => 'purple',
                'hex' => '#7b2cff',
                'avoid' => 'blue, pink, or burgundy',
                'patterns' => ['purple', 'بنفسجي'],
            ],
            'orange' => [
                'name' => 'orange',
                'hex' => '#ff7a00',
                'avoid' => 'red, yellow, brown, or gold',
                'patterns' => ['orange', 'برتقالي'],
            ],
            'brown' => [
                'name' => 'brown',
                'hex' => '#7a3f17',
                'avoid' => 'orange, red, yellow, or black',
                'patterns' => ['brown', 'بني'],
            ],
            'gray' => [
                'name' => 'gray',
                'hex' => '#808080',
                'avoid' => 'black, white, blue, or beige',
                'patterns' => ['gray', 'grey', 'رمادي'],
            ],
        ];

        foreach ($colors as $color) {
            foreach ($color['patterns'] as $pattern) {
                if (str_contains($prompt, mb_strtolower($pattern))) {
                    return [
                        'name' => $color['name'],
                        'hex' => $color['hex'],
                        'avoid' => $color['avoid'],
                    ];
                }
            }
        }

        return null;
    }

    private function storagePathFromUrl(string $url): ?string
    {
        $path = trim($url);

        if ($path === '' || str_starts_with($path, 'gs://') || preg_match('/^https?:\/\//i', $path) === 1) {
            return null;
        }

        $path = preg_replace('#^/?storage/#', '', $path) ?? $path;
        $path = preg_replace('#^public/#', '', $path) ?? $path;

        return ltrim($path, '/\\');
    }

    private function inferAspectRatioFromImage(string $filePath): ?string
    {
        $size = @getimagesize($filePath);
        if (! $size || empty($size[0]) || empty($size[1])) {
            return null;
        }

        $actualRatio = $size[0] / $size[1];
        $ratios = [
            '1:1' => 1,
            '3:4' => 3 / 4,
            '4:3' => 4 / 3,
            '9:16' => 9 / 16,
            '16:9' => 16 / 9,
        ];

        $closest = null;
        $closestDistance = PHP_FLOAT_MAX;

        foreach ($ratios as $label => $ratio) {
            $distance = abs($actualRatio - $ratio);
            if ($distance < $closestDistance) {
                $closest = $label;
                $closestDistance = $distance;
            }
        }

        return $closest;
    }

    private function normalizeFeatureConstraints(mixed $constraints): array
    {
        if (is_string($constraints)) {
            $decoded = json_decode($constraints, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($constraints) ? $constraints : [];
    }

    private function resolveAiRequestType(string $mode, ?string $styleSlug, bool $hasUploadedImage, bool $hasReferenceInput): string
    {
        if ($mode === 'video') {
            return $hasReferenceInput ? 'image_to_video' : 'text_to_video';
        }

        if ($mode === 'product') {
            return 'product';
        }

        if ($mode === 'image') {
            return $hasReferenceInput ? 'image_to_image' : 'text_to_image';
        }

        if ($hasUploadedImage) {
            return 'multimodal';
        }

        return $styleSlug ? 'styled_chat' : 'chat';
    }

    private function shouldTreatImagePromptAsText(string $content): bool
    {
        $prompt = $this->normalizePrompt($content);

        $imageIntentPatterns = [
            '/\b(generate|create|make|remake|redo|again|variation|version|aspect|ratio|size|resize|portrait|landscape|widescreen|same image|image number|number\s*\d+)\b/u',
            '/(اعمل|اعملي|أنشئ|انشئ|ولد|ولّد|سوي|سوّي|أعد|اعد|عيد|كرر|كرّر|مرة|مره|تاني|ثاني|ثانية|ثانيه|نفس الصورة|نفس الصوره|الصورة نفسها|الصوره نفسها|مثلها|زيها|نسخة|نسخه|ابعاد|أبعاد|مقاس|نسبة|أفقي|افقي|طولي|عمودي|صورة رقم|صوره رقم|الصورة رقم|الصوره رقم|16:9|9:16|4:3|3:4|1:1)/u',
        ];

        foreach ($imageIntentPatterns as $pattern) {
            if (preg_match($pattern, $prompt)) {
                return false;
            }
        }

        $textIntentPatterns = [
            '/\b(how many|count|list|which|what did|tell me|summarize|summary|explain|describe|analyze|compare|remember|understand|identify|recognize|read|ocr|extract)\b/u',
            '/(كم|عدد|اذكر|قول|قل|لخص|لخّص|اختصر|اشرح|اوصف|حلل|حلّل|قارن|تذكر|تتذكر|ما الذي|ماهي|ما هي|ايه|إيه|ايش|افهم|فهم|حدد|حدّد|تعرف|تعرّف|اقرأ|استخرج)/u',
        ];

        foreach ($textIntentPatterns as $pattern) {
            if (preg_match($pattern, $prompt)) {
                return true;
            }
        }

        return false;
    }

    private function resolveImageFollowUpContext(Conversation $conversation, int $userId, string $content): ?array
    {
        $imageMessages = $conversation->messages()
            ->with('aiRequest')
            ->where('role', 'assistant')
            ->whereNotNull('image_url')
            ->orderBy('id')
            ->get()
            ->filter(fn (ConversationMessage $message) => $message->aiRequest !== null)
            ->values();

        if ($imageMessages->isEmpty()) {
            return null;
        }

        $referencedMessage = null;
        $ordinal = $this->extractReferencedOrdinal($content);

        if ($ordinal !== null && $ordinal >= 1 && $ordinal <= $imageMessages->count()) {
            $referencedMessage = $imageMessages->get($ordinal - 1);
        }

        if (! $referencedMessage) {
            $referencedMessage = $this->findBestMatchingImageMessage($imageMessages, $content);
        }

        if (! $referencedMessage || ! $referencedMessage->aiRequest) {
            return null;
        }

        $referencedRequest = $referencedMessage->aiRequest;
        // Use user_prompt (raw) so the image-generation enhancer can re-enhance it.
        // processed_prompt is already enhanced and would be double-enhanced.
        $basePrompt = $referencedRequest->user_prompt ?: $referencedRequest->processed_prompt;

        if (! $basePrompt) {
            return null;
        }

        $isRegeneration = $this->isReferenceOnlyImageFollowUp($content, $ordinal !== null);

        // Never attach inline reference images — sending base64 image data
        // triggers Google 417 anti-automation blocks. Instead, merge any edit
        // instructions into the text prompt and regenerate as text-to-image.
        return [
            'prompt' => $this->mergeImageFollowUpPrompt($basePrompt, $content, $ordinal !== null),
            'reference_image_url' => null,
            'reference_ai_request_id' => $referencedRequest->id,
            'reference_user_id' => $userId,
            'is_regeneration' => $isRegeneration,
        ];
    }

    private function resolveVideoFollowUpContext(Conversation $conversation, int $userId, string $content, ?int $beforeMessageId = null): ?array
    {
        $ordinal = $this->extractReferencedOrdinal($content);
        if ($ordinal === null && ! $this->isVideoFollowUpPrompt($content)) {
            return null;
        }

        $query = $conversation->messages()
            ->with('aiRequest')
            ->where('role', 'assistant')
            ->orderBy('id');

        if ($beforeMessageId !== null) {
            $query->where('id', '<', $beforeMessageId);
        }

        $visualMessages = $query->get()
            ->filter(fn (ConversationMessage $message) => $this->messageHasReusableVideoContext($message))
            ->values();

        if ($visualMessages->isEmpty()) {
            return null;
        }

        $referencedMessage = null;
        if ($ordinal !== null && $ordinal >= 1 && $ordinal <= $visualMessages->count()) {
            $referencedMessage = $visualMessages->get($ordinal - 1);
        }

        if (! $referencedMessage) {
            $referencedMessage = $this->findBestMatchingVisualMessage($visualMessages, $content, true);
        }

        if (! $referencedMessage || ! $referencedMessage->aiRequest) {
            return null;
        }

        $referencedRequest = $referencedMessage->aiRequest;
        $basePrompt = $this->videoBasePromptForReferencedRequest($referencedRequest);

        if (! $basePrompt) {
            return null;
        }

        $referenceImageUrl = $this->extractVideoReferenceImageUrl($referencedMessage);

        return [
            'prompt' => $this->mergeVideoFollowUpPrompt($basePrompt, $content, $referenceImageUrl !== null, $ordinal !== null),
            'reference_image_url' => $referenceImageUrl,
            'reference_ai_request_id' => $referencedRequest->id,
            'reference_message_id' => $referencedMessage->id,
            'reference_user_id' => $userId,
            'source' => $referenceImageUrl ? 'image_reference' : 'prompt_context',
            'source_video_url' => $referencedMessage->video_url ?: $referencedRequest->output_video_path,
        ];
    }

    private function videoBasePromptForReferencedRequest(AiRequest $referencedRequest): ?string
    {
        if ($referencedRequest->type === 'inpainting' || $referencedRequest->mask_image_path) {
            return $referencedRequest->user_prompt ?: $referencedRequest->processed_prompt;
        }

        return $referencedRequest->processed_prompt ?: $referencedRequest->user_prompt;
    }

    private function messageHasReusableVideoContext(ConversationMessage $message): bool
    {
        return (bool) (
            $message->image_url
            || $message->video_url
            || $message->aiRequest?->input_image_path
            || $message->aiRequest?->output_image_path
            || $message->aiRequest?->output_video_path
        );
    }

    private function extractVideoReferenceImageUrl(ConversationMessage $message): ?string
    {
        $candidates = [
            $message->image_url,
            $message->aiRequest?->output_image_path,
            $message->aiRequest?->input_image_path,
            data_get($message->metadata, 'source_image_url'),
            data_get($message->aiRequest?->metadata, 'source_image_url'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && str_starts_with($candidate, '/storage/')) {
                return $candidate;
            }
        }

        return null;
    }

    private function isVideoFollowUpPrompt(string $content): bool
    {
        $normalized = $this->normalizePrompt($content);

        $patterns = [
            '/\b(previous|last|same|this|that|edit|change|make it|turn it|recolor|replace|remove|regenerate|again|use it)\b/u',
            '/(السابق|السابقة|اللي|اللى|الي|إلي|الى|هذا|هذه|ده|دي|دى|نفس|تعديل|عدّل|عدل|غيّر|غير|خلّي|خلي|خليلي|حوّل|حول|لوّن|لون|استبدل|بدّل|بدل|شيل|احذف|اعادة|إعادة|كرر|كرّر|الفيديو|المشهد|الاعلان|الإعلان)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function findBestMatchingImageMessage($imageMessages, string $content): ?ConversationMessage
    {
        return $this->findBestMatchingVisualMessage($imageMessages, $content);
    }

    private function findBestMatchingVisualMessage($imageMessages, string $content, bool $skipExactPrompt = false): ?ConversationMessage
    {
        $keywords = $this->extractReferenceKeywords($content);
        $normalizedContent = $this->normalizePrompt($content);

        if (empty($keywords)) {
            return $imageMessages->last();
        }

        $bestMessage = null;
        $bestScore = 0;

        foreach ($imageMessages as $message) {
            if ($skipExactPrompt && $message->aiRequest && $this->normalizePrompt((string) $message->aiRequest->user_prompt) === $normalizedContent) {
                continue;
            }

            $haystack = $this->normalizePrompt(implode(' ', array_filter([
                $message->aiRequest?->user_prompt,
                $message->aiRequest?->processed_prompt,
                $message->content,
            ])));

            $score = 0;
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($haystack, $keyword)) {
                    $score += mb_strlen($keyword);
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMessage = $message;
            }
        }

        return $bestMessage ?: $imageMessages->last();
    }

    private function extractReferencedOrdinal(string $content): ?int
    {
        $normalizedDigits = strtr($content, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        if (preg_match('/(?:رقم|number|#)\s*(\d+)/iu', $normalizedDigits, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(\d+)\b/u', $normalizedDigits, $matches)) {
            return (int) $matches[1];
        }

        $lower = $this->normalizePrompt($normalizedDigits);

        return match (true) {
            str_contains($lower, 'الاولى'), str_contains($lower, 'الأولى'), str_contains($lower, 'first') => 1,
            str_contains($lower, 'الثانية'), str_contains($lower, 'الثانيه'), str_contains($lower, 'second') => 2,
            str_contains($lower, 'الثالثة'), str_contains($lower, 'الثالثه'), str_contains($lower, 'third') => 3,
            default => null,
        };
    }

    private function extractReferenceKeywords(string $content): array
    {
        $normalized = $this->normalizePrompt($content);
        $tokens = preg_split('/[^\p{L}\p{N}:]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopwords = [
            'رقم', 'number', 'image', 'photo', 'picture',
            'الصورة', 'الصوره', 'صورة', 'صوره',
            'اعمل', 'اعملي', 'أنشئ', 'انشئ', 'ولد', 'ولد', 'عيد', 'اعد', 'أعد',
            'خلي', 'خلّي', 'خليلي', 'اجعل', 'إجعل', 'حول', 'حوّل', 'غير', 'غيّر', 'لون', 'لوّن',
            'مرة', 'مره', 'تاني', 'ثاني', 'ثانية', 'ثانيه',
            'نفس', 'مثلها', 'زيها', 'نسخة', 'نسخه',
            'ابعاد', 'أبعاد', 'مقاس', 'نسبة', 'aspect', 'ratio', 'size', 'dimensions',
            'new', 'different', 'الجديدة', 'جديدة', 'بتاع', 'بتاعت',
            'red', 'blue', 'green', 'yellow', 'black', 'white', 'احمر', 'أحمر', 'حمراء', 'ازرق', 'أزرق', 'اخضر', 'أخضر', 'اصفر', 'أصفر', 'اسود', 'أسود', 'ابيض', 'أبيض',
            '16:9', '9:16', '4:3', '3:4', '1:1',
        ];

        return array_values(array_filter(array_unique($tokens), function (string $token) use ($stopwords) {
            return ! in_array($token, $stopwords, true)
                && ! ctype_digit($token)
                && mb_strlen($token) >= 3;
        }));
    }

    private function mergeImageFollowUpPrompt(string $basePrompt, string $followUpPrompt, bool $resolvedByOrdinal = false): string
    {
        if ($this->isReferenceOnlyImageFollowUp($followUpPrompt, $resolvedByOrdinal)) {
            return $basePrompt;
        }

        return trim($basePrompt) . "\nWith these modifications: " . trim($followUpPrompt);
    }

    private function mergeVideoFollowUpPrompt(string $basePrompt, string $followUpPrompt, bool $hasReferenceImage, bool $resolvedByOrdinal = false): string
    {
        if ($this->isReferenceOnlyImageFollowUp($followUpPrompt, $resolvedByOrdinal)) {
            return $basePrompt;
        }

        $contextInstruction = $hasReferenceImage
            ? 'Use the provided reference image as the visual anchor.'
            : 'Use the previous video prompt as the visual anchor.';

        return trim(implode("\n", [
            trim($basePrompt),
            $contextInstruction,
            'Follow-up edit: ' . trim($followUpPrompt),
            'Keep the same main subject, product, setting, composition, camera style, and visual continuity unless the follow-up explicitly changes them.',
            'Do not introduce unrelated people, faces, objects, locations, or a different product.',
        ]));
    }

    private function isReferenceOnlyImageFollowUp(string $content, bool $resolvedByOrdinal = false): bool
    {
        $normalized = $this->normalizePrompt($content);
        $stripped = preg_replace([
            '/(?:رقم|number|#)\s*\d+/iu',
            '/\b\d+\b/u',
            '/(الصورة|الصوره|صورة|صوره|نفس|مثلها|زيها|أعد|اعد|عيد|كرر|كرّر|مرة|مره|تاني|ثاني|ثانية|ثانيه|ابعاد|أبعاد|مقاس|نسبة|الجديدة|جديدة|بتاع|بتاعت|عايز|عاوز|ابي|ابغى|أبي|أبغى|ممكن|شوت|لقطه|لقطة|مشهد|اخر|آخر|اخرى|أخرى|جديد|جديده|طيب|اوكي|اوك|حاضر|ماشي|يلا|كمان|واحد|واحده|aspect|ratio|size|dimensions|same|again|remake|redo|variation|version|new|different|another|shot|scene|one more|give me|ok|okay|sure|yes|please)/u',
            '/\b(?:16:9|9:16|4:3|3:4|1:1)\b/u',
        ], ' ', $normalized);

        $stripped = trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');

        if ($stripped === '') {
            return true;
        }

        return $resolvedByOrdinal && mb_strlen($stripped) <= 12;
    }

    private function appendInlineImageFromUrl(array &$parts, string $imageUrl): void
    {
        $imagePath = str_replace('/storage/', '', $imageUrl);
        $fullPath = Storage::disk('public')->path($imagePath);

        if (! file_exists($fullPath)) {
            return;
        }

        $preparedImage = $this->prepareImageForApi($fullPath, mime_content_type($fullPath) ?: 'image/jpeg');

        $parts[] = [
            'inline_data' => [
                'mime_type' => $preparedImage['mime_type'],
                'data'      => $preparedImage['data'],
            ],
        ];
    }

    private function normalizePrompt(string $content): string
    {
        $normalized = mb_strtolower(trim($content));
        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }

    /**
     * Prepare an image for Gemini/Imagen requests while preserving the original mime type when GD is unavailable.
     */
    private function prepareImageForApi(string $filePath, string $mimeType, int $maxDim = 1024, int $quality = 80): array
    {
        $normalizedMime = strtolower(trim($mimeType)) ?: 'image/jpeg';
        $rawBytes = file_get_contents($filePath);

        if ($rawBytes === false) {
            return [
                'mime_type' => $normalizedMime,
                'data' => '',
            ];
        }

        if (! $this->canResizeImageWithGd($normalizedMime)) {
            return [
                'mime_type' => $normalizedMime,
                'data' => base64_encode($rawBytes),
            ];
        }

        $src = null;

        switch ($normalizedMime) {
            case 'image/jpeg':
            case 'image/jpg':
                $src = @imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $src = @imagecreatefrompng($filePath);
                break;
            case 'image/webp':
                $src = @imagecreatefromwebp($filePath);
                break;
            case 'image/gif':
                $src = @imagecreatefromgif($filePath);
                break;
        }

        if (! $src) {
            return [
                'mime_type' => $normalizedMime,
                'data' => base64_encode($rawBytes),
            ];
        }

        $w = imagesx($src);
        $h = imagesy($src);

        if ($w <= $maxDim && $h <= $maxDim) {
            imagedestroy($src);
            return [
                'mime_type' => $normalizedMime,
                'data' => base64_encode($rawBytes),
            ];
        }

        // Scale down proportionally
        if ($w >= $h) {
            $newW = $maxDim;
            $newH = (int) round($h * ($maxDim / $w));
        } else {
            $newH = $maxDim;
            $newW = (int) round($w * ($maxDim / $h));
        }

        $dst = imagecreatetruecolor($newW, $newH);

        if (! $dst) {
            imagedestroy($src);

            return [
                'mime_type' => $normalizedMime,
                'data' => base64_encode($rawBytes),
            ];
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        ob_start();
        imagejpeg($dst, null, $quality);
        $data = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if (! is_string($data) || $data === '') {
            return [
                'mime_type' => $normalizedMime,
                'data' => base64_encode($rawBytes),
            ];
        }

        return [
            'mime_type' => 'image/jpeg',
            'data' => base64_encode($data),
        ];
    }

    private function canResizeImageWithGd(string $mimeType): bool
    {
        $coreFunctions = [
            'imagecreatetruecolor',
            'imagecopyresampled',
            'imagejpeg',
            'imagesx',
            'imagesy',
            'imagedestroy',
        ];

        foreach ($coreFunctions as $function) {
            if (! function_exists($function)) {
                return false;
            }
        }

        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => function_exists('imagecreatefromjpeg'),
            'image/png' => function_exists('imagecreatefrompng'),
            'image/webp' => function_exists('imagecreatefromwebp'),
            'image/gif' => function_exists('imagecreatefromgif'),
            default => false,
        };
    }
}
