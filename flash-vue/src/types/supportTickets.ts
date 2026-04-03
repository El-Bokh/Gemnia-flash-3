// ─── Support Ticket Types ───────────────────────────────

export interface TicketUser {
  id: number
  name: string
  email: string
  avatar: string | null
}

export interface TicketUserDetail extends TicketUser {
  phone: string | null
  status: string
  subscription: TicketUserSubscription | null
}

export interface TicketUserSubscription {
  id: number
  status: string
  billing_cycle: string
  starts_at: string | null
  ends_at: string | null
  plan: TicketSubscriptionPlan | null
}

export interface TicketSubscriptionPlan {
  id: number
  name: string
  slug: string
}

export interface TicketAgent {
  id: number
  name: string
  email: string
  avatar: string | null
}

export interface TicketListSubscription {
  id: number
  status: string
  billing_cycle: string
  plan: TicketSubscriptionPlan | null
}

export interface SupportTicket {
  id: number
  uuid: string
  ticket_number: string
  subject: string
  message_preview: string
  status: string
  priority: string
  category: string | null
  replies_count: number
  last_reply_at: string | null
  resolved_at: string | null
  closed_at: string | null
  created_at: string
  updated_at: string
  deleted_at: string | null
  user: TicketUser
  assigned_agent: TicketAgent | null
  user_subscription: TicketListSubscription | null
}

export interface SupportTicketReply {
  id: number
  message: string
  is_staff_reply: boolean
  attachments: string[] | null
  created_at: string
  updated_at: string
  user: TicketUser
}

export interface SupportTicketDetail {
  id: number
  uuid: string
  ticket_number: string
  subject: string
  message: string
  status: string
  priority: string
  category: string | null
  attachments: string[] | null
  metadata: Record<string, unknown> | null
  last_reply_at: string | null
  resolved_at: string | null
  closed_at: string | null
  created_at: string
  updated_at: string
  deleted_at: string | null
  replies_count: number
  user: TicketUserDetail
  assigned_agent: TicketAgent | null
  replies: SupportTicketReply[]
}

// ─── Request Params ─────────────────────────────────────

export interface ListSupportTicketsParams {
  search?: string
  user_id?: number
  assigned_to?: number
  status?: 'open' | 'in_progress' | 'waiting_reply' | 'resolved' | 'closed'
  priority?: 'low' | 'medium' | 'high' | 'urgent'
  category?: string
  unassigned?: boolean
  date_from?: string
  date_to?: string
  last_reply_from?: string
  last_reply_to?: string
  trashed?: 'with' | 'only'
  sort_by?: 'id' | 'ticket_number' | 'status' | 'priority' | 'created_at' | 'updated_at' | 'last_reply_at' | 'closed_at'
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface UpdateSupportTicketData {
  status?: 'open' | 'in_progress' | 'waiting_reply' | 'resolved' | 'closed'
  priority?: 'low' | 'medium' | 'high' | 'urgent'
  category?: string | null
  metadata?: Record<string, unknown> | null
}

export interface AssignTicketData {
  assigned_to: number
}

export interface ReplyTicketData {
  message: string
  attachments?: string[] | null
}

// ─── Aggregations ───────────────────────────────────────

export interface TicketAggregationsSummary {
  total_tickets: number
  open_count: number
  in_progress_count: number
  waiting_reply_count: number
  resolved_count: number
  closed_count: number
  unassigned_active_count: number
}

export interface TicketByPriority {
  priority: string
  count: number
  active_count: number
}

export interface TicketByCategory {
  category: string
  count: number
}

export interface TicketAgentPerformance {
  id: number
  name: string
  email: string
  total_assigned: number
  resolved_count: number
  active_count: number
  avg_resolution_hours: number | null
}

export interface TicketDailyTrend {
  date: string
  created: number
  resolved: number
}

export interface TicketAvgFirstResponse {
  avg_first_response_minutes: number | null
}

export interface TicketAggregations {
  summary: TicketAggregationsSummary
  by_priority: TicketByPriority[]
  by_category: TicketByCategory[]
  agent_performance: TicketAgentPerformance[]
  daily_trend: TicketDailyTrend[]
  avg_first_response: TicketAvgFirstResponse
}

export interface TicketAggregationsParams {
  date_from?: string
  date_to?: string
  trend_days?: number
}
