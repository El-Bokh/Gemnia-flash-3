export interface StoredAuthUser {
  id: number
  name: string
  email: string
  avatar: string | null
  roles: string[]
  permissions: string[]
}

const AUTH_USER_KEY = 'auth_user'

export function storeAuthUser(user: StoredAuthUser): void {
  localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user))
}

export function clearStoredAuthUser(): void {
  localStorage.removeItem(AUTH_USER_KEY)
}

export function clearStoredAuth(): void {
  localStorage.removeItem('auth_token')
  clearStoredAuthUser()
}

export function getStoredAuthUser(): StoredAuthUser | null {
  const raw = localStorage.getItem(AUTH_USER_KEY)

  if (!raw) {
    return null
  }

  try {
    const parsed = JSON.parse(raw) as Partial<StoredAuthUser>

    if (
      typeof parsed.id !== 'number'
      || typeof parsed.name !== 'string'
      || typeof parsed.email !== 'string'
      || !Array.isArray(parsed.roles)
    ) {
      clearStoredAuthUser()
      return null
    }

    return {
      id: parsed.id,
      name: parsed.name,
      email: parsed.email,
      avatar: typeof parsed.avatar === 'string' || parsed.avatar === null ? parsed.avatar : null,
      roles: parsed.roles.filter((role): role is string => typeof role === 'string'),
      permissions: Array.isArray(parsed.permissions)
        ? (parsed.permissions as unknown[]).filter((p): p is string => typeof p === 'string')
        : [],
    }
  } catch {
    clearStoredAuthUser()
    return null
  }
}

export function isAdminUser(user: Pick<StoredAuthUser, 'roles'> | null | undefined): boolean {
  return (user?.roles ?? []).some(role => role === 'admin' || role === 'super_admin')
}

/**
 * Check if the user has access to the admin panel.
 * Users with admin/super_admin roles, or any permissions, can access.
 */
export function hasAdminAccess(user: Pick<StoredAuthUser, 'roles' | 'permissions'> | null | undefined): boolean {
  if (!user) return false
  // Admin / super_admin always have access
  if ((user.roles ?? []).some(role => role === 'admin' || role === 'super_admin')) return true
  // Users with any permissions have access (e.g. support role)
  return (user.permissions ?? []).length > 0
}

/**
 * Check if the user has a specific permission.
 * Admin/super_admin bypass the check.
 */
export function hasPermission(user: Pick<StoredAuthUser, 'roles' | 'permissions'> | null | undefined, permission: string): boolean {
  if (!user) return false
  if ((user.roles ?? []).some(role => role === 'admin' || role === 'super_admin')) return true
  return (user.permissions ?? []).includes(permission)
}

export function getAuthenticatedHome(user: Pick<StoredAuthUser, 'roles' | 'permissions'> | null | undefined): string {
  return hasAdminAccess(user) ? '/admin' : '/chat'
}