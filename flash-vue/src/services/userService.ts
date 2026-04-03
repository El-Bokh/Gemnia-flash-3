// ═══════════════════════════════════════════════════════════
//  Users Service
//  Backend: app/Http/Controllers/Api/Admin/UserController.php
//  Routes:  /api/admin/users/*
//
//  كل function هنا = Endpoint في الـ Backend
// ═══════════════════════════════════════════════════════════

import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse, PaginatedResponse } from '@/api/client'
import type {
  User,
  UserDetail,
  ListUsersParams,
  StoreUserPayload,
  UpdateUserPayload,
  ResetPasswordPayload,
  AssignRolesPayload,
  UserAggregations,
  PaginatedAiRequest,
  PaginatedGeneratedImage,
} from '@/types/users'

const PREFIX = '/admin/users'

// ─── Pagination Meta & Links ────────────────────────────────

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

interface PaginationLinks {
  first: string
  last: string
  prev: string | null
  next: string | null
}

interface UserListResponse {
  data: User[]
  meta: PaginationMeta
  links: PaginationLinks
}

interface SubResourceResponse<T> {
  success: boolean
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// ─── GET /api/admin/users ───────────────────────────────────
// Paginated user list with filters, sorting, search
export function getUsers(params?: ListUsersParams) {
  return apiGet<UserListResponse>(PREFIX, { params })
}

// ─── GET /api/admin/users/{id} ──────────────────────────────
// Full user detail: roles, subscriptions, stats, recent activity, credit ledger
export function getUser(id: number) {
  return apiGet<{ data: UserDetail }>(`${PREFIX}/${id}`)
}

// ─── POST /api/admin/users ──────────────────────────────────
// Create new user with optional roles and initial subscription
export function createUser(payload: StoreUserPayload) {
  return apiPost<{ data: User }>(`${PREFIX}`, payload)
}

// ─── PUT /api/admin/users/{id} ──────────────────────────────
// Update user fields + optional password + optional role sync
export function updateUser(id: number, payload: UpdateUserPayload) {
  return apiPut<{ data: User }>(`${PREFIX}/${id}`, payload)
}

// ─── DELETE /api/admin/users/{id} ───────────────────────────
// Soft-delete (archive) — cancels subscriptions, zeros credits, revokes tokens
export function deleteUser(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}`)
}

// ─── DELETE /api/admin/users/{id}/force ─────────────────────
// Permanent hard delete — removes ALL related data
export function forceDeleteUser(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}/force`)
}

// ─── POST /api/admin/users/{id}/restore ─────────────────────
// Restore soft-deleted user + their AI requests + generated images
export function restoreUser(id: number) {
  return apiPost<ApiResponse<User>>(`${PREFIX}/${id}/restore`)
}

// ─── PUT /api/admin/users/{id}/roles ────────────────────────
// Sync user roles (replaces all existing roles)
export function assignRoles(id: number, payload: AssignRolesPayload) {
  return apiPut<ApiResponse<User>>(`${PREFIX}/${id}/roles`, payload)
}

// ─── POST /api/admin/users/{id}/reset-password ──────────────
// Reset password + optionally revoke all API tokens
export function resetPassword(id: number, payload: ResetPasswordPayload) {
  return apiPost<ApiResponse<null>>(`${PREFIX}/${id}/reset-password`, payload)
}

// ─── GET /api/admin/users/aggregations ──────────────────────
// Stats: total users, per role, per plan, per status, 30-day registration trend
export function getAggregations() {
  return apiGet<ApiResponse<UserAggregations>>(`${PREFIX}/aggregations`)
}

// ─── GET /api/admin/users/{id}/ai-requests?per_page=15 ─────
// Paginated AI requests for a specific user
export function getUserAiRequests(id: number, perPage: number = 15) {
  return apiGet<SubResourceResponse<PaginatedAiRequest>>(`${PREFIX}/${id}/ai-requests`, {
    params: { per_page: perPage },
  })
}

// ─── GET /api/admin/users/{id}/generated-images?per_page=15 ─
// Paginated generated images for a specific user
export function getUserGeneratedImages(id: number, perPage: number = 15) {
  return apiGet<SubResourceResponse<PaginatedGeneratedImage>>(`${PREFIX}/${id}/generated-images`, {
    params: { per_page: perPage },
  })
}
