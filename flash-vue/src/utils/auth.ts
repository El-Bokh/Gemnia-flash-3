export interface StoredAuthUser {
  id: number
  name: string
  email: string
  avatar: string | null
  roles: string[]
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
    }
  } catch {
    clearStoredAuthUser()
    return null
  }
}

export function isAdminUser(user: Pick<StoredAuthUser, 'roles'> | null | undefined): boolean {
  return (user?.roles ?? []).some(role => role === 'admin' || role === 'super_admin')
}

export function getAuthenticatedHome(user: Pick<StoredAuthUser, 'roles'> | null | undefined): string {
  return isAdminUser(user) ? '/admin' : '/chat'
}