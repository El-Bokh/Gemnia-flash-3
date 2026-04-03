<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\AssignRoleRequest;
use App\Http\Requests\Admin\User\ListUsersRequest;
use App\Http\Requests\Admin\User\ResetPasswordRequest;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Http\Resources\Admin\UserCollection;
use App\Http\Resources\Admin\UserDetailResource;
use App\Http\Resources\Admin\UserResource;
use App\Services\Admin\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserManagementService $service,
    ) {}

    // ──────────────────────────────────────────────
    //  LIST (GET /api/admin/users)
    // ──────────────────────────────────────────────

    public function index(ListUsersRequest $request): UserCollection
    {
        $paginator = $this->service->list($request->validated());

        return new UserCollection($paginator);
    }

    // ──────────────────────────────────────────────
    //  SHOW (GET /api/admin/users/{user})
    // ──────────────────────────────────────────────

    public function show(int $user): UserDetailResource
    {
        $detail = $this->service->getDetail($user);

        return new UserDetailResource($detail);
    }

    // ──────────────────────────────────────────────
    //  CREATE (POST /api/admin/users)
    // ──────────────────────────────────────────────

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (PUT /api/admin/users/{user})
    // ──────────────────────────────────────────────

    public function update(UpdateUserRequest $request, int $user): UserResource
    {
        $updated = $this->service->update($user, $request->validated());

        return new UserResource($updated);
    }

    // ──────────────────────────────────────────────
    //  ARCHIVE / SOFT-DELETE (DELETE /api/admin/users/{user})
    // ──────────────────────────────────────────────

    public function destroy(int $user): JsonResponse
    {
        $this->service->archive($user);

        return response()->json([
            'success' => true,
            'message' => 'User archived successfully.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  FORCE DELETE (DELETE /api/admin/users/{user}/force)
    // ──────────────────────────────────────────────

    public function forceDelete(int $user): JsonResponse
    {
        $this->service->forceDelete($user);

        return response()->json([
            'success' => true,
            'message' => 'User permanently deleted.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  RESTORE (POST /api/admin/users/{user}/restore)
    // ──────────────────────────────────────────────

    public function restore(int $user): JsonResponse
    {
        $restored = $this->service->restore($user);

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully.',
            'data'    => new UserResource($restored),
        ]);
    }

    // ──────────────────────────────────────────────
    //  ASSIGN ROLES (PUT /api/admin/users/{user}/roles)
    // ──────────────────────────────────────────────

    public function assignRoles(AssignRoleRequest $request, int $user): JsonResponse
    {
        $updated = $this->service->assignRoles($user, $request->validated('roles'));

        return response()->json([
            'success' => true,
            'message' => 'Roles assigned successfully.',
            'data'    => new UserResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  RESET PASSWORD (POST /api/admin/users/{user}/reset-password)
    // ──────────────────────────────────────────────

    public function resetPassword(ResetPasswordRequest $request, int $user): JsonResponse
    {
        $validated = $request->validated();

        $this->service->resetPassword(
            $user,
            $validated['password'],
            $validated['revoke_tokens'] ?? false,
        );

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS (GET /api/admin/users/aggregations)
    // ──────────────────────────────────────────────

    public function aggregations(): JsonResponse
    {
        $data = $this->service->getAggregations();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    //  USER AI REQUESTS (GET /api/admin/users/{user}/ai-requests)
    // ──────────────────────────────────────────────

    public function aiRequests(Request $request, int $user): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $paginator = $this->service->getUserAiRequests($user, $perPage);

        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  USER GENERATED IMAGES (GET /api/admin/users/{user}/generated-images)
    // ──────────────────────────────────────────────

    public function generatedImages(Request $request, int $user): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $paginator = $this->service->getUserGeneratedImages($user, $perPage);

        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }
}
