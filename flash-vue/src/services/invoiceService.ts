import { apiGet, apiPut, apiPost, apiDelete } from '@/api/client'
import type { ApiResponse, PaginatedResponse } from '@/api/client'
import type {
  Invoice,
  InvoiceDetail,
  ListInvoicesParams,
  UpdateInvoiceData,
  InvoiceAggregations,
  InvoiceAggregationsParams,
  InvoiceDownloadData,
} from '@/types/payments'

const BASE = '/admin/invoices'

export function getInvoices(params?: ListInvoicesParams) {
  return apiGet<PaginatedResponse<Invoice>>(BASE, { params })
}

export function getInvoice(id: number) {
  return apiGet<ApiResponse<InvoiceDetail>>(`${BASE}/${id}`)
}

export function updateInvoice(id: number, data: UpdateInvoiceData) {
  return apiPut<ApiResponse<InvoiceDetail>>(`${BASE}/${id}`, data)
}

export function generateInvoiceFromPayment(paymentId: number) {
  return apiPost<ApiResponse<InvoiceDetail>>(`${BASE}/generate/${paymentId}`)
}

export function downloadInvoice(id: number) {
  return apiGet<ApiResponse<InvoiceDownloadData>>(`${BASE}/${id}/download`)
}

export function deleteInvoice(id: number) {
  return apiDelete<ApiResponse<null>>(`${BASE}/${id}`)
}

export function forceDeleteInvoice(id: number) {
  return apiDelete<ApiResponse<null>>(`${BASE}/${id}/force`)
}

export function restoreInvoice(id: number) {
  return apiPost<ApiResponse<InvoiceDetail>>(`${BASE}/${id}/restore`)
}

export function getInvoiceAggregations(params?: InvoiceAggregationsParams) {
  return apiGet<ApiResponse<InvoiceAggregations>>(`${BASE}/aggregations`, { params })
}
