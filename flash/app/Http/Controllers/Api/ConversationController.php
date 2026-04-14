<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\CreditLedger;
use App\Models\MediaFile;
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

    private function processSendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        // ── Quota enforcement (concurrency-safe) ──
        $usageService = new UsageService();

        // Check feature-level limits first
        $featureSlug = ($request->input('mode') === 'image') ? 'text_to_image' : 'chat';
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

        $data = $request->validate([
            'content'     => ['required', 'string', 'max:5000'],
            'image_style' => ['nullable', 'string', 'max:100'],
            'image'       => ['nullable', 'image', 'max:10240'], // 10MB
            'images'      => ['nullable', 'array', 'max:10'],
            'images.*'    => ['image', 'max:10240'],
            'mode'        => ['nullable', 'string', 'in:text,image,product'],
        ]);

        $mode = $data['mode'] ?? 'text';

        // Auto-detect: if user sends image-mode text follow-up in a conversation
        // that has recent product/image uploads, switch to text mode for multimodal context.
        // This prevents accidental image generation when the user intends to discuss uploaded images.
        if ($mode === 'image' && ! $request->hasFile('image') && ! $request->hasFile('images')) {
            $hasRecentImages = $conversation->messages()
                ->where('role', 'user')
                ->where(function ($q) {
                    $q->whereNotNull('image_url')->orWhereNotNull('product_images');
                })
                ->where('created_at', '>=', now()->subHours(1))
                ->exists();

            if ($hasRecentImages) {
                $mode = 'text';
            }
        }

        // Handle uploaded image
        $imageUrl = null;
        $imageBase64 = null;
        $imageMime = null;

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
            $imageBase64 = base64_encode(file_get_contents($file->getRealPath()));
            $imageMime = $file->getMimeType();
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
                $resizedBase64 = $this->resizeImageForApi($file->getRealPath(), $file->getMimeType());

                $productImagesData[] = [
                    'url'    => '/storage/' . $path,
                    'base64' => $resizedBase64,
                    'mime'   => 'image/jpeg',
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
        $geminiPrompt = $userContent;

        if ($mode === 'image') {
            $parts = [];

            // Inject hidden style prompt if a style is selected
            $style = null;
            if ($styleSlug) {
                $style = VisualStyle::where('slug', $styleSlug)->where('is_active', true)->first();
                if ($style && $style->prompt_prefix) {
                    $parts[] = $style->prompt_prefix;
                }
            }

            // User prompt (keep it clean - enhancePromptForImagen will handle quality keywords)
            $parts[] = $userContent;

            // Style suffix (if any)
            if ($style && $style->prompt_suffix) {
                $parts[] = $style->prompt_suffix;
            }

            $geminiPrompt = implode(', ', $parts);
        } elseif ($styleSlug) {
            // Text mode with style — just wrap with prefix/suffix
            $style = VisualStyle::where('slug', $styleSlug)->where('is_active', true)->first();
            if ($style) {
                $parts = [];
                if ($style->prompt_prefix) {
                    $parts[] = $style->prompt_prefix;
                }
                $parts[] = $userContent;
                if ($style->prompt_suffix) {
                    $parts[] = $style->prompt_suffix;
                }
                $geminiPrompt = implode("\n\n", $parts);
            }
        }

        // Build conversation history for Gemini — include recent images for multimodal context
        $historyMessages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->where('id', '<', $userMsg->id)
            ->orderBy('created_at')
            ->get();

        // Identify recent user messages with images (last 3) for inline inclusion
        $recentImageMsgIds = $historyMessages
            ->filter(fn ($m) => $m->role === 'user' && ($m->image_url || $m->product_images))
            ->sortByDesc('id')
            ->take(3)
            ->pluck('id')
            ->toArray();

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
                        $resizedBase64 = $this->resizeImageForApi($fullPath, mime_content_type($fullPath) ?: 'image/jpeg');
                        $parts[] = [
                            'inline_data' => [
                                'mime_type' => 'image/jpeg',
                                'data'      => $resizedBase64,
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

        // If user uploaded an image, include it as inline data for Gemini multimodal
        if ($imageBase64 && $imageMime) {
            $currentParts[] = [
                'inline_data' => [
                    'mime_type' => $imageMime,
                    'data'      => $imageBase64,
                ],
            ];
        }

        // Product mode: include all product images as inline data
        if ($mode === 'product' && ! empty($productImagesData)) {
            foreach ($productImagesData as $pImg) {
                $currentParts[] = [
                    'inline_data' => [
                        'mime_type' => $pImg['mime'],
                        'data'      => $pImg['base64'],
                    ],
                ];
            }
        }

        $gemini = new GeminiService($mode);
        $startedAt = now();
        $result = $gemini->chatWithParts($history, $currentParts);
        $completedAt = now();
        $processingTimeMs = (int) round($startedAt->diffInMilliseconds($completedAt));

        // ── Create AiRequest tracking record ──
        $aiRequest = AiRequest::create([
            'user_id'          => $request->user()->id,
            'subscription_id'  => $consumption['subscription']?->id,
            'visual_style_id'  => isset($style) ? $style->id : null,
            'type'             => $imageBase64 ? 'multimodal' : ($mode === 'image' ? 'text_to_image' : ($styleSlug ? 'styled_chat' : 'chat')),
            'status'           => $result['success'] ? 'completed' : 'failed',
            'user_prompt'      => $userContent,
            'processed_prompt' => $geminiPrompt !== $userContent ? $geminiPrompt : null,
            'model_used'       => $gemini->getModel(),
            'engine_provider'  => 'gemini',
            'credits_consumed' => $creditCost,
            'input_image_path' => $imageUrl,
            'processing_time_ms' => $processingTimeMs,
            'ip_address'       => $request->ip(),
            'user_agent'       => $request->userAgent(),
            'error_message'    => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
            'started_at'       => $startedAt,
            'completed_at'     => $completedAt,
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
            ->where('role', 'user')
            ->where('id', '<', $aiMessage->id)
            ->orderByDesc('id')
            ->first();

        if (! $userMessage) {
            return response()->json(['success' => false, 'message' => 'Original prompt not found.'], 404);
        }

        // ── Quota enforcement ──
        $usageService = new UsageService();

        $featureSlug = $aiMessage->image_url ? 'text_to_image' : 'chat';
        $featureCheck = $usageService->checkFeatureLimit($request->user(), $featureSlug);
        if (! $featureCheck['allowed']) {
            return response()->json([
                'success'    => false,
                'message'    => 'Feature limit reached.',
                'error_code' => $featureCheck['reason'],
            ], 402);
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

        $originalAiRequest = $this->findRelatedAiRequest($request->user()->id, $userMessage, $aiMessage);

        // Rebuild prompt using the original processed prompt whenever available.
        $content = $userMessage->content;
        $imageStyle = $userMessage->image_style;
        $mode = $this->inferRegenerateMode($originalAiRequest, $userMessage, $aiMessage);
        $geminiPrompt = $originalAiRequest?->processed_prompt ?: $content;

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

    private function findRelatedAiRequest(int $userId, ConversationMessage $userMessage, ConversationMessage $aiMessage): ?AiRequest
    {
        if ($aiMessage->ai_request_id) {
            return AiRequest::find($aiMessage->ai_request_id);
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
            if ($originalAiRequest->output_image_path) {
                return 'image';
            }

            if (in_array($originalAiRequest->type, ['text_to_image', 'image_to_image'], true)) {
                return 'image';
            }
        }

        if ($aiMessage->image_url || $userMessage->image_style || $userMessage->image_url) {
            return 'image';
        }

        return 'text';
    }

    /**
     * Resize an image to a max 1024px dimension and return base64-encoded JPEG.
     * This keeps the Gemini API payload small and prevents request-too-large errors.
     */
    private function resizeImageForApi(string $filePath, string $mimeType, int $maxDim = 1024, int $quality = 80): string
    {
        $src = null;

        switch (strtolower($mimeType)) {
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
            // Fallback: raw base64 if GD can't read it
            return base64_encode(file_get_contents($filePath));
        }

        $w = imagesx($src);
        $h = imagesy($src);

        if ($w <= $maxDim && $h <= $maxDim) {
            // Already small enough — just re-encode as JPEG
            ob_start();
            imagejpeg($src, null, $quality);
            $data = ob_get_clean();
            imagedestroy($src);
            return base64_encode($data);
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
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        ob_start();
        imagejpeg($dst, null, $quality);
        $data = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return base64_encode($data);
    }
}
