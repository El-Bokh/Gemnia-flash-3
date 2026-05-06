import { apiPost, apiGet, fetchCsrfCookie } from '@/api/client'
import type { ApiResponse } from '@/api/client'

// ─── Types ──────────────────────────────────────────────────

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
  avatar: string | null
  roles: string[]
  permissions: string[]
}

export interface LoginResponse {
  token: string
  user: AuthUser
}

export interface QuotaInfo {
  has_subscription: boolean
  plan_id: number | null
  plan_name: string | null
  plan_slug: string | null
  plan_is_free: boolean
  billing_cycle: 'monthly' | 'yearly' | null
  payment_gateway: string | null
  credits_remaining: number
  credits_total: number
  credits_used: number
  usage_percentage: number
  period_start: string | null
  period_end: string | null
  status: string
  requests_today: number
  requests_this_month: number
  warning_level: 'none' | 'low' | 'critical' | 'depleted'
  can_renew: boolean
}

export interface MeResponse {
  id: number
  name: string
  email: string
  phone: string | null
  avatar: string | null
  status: string
  roles: string[]
  permissions: string[]
  locale: string
  timezone: string
  last_login_at: string | null
  last_login_ip: string | null
  quota: QuotaInfo
}

// ─── Service ────────────────────────────────────────────────

export async function login(credentials: LoginCredentials) {
  await fetchCsrfCookie()
  return apiPost<ApiResponse<LoginResponse>>('/auth/login', credentials)
}

export async function register(payload: RegisterPayload) {
  await fetchCsrfCookie()
  return apiPost<ApiResponse<LoginResponse>>('/auth/register', payload)
}

export function logout() {
  return apiPost<ApiResponse<null>>('/auth/logout')
}

export function getMe() {
  return apiGet<ApiResponse<MeResponse>>('/auth/me')
}

export function getSubscription() {
  return apiGet<ApiResponse<QuotaInfo>>('/subscription')
}

// ─── Google OAuth ───────────────────────────────────────────

export function getGoogleRedirectUrl(): string {
  const baseUrl = (import.meta.env.VITE_API_BASE_URL || 'https://klek.studio/api').replace(/\/api\/?$/, '')
  return `${baseUrl}/auth/google/redirect`
}
