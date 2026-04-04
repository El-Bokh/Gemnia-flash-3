import { apiGet, apiPost, apiDelete } from '@/api/client'
import type { ApiResponse, PaginatedResponse } from '@/api/client'
import type { Notification, ListNotificationsParams, UnreadCountResponse } from '@/types/notifications'

// ─── Client (User) Notifications ────────────────────────────

const USER_BASE = '/notifications'

export function getUserNotifications(params?: ListNotificationsParams) {
  return apiGet<PaginatedResponse<Notification>>(USER_BASE, { params })
}

export function getUserUnreadCount() {
  return apiGet<ApiResponse<UnreadCountResponse>>(`${USER_BASE}/unread-count`)
}

export function markNotificationRead(id: number) {
  return apiPost<ApiResponse<Notification>>(`${USER_BASE}/${id}/read`)
}

export function markAllNotificationsRead() {
  return apiPost<ApiResponse<null>>(`${USER_BASE}/read-all`)
}

export function deleteNotification(id: number) {
  return apiDelete<ApiResponse<null>>(`${USER_BASE}/${id}`)
}

// ─── Admin Notifications ────────────────────────────────────

const ADMIN_BASE = '/admin/notifications'

export function getAdminNotifications(params?: ListNotificationsParams) {
  return apiGet<PaginatedResponse<Notification>>(ADMIN_BASE, { params })
}

export function getAdminUnreadCount() {
  return apiGet<ApiResponse<UnreadCountResponse>>(`${ADMIN_BASE}/unread-count`)
}

export function markAdminNotificationRead(id: number) {
  return apiPost<ApiResponse<Notification>>(`${ADMIN_BASE}/${id}/read`)
}

export function markAllAdminNotificationsRead() {
  return apiPost<ApiResponse<null>>(`${ADMIN_BASE}/read-all`)
}

export function deleteAdminNotification(id: number) {
  return apiDelete<ApiResponse<null>>(`${ADMIN_BASE}/${id}`)
}
