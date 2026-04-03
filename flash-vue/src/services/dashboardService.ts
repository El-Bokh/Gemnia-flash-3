// ═══════════════════════════════════════════════════════════
//  Dashboard Service
//  Backend: app/Http/Controllers/Api/Admin/DashboardController.php
//  Routes:  /api/admin/dashboard/*
//
//  كل function هنا = Endpoint في الـ Backend
// ═══════════════════════════════════════════════════════════

import { apiGet } from '@/api/client'
import type { ApiResponse } from '@/api/client'
import type {
  DashboardOverview,
  DashboardKpis,
  DashboardCharts,
  RecentAiRequest,
  RecentPayment,
  DashboardAlerts,
} from '@/types/dashboard'

const PREFIX = '/admin/dashboard'

// ─── GET /api/admin/dashboard ───────────────────────────────
// Full overview: KPIs + Charts + Recent Activity + Alerts
export function getFullOverview() {
  return apiGet<ApiResponse<DashboardOverview>>(PREFIX)
}

// ─── GET /api/admin/dashboard/kpis ──────────────────────────
// Users KPIs, Subscriptions per plan, Images, Revenue, AI Requests
export function getKpis() {
  return apiGet<ApiResponse<DashboardKpis>>(`${PREFIX}/kpis`)
}

// ─── GET /api/admin/dashboard/charts ────────────────────────
// Subscriptions pie chart, Images line chart (7 days), AI requests bar chart
export function getCharts() {
  return apiGet<ApiResponse<DashboardCharts>>(`${PREFIX}/charts`)
}

// ─── GET /api/admin/dashboard/recent-ai-requests?limit=10 ───
// Recent AI requests with user, style, model, credits, processing time
export function getRecentAiRequests(limit: number = 10) {
  return apiGet<ApiResponse<RecentAiRequest[]>>(`${PREFIX}/recent-ai-requests`, {
    params: { limit },
  })
}

// ─── GET /api/admin/dashboard/recent-payments?limit=10 ──────
// Recent payments with user, plan, amount, method, status
export function getRecentPayments(limit: number = 10) {
  return apiGet<ApiResponse<RecentPayment[]>>(`${PREFIX}/recent-payments`, {
    params: { limit },
  })
}

// ─── GET /api/admin/dashboard/alerts ────────────────────────
// Failed requests, pending payments, system alerts (low credits, expiring subs)
export function getAlerts() {
  return apiGet<ApiResponse<DashboardAlerts>>(`${PREFIX}/alerts`)
}
