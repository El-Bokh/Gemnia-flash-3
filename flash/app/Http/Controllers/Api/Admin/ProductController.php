<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * GET /api/admin/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('is_premium')) {
            $query->where('is_premium', filter_var($request->input('is_premium'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $sortField = $request->input('sort_field', 'sort_order');
        $sortOrder = $request->input('sort_order', 'asc');
        $allowed   = ['name', 'slug', 'category', 'sort_order', 'is_active', 'is_premium', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowed)) {
            $query->orderBy($sortField, $sortOrder === 'desc' ? 'desc' : 'asc');
        }

        $products = $query->withCount('aiRequests')->get();

        $categories = Product::query()
            ->select('category')
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category')
            ->sort()
            ->values();

        return response()->json([
            'success'    => true,
            'data'       => $products,
            'categories' => $categories,
        ]);
    }

    /**
     * GET /api/admin/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::withTrashed()
            ->withCount('aiRequests')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $product]);
    }

    /**
     * POST /api/admin/products
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'slug'            => ['nullable', 'string', 'max:100', 'unique:products,slug'],
            'description'     => ['nullable', 'string', 'max:500'],
            'hidden_prompt'   => ['nullable', 'string', 'max:5000'],
            'negative_prompt' => ['nullable', 'string', 'max:2000'],
            'category'        => ['nullable', 'string', 'max:50'],
            'is_active'       => ['boolean'],
            'is_premium'      => ['boolean'],
            'sort_order'      => ['integer', 'min:0'],
            'settings'        => ['nullable', 'array'],
            'metadata'        => ['nullable', 'array'],
            'thumbnail'       => ['nullable', 'image', 'max:4096'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            $base = $validated['slug'];
            $i = 1;
            while (Product::withTrashed()->where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $base . '-' . $i++;
            }
        }

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $this->uploadThumbnail($request->file('thumbnail'), $validated['slug']);
        }

        $product = Product::create($validated);

        Log::info('Product created', ['id' => $product->id, 'name' => $product->name]);

        return response()->json([
            'success' => true,
            'data'    => $product,
            'message' => 'Product created successfully',
        ], 201);
    }

    /**
     * PUT /api/admin/products/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'            => ['sometimes', 'string', 'max:100'],
            'slug'            => ['sometimes', 'string', 'max:100', 'unique:products,slug,' . $id],
            'description'     => ['nullable', 'string', 'max:500'],
            'hidden_prompt'   => ['nullable', 'string', 'max:5000'],
            'negative_prompt' => ['nullable', 'string', 'max:2000'],
            'category'        => ['nullable', 'string', 'max:50'],
            'is_active'       => ['boolean'],
            'is_premium'      => ['boolean'],
            'sort_order'      => ['integer', 'min:0'],
            'settings'        => ['nullable', 'array'],
            'metadata'        => ['nullable', 'array'],
            'thumbnail'       => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('thumbnail')) {
            $this->deleteOldThumbnail($product->thumbnail);
            $slug = $validated['slug'] ?? $product->slug;
            $validated['thumbnail'] = $this->uploadThumbnail($request->file('thumbnail'), $slug);
        }

        $product->update($validated);

        Log::info('Product updated', ['id' => $product->id, 'name' => $product->name]);

        return response()->json([
            'success' => true,
            'data'    => $product->fresh(),
            'message' => 'Product updated successfully',
        ]);
    }

    /**
     * DELETE /api/admin/products/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        Log::info('Product soft-deleted', ['id' => $product->id, 'name' => $product->name]);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * DELETE /api/admin/products/{id}/force
     */
    public function forceDelete(int $id): JsonResponse
    {
        $product = Product::withTrashed()->findOrFail($id);
        $this->deleteOldThumbnail($product->thumbnail);
        $product->forceDelete();

        Log::info('Product permanently deleted', ['id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Product permanently deleted',
        ]);
    }

    /**
     * POST /api/admin/products/{id}/restore
     */
    public function restore(int $id): JsonResponse
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();

        return response()->json([
            'success' => true,
            'data'    => $product->fresh(),
            'message' => 'Product restored successfully',
        ]);
    }

    /**
     * POST /api/admin/products/{id}/toggle-active
     */
    public function toggleActive(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update(['is_active' => ! $product->is_active]);

        return response()->json([
            'success' => true,
            'data'    => $product->fresh(),
            'message' => $product->is_active ? 'Product activated' : 'Product deactivated',
        ]);
    }

    /**
     * POST /api/admin/products/{id}/duplicate
     */
    public function duplicate(int $id): JsonResponse
    {
        $original = Product::findOrFail($id);

        $slug = $original->slug . '-copy';
        $i = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $original->slug . '-copy-' . $i++;
        }

        $copy = $original->replicate();
        $copy->name      = $original->name . ' (Copy)';
        $copy->slug      = $slug;
        $copy->is_active = false;
        $copy->save();

        Log::info('Product duplicated', ['original' => $original->id, 'copy' => $copy->id]);

        return response()->json([
            'success' => true,
            'data'    => $copy,
            'message' => 'Product duplicated successfully',
        ], 201);
    }

    /**
     * POST /api/admin/products/{id}/upload-thumbnail
     */
    public function uploadThumbnailEndpoint(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'thumbnail' => ['required', 'image', 'max:4096'],
        ]);

        $this->deleteOldThumbnail($product->thumbnail);
        $path = $this->uploadThumbnail($request->file('thumbnail'), $product->slug);
        $product->update(['thumbnail' => $path]);

        return response()->json([
            'success' => true,
            'data'    => $product->fresh(),
            'message' => 'Thumbnail updated successfully',
        ]);
    }

    /**
     * POST /api/admin/products/reorder
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.id'         => ['required', 'integer', 'exists:products,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                Product::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Products reordered successfully',
        ]);
    }

    // ─── Private Helpers ────────────────────────────────

    private function uploadThumbnail(UploadedFile $file, string $slug): string
    {
        $filename = $slug . '-' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('product-thumbnails', $filename, 'public');

        return '/storage/' . $path;
    }

    private function deleteOldThumbnail(?string $thumbnailPath): void
    {
        if (! $thumbnailPath) {
            return;
        }

        if (str_starts_with($thumbnailPath, '/storage/')) {
            $relative = str_replace('/storage/', '', $thumbnailPath);
            Storage::disk('public')->delete($relative);
        }
    }
}
