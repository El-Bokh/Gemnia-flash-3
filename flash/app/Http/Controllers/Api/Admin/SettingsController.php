<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Setting\BulkUpdateSettingsRequest;
use App\Http\Requests\Admin\Setting\CreateSettingRequest;
use App\Http\Requests\Admin\Setting\ListSettingsRequest;
use App\Http\Requests\Admin\Setting\TestIntegrationRequest;
use App\Http\Requests\Admin\Setting\UpdateSettingRequest;
use App\Http\Resources\Admin\SettingGroupResource;
use App\Http\Resources\Admin\SettingResource;
use App\Models\Setting;
use App\Services\Admin\SettingsManagementService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsManagementService $service,
    ) {}

    // ──────────────────────────────────────────────
    //  LIST (GET /api/admin/settings)
    //  Returns settings grouped by their group field.
    // ──────────────────────────────────────────────

    public function index(ListSettingsRequest $request): JsonResponse
    {
        $grouped = $this->service->list($request->validated());

        $data = $grouped->map(function ($settings, $group) {
            return [
                'group'    => $group,
                'count'    => $settings->count(),
                'settings' => SettingResource::collection($settings),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    //  SHOW (GET /api/admin/settings/{setting})
    // ──────────────────────────────────────────────

    public function show(Setting $setting): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new SettingResource($setting),
        ]);
    }

    // ──────────────────────────────────────────────
    //  CREATE (POST /api/admin/settings)
    // ──────────────────────────────────────────────

    public function store(CreateSettingRequest $request): JsonResponse
    {
        $setting = $this->service->create($request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Setting created successfully.',
            'data'    => new SettingResource($setting),
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  UPDATE (PUT /api/admin/settings/{setting})
    // ──────────────────────────────────────────────

    public function update(UpdateSettingRequest $request, Setting $setting): JsonResponse
    {
        $updated = $this->service->update($setting, $request->validated('value'), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => "Setting '{$setting->key}' updated successfully.",
            'data'    => new SettingResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    //  BULK UPDATE (PUT /api/admin/settings/bulk)
    // ──────────────────────────────────────────────

    public function bulkUpdate(BulkUpdateSettingsRequest $request): JsonResponse
    {
        $result = $this->service->bulkUpdate($request->validated('settings'), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => count($result['updated']) . ' setting(s) updated successfully.',
            'data'    => [
                'updated' => SettingResource::collection($result['updated']),
                'errors'  => $result['errors'],
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  TOGGLE (POST /api/admin/settings/{setting}/toggle)
    // ──────────────────────────────────────────────

    public function toggle(Setting $setting): JsonResponse
    {
        $toggled = $this->service->toggle($setting, request()->user()->id);

        $state = $toggled->getTypedValue() ? 'enabled' : 'disabled';

        return response()->json([
            'success' => true,
            'message' => "'{$setting->display_name}' is now {$state}.",
            'data'    => new SettingResource($toggled),
        ]);
    }

    // ──────────────────────────────────────────────
    //  RESET GROUP (POST /api/admin/settings/reset/{group})
    // ──────────────────────────────────────────────

    public function resetGroup(string $group): JsonResponse
    {
        $settings = $this->service->resetGroup($group, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => "All settings in '{$group}' group have been reset to defaults.",
            'data'    => SettingResource::collection($settings),
        ]);
    }

    // ──────────────────────────────────────────────
    //  MAINTENANCE MODE (GET + POST)
    // ──────────────────────────────────────────────

    public function maintenanceStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getMaintenanceStatus(),
        ]);
    }

    public function toggleMaintenance(): JsonResponse
    {
        $status = $this->service->toggleMaintenance(request()->user()->id);

        $state = $status['is_enabled'] ? 'enabled' : 'disabled';

        return response()->json([
            'success' => true,
            'message' => "Maintenance mode {$state}.",
            'data'    => $status,
        ]);
    }

    // ──────────────────────────────────────────────
    //  TEST INTEGRATION (POST /api/admin/settings/test-integration)
    // ──────────────────────────────────────────────

    public function testIntegration(TestIntegrationRequest $request): JsonResponse
    {
        $result = $this->service->testIntegration(
            $request->validated('integration'),
            $request->user()->id
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data'    => $result,
        ]);
    }

    // ──────────────────────────────────────────────
    //  AUDIT LOG (GET /api/admin/settings/audit-log)
    // ──────────────────────────────────────────────

    public function auditLog(): JsonResponse
    {
        $paginator = $this->service->getAuditLog(request()->all());

        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE (DELETE /api/admin/settings/{setting})
    // ──────────────────────────────────────────────

    public function destroy(Setting $setting): JsonResponse
    {
        $this->service->delete($setting, request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => "Setting '{$setting->key}' deleted.",
        ]);
    }

    // ──────────────────────────────────────────────
    //  PUBLIC SETTINGS (for frontend, no auth needed)
    // ──────────────────────────────────────────────

    public function publicSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getPublicSettings(),
        ]);
    }
}
