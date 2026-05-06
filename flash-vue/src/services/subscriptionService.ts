import { apiGet, apiPost } from '@/api/client'
import type { ApiResponse } from '@/api/client'

export const GUMROAD_CHECKOUT_URL = 'https://klekstudio.gumroad.com/l/membership'

export type BillingCycle = 'monthly' | 'yearly'

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
  sort_order: number
  trial_days: number
  checkout_url: string | null
  features: PlanFeature[]
}

export function getPlans() {
  return apiGet<ApiResponse<Plan[]>>('/plans/public')
}

export function buildPlanCheckoutUrl(plan: Pick<Plan, 'checkout_url' | 'slug'>, email?: string | null) {
  const baseUrl = plan.checkout_url || (plan.slug.startsWith('gumroad-') ? GUMROAD_CHECKOUT_URL : null)
  if (!baseUrl) return null

  const separator = baseUrl.includes('?') ? '&' : '?'
  return `${baseUrl}${separator}email=${encodeURIComponent(email ?? '')}&wanted=true`
}

export function upgradeSubscription(planId: number, billingCycle: BillingCycle) {
  return apiPost<ApiResponse<any>>('/subscription/upgrade', {
    plan_id: planId,
    billing_cycle: billingCycle,
  })
}

export function renewSubscription() {
  return apiPost<ApiResponse<any>>('/subscription/renew')
}

export function cancelSubscription(reason?: string) {
  return apiPost<ApiResponse<any>>('/subscription/cancel', { reason })
}
