<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\AssignPermissionsRequest;
use App\Http\Requests\Admin\Role\ListRolesRequest;
use App\Http\Requests\Admin\Role\StoreRoleRequest;
use App\Http\Requests\Admin\Role\UpdateRoleRequest;
use App\Http\Resources\Admin\RoleDetailResource;
use App\Http\Resources\Admin\RoleResource;
use App\Services\Admin\RolePermissionService;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(
        private readonly RolePermissionService $service,
    ) {}

    // ──────────────────────────────────────────────
    //  LIST (GET /api/admin/roles)
    // ──────────────────────────────────────────────

    public function index(ListRolesRequest $request): JsonResponse
    {
        $roles = $this->service->listRoles($request->validated());

        return response()->json([
            'success' => true,
            'data'    => RoleResource::collection($roles),
        ]);
    }

    // ──────────────────────────────────────────────
    //  SHOW (GET /api/admin/roles/{role})
    // ──────────────────────────────────────────────

    public function show(int $role): JsonResponse
    {
        $detail = $this->service->getRoleDetail($role);

        return response()->json([
            'success' => true,
            'data'    => new RoleDetailResource($detail),
        ]);
    }

    // ──────────────────────────────────────────────
    //  CREATE (POST /api/admin/roles)
    // ──────────────────────────────────────────────

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->service->createRole($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data'    => new RoleResource($role),
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (PUT /api/admin/roles/{role})
    // ──────────────────────────────────────────────

    public function update(UpdateRoleRequest $request, int $role): JsonResponse
    {
        $updated = $this->service->updateRole($role, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data'    => new RoleResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE (DELETE /api/admin/roles/{role})
    // ──────────────────────────────────────────────

    public function destroy(int $role): JsonResponse
    {
        $this->service->deleteRole($role);

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  ASSIGN PERMISSIONS (PUT /api/admin/roles/{role}/permissions)
    // ──────────────────────────────────────────────

    public function assignPermissions(AssignPermissionsRequest $request, int $role): JsonResponse
    {
        $updated = $this->service->assignPermissions($role, $request->validated('permissions'));

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned successfully.',
            'data'    => new RoleResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  PERMISSION MATRIX (GET /api/admin/roles/matrix)
    // ──────────────────────────────────────────────

    public function matrix(): JsonResponse
    {
        $data = $this->service->getPermissionMatrix();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
