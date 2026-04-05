<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Models\Conversation;
use App\Models\MediaFile;
use App\Models\VisualStyle;
use App\Services\GeminiService;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $consumption = $usageService->consume($request->user(), 1, 'chat_message');

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
            'mode'        => ['nullable', 'string', 'in:text,image'],
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

        // Build the prompt — inject hidden style prompts if a style is selected
        $userContent = $data['content'];
        $styleSlug = $data['image_style'] ?? null;
        $geminiPrompt = $userContent;

        if ($styleSlug) {
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
            'credits_consumed' => 1,
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

                // Update AiRequest type when images were generated
                if ($aiRequest->type === 'chat' || $aiRequest->type === 'styled_chat') {
                    $aiRequest->update(['type' => 'text_to_image']);
                }
            }

            $aiMsg = $conversation->messages()->create([
                'role'      => 'assistant',
                'content'   => $result['content'],
                'image_url' => $generatedImageUrl,
                'status'    => 'sent',
            ]);
        } else {
            $aiMsg = $conversation->messages()->create([
                'role'    => 'assistant',
                'content' => 'عذراً، حدث خطأ أثناء معالجة طلبك. يرجى المحاولة مرة أخرى.',
                'status'  => 'error',
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'user_message' => $userMsg,
                'ai_message'   => $aiMsg,
                'conversation' => $conversation->fresh(),
            ],
            'quota' => [
                'remaining' => $consumption['remaining'],
                'warning'   => $consumption['remaining'] <= 0 ? 'depleted'
                    : ($consumption['remaining'] <= (($consumption['subscription']->credits_total ?? 0) * 0.1) ? 'critical'
                    : ($consumption['remaining'] <= (($consumption['subscription']->credits_total ?? 0) * 0.2) ? 'low'
                    : 'none')),
            ],
        ]);
    }
}
