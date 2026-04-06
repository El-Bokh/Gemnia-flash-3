import { apiGet, apiPost } from '@/api/client'
import type { ApiResponse } from '@/api/client'

export interface PlanFeature {
  name: string
  slug: string
  credits_per_use: number | null
  usage_limit: number | null
}

export interface Plan {
  id: number
  name: string
  slug: string
  description: string | null
  price_monthly: number
  price_yearly: number
  currency: string
  credits_monthly: number
  credits_yearly: number
  is_free: boolean
  is_featured: boolean
  trial_days: number
  features: PlanFeature[]
}

export function getPlans() {
  return apiGet<ApiResponse<Plan[]>>('/plans/public')
}

export function upgradeSubscription(planId: number, billingCycle: 'monthly' | 'yearly') {
  return apiPost<ApiResponse<any>>('/subscription/upgrade', {
    plan_id: planId,
    billing_cycle: billingCycle,
  })
}

export function cancelSubscription(reason?: string) {
  return apiPost<ApiResponse<any>>('/subscription/cancel', { reason })
}
