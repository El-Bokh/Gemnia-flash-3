<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\VisualStyle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

class VisualStyleController extends Controller
{
    /**
     * GET /api/admin/styles
     * List all styles with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = VisualStyle::query();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by premium
        if ($request->has('is_premium')) {
            $query->where('is_premium', filter_var($request->input('is_premium'), FILTER_VALIDATE_BOOLEAN));
        }

        // Include soft-deleted
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        // Sort
        $sortField = $request->input('sort_field', 'sort_order');
        $sortOrder = $request->input('sort_order', 'asc');
        $allowed   = ['name', 'slug', 'category', 'sort_order', 'is_active', 'is_premium', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowed)) {
            $query->orderBy($sortField, $sortOrder === 'desc' ? 'desc' : 'asc');
        }

        $styles = $query->withCount('aiRequests')->get();

        // Categories list for filters
        $categories = VisualStyle::query()
            ->select('category')
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category')
            ->sort()
            ->values();

        return response()->json([
            'success'    => true,
            'data'       => $styles,
            'categories' => $categories,
        ]);
    }

    /**
     * GET /api/admin/styles/{id}
     */
    public function show(int $id): JsonResponse
    {
        $style = VisualStyle::withTrashed()
            ->withCount('aiRequests')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $style]);
    }

    /**
     * POST /api/admin/styles
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'slug'            => ['nullable', 'string', 'max:100', 'unique:visual_styles,slug'],
            'description'     => ['nullable', 'string', 'max:500'],
            'prompt_prefix'   => ['nullable', 'string', 'max:2000'],
            'prompt_suffix'   => ['nullable', 'string', 'max:2000'],
            'negative_prompt' => ['nullable', 'string', 'max:2000'],
            'category'        => ['nullable', 'string', 'max:50'],
            'is_active'       => ['boolean'],
            'is_premium'      => ['boolean'],
            'sort_order'      => ['integer', 'min:0'],
            'settings'        => ['nullable', 'array'],
            'metadata'        => ['nullable', 'array'],
            'thumbnail'       => ['nullable', 'image', 'max:4096'],
        ]);

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            // Ensure uniqueness
            $base = $validated['slug'];
            $i = 1;
            while (VisualStyle::withTrashed()->where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $base . '-' . $i++;
            }
        }

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $this->uploadThumbnail($request->file('thumbnail'), $validated['slug']);
        }
        unset($validated['thumbnail_file']);

        $style = VisualStyle::create($validated);

        Log::info('Visual style created', ['id' => $style->id, 'name' => $style->name]);

        return response()->json([
            'success' => true,
            'data'    => $style,
            'message' => 'Style created successfully',
        ], 201);
    }

    /**
     * PUT /api/admin/styles/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $style = VisualStyle::findOrFail($id);

        $validated = $request->validate([
            'name'            => ['sometimes', 'string', 'max:100'],
            'slug'            => ['sometimes', 'string', 'max:100', 'unique:visual_styles,slug,' . $id],
            'description'     => ['nullable', 'string', 'max:500'],
            'prompt_prefix'   => ['nullable', 'string', 'max:2000'],
            'prompt_suffix'   => ['nullable', 'string', 'max:2000'],
            'negative_prompt' => ['nullable', 'string', 'max:2000'],
            'category'        => ['nullable', 'string', 'max:50'],
            'is_active'       => ['boolean'],
            'is_premium'      => ['boolean'],
            'sort_order'      => ['integer', 'min:0'],
            'settings'        => ['nullable', 'array'],
            'metadata'        => ['nullable', 'array'],
            'thumbnail'       => ['nullable', 'image', 'max:4096'],
        ]);

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail if it was uploaded (not from public/)
            $this->deleteOldThumbnail($style->thumbnail);
            $slug = $validated['slug'] ?? $style->slug;
            $validated['thumbnail'] = $this->uploadThumbnail($request->file('thumbnail'), $slug);
        }

        $style->update($validated);

        Log::info('Visual style updated', ['id' => $style->id, 'name' => $style->name]);

        return response()->json([
            'success' => true,
            'data'    => $style->fresh(),
            'message' => 'Style updated successfully',
        ]);
    }

    /**
     * DELETE /api/admin/styles/{id}
     * Soft-delete.
     */
    public function destroy(int $id): JsonResponse
    {
        $style = VisualStyle::findOrFail($id);
        $style->delete();

        Log::info('Visual style soft-deleted', ['id' => $style->id, 'name' => $style->name]);

        return response()->json([
            'success' => true,
            'message' => 'Style deleted successfully',
        ]);
    }

    /**
     * DELETE /api/admin/styles/{id}/force
     */
    public function forceDelete(int $id): JsonResponse
    {
        $style = VisualStyle::withTrashed()->findOrFail($id);

        // Delete uploaded thumbnail
        $this->deleteOldThumbnail($style->thumbnail);

        $style->forceDelete();

        Log::info('Visual style permanently deleted', ['id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Style permanently deleted',
        ]);
    }

    /**
     * POST /api/admin/styles/{id}/restore
     */
    public function restore(int $id): JsonResponse
    {
        $style = VisualStyle::withTrashed()->findOrFail($id);
        $style->restore();

        return response()->json([
            'success' => true,
            'data'    => $style->fresh(),
            'message' => 'Style restored successfully',
        ]);
    }

    /**
     * POST /api/admin/styles/{id}/toggle-active
     */
    public function toggleActive(int $id): JsonResponse
    {
        $style = VisualStyle::findOrFail($id);
        $style->update(['is_active' => ! $style->is_active]);

        return response()->json([
            'success' => true,
            'data'    => $style->fresh(),
            'message' => $style->is_active ? 'Style activated' : 'Style deactivated',
        ]);
    }

    /**
     * POST /api/admin/styles/{id}/duplicate
     */
    public function duplicate(int $id): JsonResponse
    {
        $original = VisualStyle::findOrFail($id);

        $slug = $original->slug . '-copy';
        $i = 1;
        while (VisualStyle::where('slug', $slug)->exists()) {
            $slug = $original->slug . '-copy-' . $i++;
        }

        $copy = $original->replicate();
        $copy->name      = $original->name . ' (Copy)';
        $copy->slug      = $slug;
        $copy->is_active = false;
        $copy->save();

        Log::info('Visual style duplicated', ['original' => $original->id, 'copy' => $copy->id]);

        return response()->json([
            'success' => true,
            'data'    => $copy,
            'message' => 'Style duplicated successfully',
        ], 201);
    }

    /**
     * POST /api/admin/styles/{id}/upload-thumbnail
     * Standalone thumbnail upload endpoint.
     */
    public function uploadThumbnailEndpoint(Request $request, int $id): JsonResponse
    {
        $style = VisualStyle::findOrFail($id);

        $request->validate([
            'thumbnail' => ['required', 'image', 'max:4096'],
        ]);

        $this->deleteOldThumbnail($style->thumbnail);

        $path = $this->uploadThumbnail($request->file('thumbnail'), $style->slug);
        $style->update(['thumbnail' => $path]);

        return response()->json([
            'success' => true,
            'data'    => $style->fresh(),
            'message' => 'Thumbnail updated successfully',
        ]);
    }

    /**
     * POST /api/admin/styles/reorder
     * Bulk update sort_order.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'            => ['required', 'array', 'min:1'],
            'items.*.id'       => ['required', 'integer', 'exists:visual_styles,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                VisualStyle::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Styles reordered successfully',
        ]);
    }

    // ─── Private Helpers ────────────────────────────────

    private function uploadThumbnail(UploadedFile $file, string $slug): string
    {
        $filename = $slug . '-' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('style-thumbnails', $filename, 'public');

        return '/storage/' . $path;
    }

    private function deleteOldThumbnail(?string $thumbnailPath): void
    {
        if (! $thumbnailPath) {
            return;
        }

        // Only delete if it's an uploaded file (in storage/), not a static public asset
        if (str_starts_with($thumbnailPath, '/storage/')) {
            $relative = str_replace('/storage/', '', $thumbnailPath);
            Storage::disk('public')->delete($relative);
        }
    }
}
