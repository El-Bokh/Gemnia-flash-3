<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisualStyle;
use Illuminate\Http\JsonResponse;

class VisualStyleController extends Controller
{
    public function index(): JsonResponse
    {
        $styles = VisualStyle::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'description', 'thumbnail', 'category', 'is_premium', 'sort_order']);

        return response()->json([
            'success' => true,
            'data'    => $styles,
        ]);
    }
}
