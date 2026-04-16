import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse, PaginatedResponse } from '@/api/client'
import type {
  SupportTicket,
  SupportTicketDetail,
  SupportTicketReply,
  ListSupportTicketsParams,
  UpdateSupportTicketData,
  AssignTicketData,
  ReplyTicketData,
  TicketAggregations,
  TicketAggregationsParams,
} from '@/types/supportTickets'

const BASE = '/admin/support-tickets'

export function getSupportTickets(params?: ListSupportTicketsParams) {
  return apiGet<PaginatedResponse<SupportTicket>>(BASE, { params })
}

export function getSupportTicket(id: number) {
  return apiGet<ApiResponse<SupportTicketDetail>>(`${BASE}/${id}`)
}

export function updateSupportTicket(id: number, data: UpdateSupportTicketData) {
  return apiPut<ApiResponse<SupportTicket>>(`${BASE}/${id}`, data)
}

export function assignTicket(id: number, data: AssignTicketData) {
  return apiPost<ApiResponse<SupportTicket>>(`${BASE}/${id}/assign`, data)
}

export function replyToTicket(id: number, data: ReplyTicketData) {
  const form = new FormData()
  form.append('message', data.message)
  data.attachments?.forEach((file, index) => {
    form.append(`attachments[${index}]`, file)
  })

  return apiPost<ApiResponse<SupportTicketReply>>(`${BASE}/${id}/reply`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function resolveTicket(id: number) {
  return apiPost<ApiResponse<SupportTicket>>(`${BASE}/${id}/resolve`)
}

export function closeTicket(id: number) {
  return apiPost<ApiResponse<SupportTicket>>(`${BASE}/${id}/close`)
}

export function reopenTicket(id: number) {
  return apiPost<ApiResponse<SupportTicket>>(`${BASE}/${id}/reopen`)
}

export function deleteSupportTicket(id: number) {
  return apiDelete<ApiResponse<null>>(`${BASE}/${id}`)
}

export function forceDeleteSupportTicket(id: number) {
  return apiDelete<ApiResponse<null>>(`${BASE}/${id}/force`)
}

export function restoreSupportTicket(id: number) {
  return apiPost<ApiResponse<SupportTicket>>(`${BASE}/${id}/restore`)
}

export function getTicketAggregations(params?: TicketAggregationsParams) {
  return apiGet<ApiResponse<TicketAggregations>>(`${BASE}/aggregations`, { params })
}
