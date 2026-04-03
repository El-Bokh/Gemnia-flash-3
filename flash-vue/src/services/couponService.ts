import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse, PaginatedResponse } from '@/api/client'
import type {
  Coupon,
  CouponDetail,
  ListCouponsParams,
  StoreCouponData,
  UpdateCouponData,
  ValidateCouponData,
  ValidateCouponResponse,
  CouponUsageStats,
  CouponAggregations,
} from '@/types/payments'

const BASE = '/admin/coupons'

export function getCoupons(params?: ListCouponsParams) {
  return apiGet<PaginatedResponse<Coupon>>(BASE, { params })
}

export function getCoupon(id: number) {
  return apiGet<ApiResponse<CouponDetail>>(`${BASE}/${id}`)
}

export function storeCoupon(data: StoreCouponData) {
  return apiPost<ApiResponse<CouponDetail>>(BASE, data)
}

export function updateCoupon(id: number, data: UpdateCouponData) {
  return apiPut<ApiResponse<CouponDetail>>(`${BASE}/${id}`, data)
}

export function toggleCoupon(id: number) {
  return apiPost<ApiResponse<CouponDetail>>(`${BASE}/${id}/toggle`)
}

export function validateCoupon(data: ValidateCouponData) {
  return apiPost<ApiResponse<ValidateCouponResponse>>(`${BASE}/validate`, data)
}

export function getCouponUsage(id: number) {
  return apiGet<ApiResponse<CouponUsageStats>>(`${BASE}/${id}/usage`)
}

export function deleteCoupon(id: number) {
  return apiDelete<ApiResponse<null>>(`${BASE}/${id}`)
}

export function forceDeleteCoupon(id: number) {
  return apiDelete<ApiResponse<null>>(`${BASE}/${id}/force`)
}

export function restoreCoupon(id: number) {
  return apiPost<ApiResponse<CouponDetail>>(`${BASE}/${id}/restore`)
}

export function getCouponAggregations() {
  return apiGet<ApiResponse<CouponAggregations>>(`${BASE}/aggregations`)
}
