// ═══════════════════════════════════════════════════════════
//  Dashboard Types — matches Laravel DashboardService responses
//  Backend: app/Services/Admin/DashboardService.php
// ═══════════════════════════════════════════════════════════

// ─── Shared sub-types ───────────────────────────────────────

export interface UserRef {
  id: number
  name: string
  email: string
  avatar: string | null
}

// ─── KPIs ───────────────────────────────────────────────────

export interface UserKpis {
  total: number
  active: number
  suspended: number
  pending: number
  new_today: number
  new_week: number
}

export interface SubscriptionPerPlan {
  id: number
  name: string
  slug: string
  active_count: number
  trial_count: number
  total_count: number
}

export interface RevenueKpi {
  total: number
  count: number
  currency: string
}

export interface DashboardKpis {
  users: UserKpis
  subscriptions_per_plan: SubscriptionPerPlan[]
  images_generated_today: number
  images_generated_week: number
  videos_generated_today: number
  videos_generated_week: number
  video_requests_processing: number
  revenue_today: RevenueKpi
  revenue_week: RevenueKpi
  ai_requests_pending: number
  ai_requests_processing: number
  ai_requests_completed: number
  ai_requests_failed: number
}

// ─── Charts ─────────────────────────────────────────────────

export interface PieChartItem {
  label: string
  value: number
}

export interface LineChartItem {
  date: string
  label: string
  count: number
}

export interface AiRequestsByStatus {
  pending?: number
  processing?: number
  completed?: number
  failed?: number
}

export interface DashboardCharts {
  subscriptions_by_plan: PieChartItem[]
  images_last_7_days: LineChartItem[]
  ai_requests_by_status: AiRequestsByStatus
}

// ─── Recent AI Requests ─────────────────────────────────────

export interface RecentAiRequest {
  id: number
  uuid: string
  user: UserRef | null
  prompt: string
  style: string | null
  type: string
  status: string
  model: string
  credits: number
  processing_ms: number | null
  date: string
}

// ─── Recent Payments ────────────────────────────────────────

export interface RecentPayment {
  id: number
  uuid: string
  user: UserRef | null
  plan: string | null
  amount: number
  net_amount: number
  currency: string
  payment_method: string
  status: string
  paid_at: string | null
  date: string
}

// ─── Alerts ─────────────────────────────────────────────────

export interface FailedRequestAlert {
  id: number
  user: string | null
  error: string | null
  date: string
}

export interface FailedRequestAlerts {
  count_today: number
  recent: FailedRequestAlert[]
}

export interface PendingPaymentAlert {
  id: number
  user: string | null
  amount: number
  currency: string
  status: string
  date: string
}

export interface PendingPaymentAlerts {
  count: number
  recent: PendingPaymentAlert[]
}

export interface SystemAlerts {
  low_credit_users: number
  subscriptions_expiring: number
}

export interface DashboardAlerts {
  failed_requests: FailedRequestAlerts
  pending_payments: PendingPaymentAlerts
  system: SystemAlerts
}

// ─── Full Overview ──────────────────────────────────────────

export interface DashboardOverview {
  kpis: DashboardKpis
  charts: DashboardCharts
  recent_ai_requests: RecentAiRequest[]
  recent_payments: RecentPayment[]
  alerts: DashboardAlerts
}
