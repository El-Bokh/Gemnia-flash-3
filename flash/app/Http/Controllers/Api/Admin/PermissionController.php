<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PermissionResource;
use App\Services\Admin\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function __construct(
        private readonly RolePermissionService $service,
    ) {}

    // ──────────────────────────────────────────────
    //  LIST ALL (GET /api/admin/permissions)
    // ──────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $permissions = $this->service->listPermissions();

        return response()->json([
            'success' => true,
            'data'    => PermissionResource::collection($permissions),
        ]);
    }

    // ──────────────────────────────────────────────
    //  LIST GROUPED (GET /api/admin/permissions/grouped)
    // ──────────────────────────────────────────────

    public function grouped(): JsonResponse
    {
        $data = $this->service->listPermissionsGrouped();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    //  CREATE (POST /api/admin/permissions)
    // ──────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:permissions,name'],
            'slug'        => ['required', 'string', 'max:100', 'unique:permissions,slug', 'regex:/^[a-z0-9_]+$/'],
            'group'       => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $permission = $this->service->createPermission($validated);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully.',
            'data'    => new PermissionResource($permission),
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (PUT /api/admin/permissions/{permission})
    // ──────────────────────────────────────────────

    public function update(Request $request, int $permission): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100', Rule::unique('permissions', 'name')->ignore($permission)],
            'slug'        => ['sometimes', 'string', 'max:100', Rule::unique('permissions', 'slug')->ignore($permission), 'regex:/^[a-z0-9_]+$/'],
            'group'       => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->service->updatePermission($permission, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully.',
            'data'    => new PermissionResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE (DELETE /api/admin/permissions/{permission})
    // ──────────────────────────────────────────────

    public function destroy(int $permission): JsonResponse
    {
        $this->service->deletePermission($permission);

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully.',
        ]);
    }
}
