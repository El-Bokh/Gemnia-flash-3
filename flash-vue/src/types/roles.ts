// ═══════════════════════════════════════════════════════════
//  Roles & Permissions Types
//  Backend: app/Http/Resources/Admin/RoleResource.php
//           app/Http/Resources/Admin/RoleDetailResource.php
//           app/Http/Resources/Admin/PermissionResource.php
//           app/Services/Admin/RolePermissionService.php
// ═══════════════════════════════════════════════════════════

// ─── Permission ─────────────────────────────────────────────

export interface PermissionRef {
  id: number
  name: string
  slug: string
  group: string | null
}

export interface Permission {
  id: number
  name: string
  slug: string
  group: string | null
  description: string | null
  created_at: string | null
  updated_at: string | null
}

// ─── Permission Grouped ─────────────────────────────────────

export interface PermissionGroupItem {
  id: number
  name: string
  slug: string
  description: string | null
}

export interface PermissionGroup {
  group: string
  permissions: PermissionGroupItem[]
}

// ─── Role (list item — RoleResource) ────────────────────────

export interface Role {
  id: number
  name: string
  slug: string
  description: string | null
  is_default: boolean
  created_at: string | null
  updated_at: string | null
  permissions?: PermissionRef[]
  permissions_count?: number
  users_count?: number
}

// ─── Role Detail (show — RoleDetailResource) ────────────────

export interface RoleDetailPermission {
  id: number
  name: string
  slug: string
  group: string | null
  description: string | null
}

export interface RoleDetailPermissionGrouped {
  group: string
  permissions: {
    id: number
    name: string
    slug: string
  }[]
}

export interface RoleDetailUser {
  id: number
  name: string
  email: string
  avatar: string | null
  status: string
}

export interface RoleDetail {
  id: number
  name: string
  slug: string
  description: string | null
  is_default: boolean
  created_at: string | null
  updated_at: string | null
  permissions: RoleDetailPermission[]
  permissions_grouped: RoleDetailPermissionGrouped[]
  permissions_count: number
  users_count: number
  recent_users: RoleDetailUser[]
}

// ─── Permission Matrix ──────────────────────────────────────

export interface MatrixRoleRef {
  id: number
  name: string
  slug: string
  users_count: number
}

export interface MatrixPermission {
  id: number
  slug: string
  name: string
  group: string | null
  enabled: boolean
}

export interface MatrixRow {
  role: MatrixRoleRef
  permissions: MatrixPermission[]
}

export interface MatrixPermissionRef {
  id: number
  name: string
  slug: string
  group: string | null
}

export interface PermissionMatrix {
  roles: MatrixRow[]
  permissions: MatrixPermissionRef[]
}

// ─── Request Payloads ───────────────────────────────────────

export interface ListRolesParams {
  search?: string
  with_counts?: boolean
  sort_by?: 'name' | 'slug' | 'created_at' | 'users_count' | 'permissions_count'
  sort_dir?: 'asc' | 'desc'
}

export interface StoreRolePayload {
  name: string
  slug: string
  description?: string | null
  is_default?: boolean
  permissions?: number[]
}

export interface UpdateRolePayload {
  name?: string
  slug?: string
  description?: string | null
  is_default?: boolean
  permissions?: number[]
}

export interface AssignPermissionsPayload {
  permissions: number[]
}

export interface StorePermissionPayload {
  name: string
  slug: string
  group?: string | null
  description?: string | null
}

export interface UpdatePermissionPayload {
  name?: string
  slug?: string
  group?: string | null
  description?: string | null
}
