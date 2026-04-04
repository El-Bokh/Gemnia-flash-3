import { apiPost, apiGet } from '@/api/client'
import type { ApiResponse } from '@/api/client'

// ─── Types ──────────────────────────────────────────────────

export interface LoginCredentials {
  email: string
  password: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
  avatar: string | null
  roles: string[]
}

export interface LoginResponse {
  token: string
  user: AuthUser
}

export interface MeResponse {
  id: number
  name: string
  email: string
  phone: string | null
  avatar: string | null
  status: string
  roles: string[]
  locale: string
  timezone: string
  last_login_at: string | null
  last_login_ip: string | null
}

// ─── Service ────────────────────────────────────────────────

export function login(credentials: LoginCredentials) {
  return apiPost<ApiResponse<LoginResponse>>('/auth/login', credentials)
}

export function logout() {
  return apiPost<ApiResponse<null>>('/auth/logout')
}

export function getMe() {
  return apiGet<ApiResponse<MeResponse>>('/auth/me')
}
