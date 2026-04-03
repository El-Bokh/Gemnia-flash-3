// ═══════════════════════════════════════════════════════════
//  Permission Service
//  Backend: app/Http/Controllers/Api/Admin/PermissionController.php
//  Routes:  /api/admin/permissions/*
//
//  كل function هنا = Endpoint في الـ Backend
// ═══════════════════════════════════════════════════════════

import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse } from '@/api/client'
import type {
  Permission,
  PermissionGroup,
  StorePermissionPayload,
  UpdatePermissionPayload,
} from '@/types/roles'

const PREFIX = '/admin/permissions'

// ─── GET /api/admin/permissions ─────────────────────────────
// List all permissions (flat, ordered by group then name)
export function getPermissions() {
  return apiGet<ApiResponse<Permission[]>>(PREFIX)
}

// ─── GET /api/admin/permissions/grouped ─────────────────────
// Permissions grouped by their group field
export function getPermissionsGrouped() {
  return apiGet<ApiResponse<PermissionGroup[]>>(`${PREFIX}/grouped`)
}

// ─── POST /api/admin/permissions ────────────────────────────
// Create a new permission
export function createPermission(payload: StorePermissionPayload) {
  return apiPost<ApiResponse<Permission>>(PREFIX, payload)
}

// ─── PUT /api/admin/permissions/{id} ────────────────────────
// Update permission fields
export function updatePermission(id: number, payload: UpdatePermissionPayload) {
  return apiPut<ApiResponse<Permission>>(`${PREFIX}/${id}`, payload)
}

// ─── DELETE /api/admin/permissions/{id} ─────────────────────
// Delete permission (fails if assigned to any roles)
export function deletePermission(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}`)
}
