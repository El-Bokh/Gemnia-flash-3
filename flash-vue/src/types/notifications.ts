// ─── Notification Types ─────────────────────────────────────

export interface Notification {
  id: number
  uuid: string
  user_id: number
  type: string
  title: string
  body: string
  icon: string | null
  action_url: string | null
  channel: 'in_app' | 'email' | 'sms' | 'push'
  priority: 'low' | 'normal' | 'high'
  is_read: boolean
  read_at: string | null
  sent_at: string | null
  data: Record<string, unknown> | null
  created_at: string
  updated_at: string
}

export interface ListNotificationsParams {
  page?: number
  per_page?: number
  type?: string
  is_read?: boolean | string
  priority?: string
  search?: string
  date_from?: string
  date_to?: string
  category?: string
  user_id?: number
}

export interface UnreadCountResponse {
  count: number
}
