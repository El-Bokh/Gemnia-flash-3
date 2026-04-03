// ═══════════════════════════════════════════════════════════
//  Role Service
//  Backend: app/Http/Controllers/Api/Admin/RoleController.php
//  Routes:  /api/admin/roles/*
//
//  كل function هنا = Endpoint في الـ Backend
// ═══════════════════════════════════════════════════════════

import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse } from '@/api/client'
import type {
  Role,
  RoleDetail,
  ListRolesParams,
  StoreRolePayload,
  UpdateRolePayload,
  AssignPermissionsPayload,
  PermissionMatrix,
} from '@/types/roles'

const PREFIX = '/admin/roles'

// ─── GET /api/admin/roles ───────────────────────────────────
// List all roles with optional search, sorting, counts
export function getRoles(params?: ListRolesParams) {
  return apiGet<ApiResponse<Role[]>>(PREFIX, { params })
}

// ─── GET /api/admin/roles/{id} ──────────────────────────────
// Role detail: permissions, permissions_grouped, recent_users, counts
export function getRole(id: number) {
  return apiGet<ApiResponse<RoleDetail>>(`${PREFIX}/${id}`)
}

// ─── POST /api/admin/roles ──────────────────────────────────
// Create role with optional permissions
export function createRole(payload: StoreRolePayload) {
  return apiPost<ApiResponse<Role>>(PREFIX, payload)
}

// ─── PUT /api/admin/roles/{id} ──────────────────────────────
// Update role fields + optional permissions sync
export function updateRole(id: number, payload: UpdateRolePayload) {
  return apiPut<ApiResponse<Role>>(`${PREFIX}/${id}`, payload)
}

// ─── DELETE /api/admin/roles/{id} ───────────────────────────
// Delete role (fails if has users assigned)
export function deleteRole(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}`)
}

// ─── PUT /api/admin/roles/{id}/permissions ──────────────────
// Sync permissions for a role (replaces all existing)
export function assignPermissions(id: number, payload: AssignPermissionsPayload) {
  return apiPut<ApiResponse<Role>>(`${PREFIX}/${id}/permissions`, payload)
}

// ─── GET /api/admin/roles/matrix ────────────────────────────
// Permission matrix: all roles × all permissions with enabled flags
export function getPermissionMatrix() {
  return apiGet<ApiResponse<PermissionMatrix>>(`${PREFIX}/matrix`)
}
