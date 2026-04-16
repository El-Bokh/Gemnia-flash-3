import { apiGet, apiPost } from '@/api/client'
import type { ApiResponse, PaginatedResponse } from '@/api/client'
import type { SupportAttachment } from '@/types/supportShared'

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
  attachments: SupportAttachment[]
  created_at: string
  user: { id: number; name: string; avatar: string | null }
}

export interface ClientTicketDetail extends ClientTicket {
  message: string
  attachments: SupportAttachment[]
  replies: ClientTicketReply[]
}

export interface CreateTicketData {
  subject: string
  message: string
  category?: string
  priority?: string
  attachments?: File[]
}

export interface ReplyToTicketData {
  message: string
  attachments?: File[]
}

export interface ClientTicketState {
  id: number
  status: string
  resolved_at: string | null
  closed_at: string | null
}

function appendAttachments(form: FormData, attachments?: File[]) {
  attachments?.forEach((file, index) => {
    form.append(`attachments[${index}]`, file)
  })
}

function buildCreateTicketForm(data: CreateTicketData) {
  const form = new FormData()
  form.append('subject', data.subject)
  form.append('message', data.message)
  if (data.category) form.append('category', data.category)
  if (data.priority) form.append('priority', data.priority)
  appendAttachments(form, data.attachments)
  return form
}

function buildReplyForm(data: ReplyToTicketData) {
  const form = new FormData()
  form.append('message', data.message)
  appendAttachments(form, data.attachments)
  return form
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
  return apiPost<ApiResponse<ClientTicket>>(BASE, buildCreateTicketForm(data), {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function replyToMyTicket(id: number, data: ReplyToTicketData) {
  return apiPost<ApiResponse<ClientTicketReply>>(`${BASE}/${id}/reply`, buildReplyForm(data), {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function resolveMyTicket(id: number) {
  return apiPost<ApiResponse<ClientTicketState>>(`${BASE}/${id}/resolve`)
}

export function reopenMyTicket(id: number) {
  return apiPost<ApiResponse<ClientTicketState>>(`${BASE}/${id}/reopen`)
}
