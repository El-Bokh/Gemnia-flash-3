import { apiGet, apiPost } from '@/api/client'
import type { ApiResponse, PaginatedResponse } from '@/api/client'

// ─── Types ───────────────────────────────────
export interface ClientTicket {
  id: number
  uuid: string
  ticket_number: string
  subject: string
  status: string
  priority: string
  category: string | null
  replies_count: number
  last_reply_at: string | null
  created_at: string
  updated_at: string
}

export interface ClientTicketReply {
  id: number
  message: string
  is_staff_reply: boolean
  created_at: string
  user: { id: number; name: string; avatar: string | null }
}

export interface ClientTicketDetail extends ClientTicket {
  message: string
  replies: ClientTicketReply[]
}

export interface CreateTicketData {
  subject: string
  message: string
  category?: string
  priority?: string
}

// ─── API Calls ───────────────────────────────
const BASE = '/support-tickets'

export function getMyTickets(params?: { status?: string; page?: number; per_page?: number }) {
  return apiGet<PaginatedResponse<ClientTicket>>(BASE, { params })
}

export function getMyTicket(id: number) {
  return apiGet<ApiResponse<ClientTicketDetail>>(`${BASE}/${id}`)
}

export function createTicket(data: CreateTicketData) {
  return apiPost<ApiResponse<ClientTicket>>(BASE, data)
}

export function replyToMyTicket(id: number, message: string) {
  return apiPost<ApiResponse<ClientTicketReply>>(`${BASE}/${id}/reply`, { message })
}
