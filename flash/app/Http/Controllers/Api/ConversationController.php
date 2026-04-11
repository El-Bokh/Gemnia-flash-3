<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Models\Conversation;
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

                $productImagesData[] = [
                    'url'    => '/storage/' . $path,
                    'base64' => base64_encode(file_get_contents($file->getRealPath())),
                    'mime'   => $file->getMimeType(),
                ];
            }

            // Use first image URL for the message display
            if (! empty($productImagesData)) {
                $imageUrl = $productImagesData[0]['url'];
            }
        }

        // Store user message
        $userMsg = $conversation->messages()->create([
            'role'        => 'user',
            'content'     => $data['content'],
            'image_url'   => $imageUrl,
            'image_style' => $data['image_style'] ?? null,
            'status'      => 'sent',
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

            // User prompt
            $parts[] = $userContent;

            // Quality enhancement suffix
            $parts[] = 'high quality, highly detailed, professional';

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

        // Build conversation history for Gemini (use raw content for older messages)
        $history = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->where('id', '<', $userMsg->id) // exclude current message
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

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
                'role'    => 'assistant',
                'content' => 'عذراً، حدث خطأ أثناء معالجة طلبك. يرجى المحاولة مرة أخرى.',
                'status'  => 'error',
            ]);
        }

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
                'conversation' => $conversation->fresh(),
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

        // Rebuild prompt
        $content = $userMessage->content;
        $imageStyle = $userMessage->image_style;
        $mode = $aiMessage->image_url ? 'image' : 'text';
        $geminiPrompt = $content;

        $style = null;
        if ($mode === 'image') {
            $parts = [];
            if ($imageStyle) {
                $style = VisualStyle::where('slug', $imageStyle)->where('is_active', true)->first();
                if ($style && $style->prompt_prefix) {
                    $parts[] = $style->prompt_prefix;
                }
            }
            $parts[] = $content;
            $parts[] = 'high quality, highly detailed, professional';
            if ($style && $style->prompt_suffix) {
                $parts[] = $style->prompt_suffix;
            }
            $geminiPrompt = implode(', ', $parts);
        }

        // Build history (exclude the AI message being regenerated)
        $history = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->where('id', '<', $aiMessage->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $currentParts = [['text' => $geminiPrompt]];

        // If user had uploaded an image, re-include it
        if ($userMessage->image_url) {
            $imagePath = str_replace('/storage/', '', $userMessage->image_url);
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
            'started_at'         => $startedAt,
            'completed_at'       => $completedAt,
        ]);

        // Delete old AI message
        $aiMessage->delete();

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
                    'type' => 'image_generation',
                ]);
            }

            $newAiMsg = $conversation->messages()->create([
                'role'      => 'assistant',
                'content'   => $result['content'] ?? ($generatedImageUrl ? '' : 'No response generated.'),
                'image_url' => $generatedImageUrl,
                'status'    => 'sent',
            ]);
        } else {
            $usageService->refund($request->user(), $creditCost, 'ai_failure', [
                'ai_request_id' => $aiRequest->id,
            ]);
            $consumption['remaining'] += $creditCost;
            $aiRequest->update(['credits_consumed' => 0]);

            $newAiMsg = $conversation->messages()->create([
                'role'    => 'assistant',
                'content' => 'عذراً، حدث خطأ أثناء إعادة التوليد. يرجى المحاولة مرة أخرى.',
                'status'  => 'error',
            ]);
        }

        $quotaStats = $usageService->computeWarningLevel(
            $consumption['remaining'],
            $consumption['subscription']->credits_total ?? 0
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'ai_message'   => $newAiMsg,
                'conversation' => $conversation->fresh(),
            ],
            'quota' => [
                'remaining' => $consumption['remaining'],
                'warning'   => $quotaStats,
            ],
        ]);
    }
}
