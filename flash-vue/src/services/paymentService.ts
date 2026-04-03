import { apiGet, apiPut, apiPost, apiDelete } from '@/api/client'
import type { ApiResponse, PaginatedResponse } from '@/api/client'
import type {
  Payment,
  PaymentDetail,
  ListPaymentsParams,
  UpdatePaymentData,
  RefundPaymentData,
  PaymentAggregations,
  PaymentAggregationsParams,
} from '@/types/payments'

const BASE = '/admin/payments'

export function getPayments(params?: ListPaymentsParams) {
  return apiGet<PaginatedResponse<Payment>>(BASE, { params })
}

export function getPayment(id: number) {
  return apiGet<ApiResponse<PaymentDetail>>(`${BASE}/${id}`)
}

export function updatePayment(id: number, data: UpdatePaymentData) {
  return apiPut<ApiResponse<PaymentDetail>>(`${BASE}/${id}`, data)
}

export function refundPayment(id: number, data: RefundPaymentData) {
  return apiPost<ApiResponse<PaymentDetail>>(`${BASE}/${id}/refund`, data)
}

export function deletePayment(id: number) {
  return apiDelete<ApiResponse<null>>(`${BASE}/${id}`)
}

export function forceDeletePayment(id: number) {
  return apiDelete<ApiResponse<null>>(`${BASE}/${id}/force`)
}

export function restorePayment(id: number) {
  return apiPost<ApiResponse<PaymentDetail>>(`${BASE}/${id}/restore`)
}

export function getPaymentAggregations(params?: PaymentAggregationsParams) {
  return apiGet<ApiResponse<PaymentAggregations>>(`${BASE}/aggregations`, { params })
}
