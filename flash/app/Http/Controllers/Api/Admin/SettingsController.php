<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SettingsManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsManagementService $settings,
    ) {}

    // ──────────────────────────────────────────────
    //  AUDIT LOG
    // ──────────────────────────────────────────────

    public function auditLog(Request $request): JsonResponse
    {
        $filters = $request->only(['action', 'per_page', 'page']);
        $log = $this->settings->getAuditLog($filters);

        return response()->json([
            'success' => true,
            'data'    => $log->items(),
            'meta'    => [
                'current_page' => $log->currentPage(),
                'last_page'    => $log->lastPage(),
                'per_page'     => $log->perPage(),
                'total'        => $log->total(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  MAINTENANCE MODE
    // ──────────────────────────────────────────────

    public function maintenanceStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->settings->getMaintenanceStatus(),
        ]);
    }

    public function toggleMaintenance(): JsonResponse
    {
        $data = $this->settings->toggleMaintenance(auth()->id());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function updateMaintenance(Request $request): JsonResponse
    {
        $request->validate([
            'message'     => 'nullable|string|max:500',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
        ]);

        $data = $this->settings->updateMaintenanceSettings(
            auth()->id(),
            $request->only(['message', 'allowed_ips'])
        );

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    //  AI INTEGRATIONS
    // ──────────────────────────────────────────────

    public function aiIntegrations(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->settings->getAiIntegrations(),
        ]);
    }

    public function updateAiIntegrations(Request $request): JsonResponse
    {
        $request->validate([
            'settings'        => 'required|array',
            'settings.*.key'  => 'required|string',
            'settings.*.value'=> 'present',
        ]);

        $result = $this->settings->updateAiIntegrations(
            $request->input('settings'),
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    public function testAiIntegration(Request $request): JsonResponse
    {
        $request->validate([
            'integration' => 'required|string|in:openai,stability_ai',
        ]);

        $result = $this->settings->testIntegration(
            $request->input('integration'),
            auth()->id()
        );

        return response()->json([
            'success' => $result['success'],
            'data'    => $result,
        ]);
    }
}
