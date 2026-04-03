<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    /**
     * GET /api/admin/dashboard
     *
     * Full overview: KPIs + Charts + Recent activity + Alerts.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboard->getFullOverview(),
        ]);
    }

    /**
     * GET /api/admin/dashboard/kpis
     *
     * KPI widgets only.
     */
    public function kpis(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboard->getKpis(),
        ]);
    }

    /**
     * GET /api/admin/dashboard/charts
     *
     * Chart data only (Pie, Line, Bar).
     */
    public function charts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboard->getCharts(),
        ]);
    }

    /**
     * GET /api/admin/dashboard/recent-ai-requests
     *
     * Last N AI requests.
     */
    public function recentAiRequests(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = min(max($limit, 1), 50);

        return response()->json([
            'success' => true,
            'data'    => $this->dashboard->getRecentAiRequests($limit),
        ]);
    }

    /**
     * GET /api/admin/dashboard/recent-payments
     *
     * Last N payments.
     */
    public function recentPayments(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = min(max($limit, 1), 50);

        return response()->json([
            'success' => true,
            'data'    => $this->dashboard->getRecentPayments($limit),
        ]);
    }

    /**
     * GET /api/admin/dashboard/alerts
     *
     * Admin notifications & system alerts.
     */
    public function alerts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dashboard->getAdminAlerts(),
        ]);
    }
}
