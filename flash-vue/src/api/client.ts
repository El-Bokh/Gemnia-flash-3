import axios from 'axios'
import type { AxiosInstance, AxiosRequestConfig, InternalAxiosRequestConfig, AxiosResponse } from 'axios'
import router from '@/router'
import { recordMaintenanceFromMessage } from '@/utils/maintenanceState'
import { clearStoredAuth } from '@/utils/auth'

// ─── Base API Configuration ─────────────────────────────────

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'https://klek.studio/api'

const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30_000,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

// ─── CSRF Cookie ────────────────────────────────────────────

const BACKEND_URL = API_BASE_URL.replace(/\/api\/?$/, '')

export async function fetchCsrfCookie(): Promise<void> {
  await axios.get(`${BACKEND_URL}/sanctum/csrf-cookie`, {
    withCredentials: true,
  })
}

// ─── Request Interceptor ────────────────────────────────────

apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error),
)

// ─── Response Interceptor ───────────────────────────────────

apiClient.interceptors.response.use(
  (response: AxiosResponse) => response,
  (error) => {
    if (error.response) {
      const { status } = error.response

      if (status === 401) {
        // Only redirect to login if user was previously authenticated
        const wasAuthenticated = !!localStorage.getItem('auth_token')
        clearStoredAuth()
        if (wasAuthenticated && router.currentRoute.value.name !== 'login') {
          void router.replace({ name: 'login' })
        }
      }

      if (status === 403) {
        console.error('[API] Forbidden — insufficient permissions')
      }

      if (status === 419) {
        console.error('[API] CSRF token mismatch')
      }

      if (status === 429) {
        console.error('[API] Too many requests — rate limited')
      }

      if (status === 503) {
        const currentPath = router.currentRoute.value.fullPath || '/'
        const message = typeof error.response.data?.message === 'string'
          ? error.response.data.message
          : 'The platform is currently under maintenance. Please try again later.'

        recordMaintenanceFromMessage(message)
        console.error('[API] Service unavailable — maintenance mode')

        if (router.currentRoute.value.name !== 'maintenance') {
          void router.replace({
            name: 'maintenance',
            query: currentPath !== '/maintenance' ? { redirect: currentPath } : undefined,
          })
        }
      }
    }

    return Promise.reject(error)
  },
)

// ─── Generic API Response Type ──────────────────────────────

export interface ApiResponse<T> {
  success: boolean
  data: T
  message?: string
}

export interface PaginatedResponse<T> {
  success: boolean
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}

// ─── Helper Methods ─────────────────────────────────────────

export async function apiGet<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
  const response = await apiClient.get<T>(url, config)
  return response.data
}

export async function apiPost<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T> {
  const response = await apiClient.post<T>(url, data, config)
  return response.data
}

export async function apiPut<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T> {
  const response = await apiClient.put<T>(url, data, config)
  return response.data
}

export async function apiPatch<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T> {
  const response = await apiClient.patch<T>(url, data, config)
  return response.data
}

export async function apiDelete<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
  const response = await apiClient.delete<T>(url, config)
  return response.data
}

export default apiClient
