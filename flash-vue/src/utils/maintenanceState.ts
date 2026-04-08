import type { PublicMaintenanceStatus } from '@/types/settings'

const CACHE_TTL_MS = 15_000
const DEFAULT_MESSAGE = 'The platform is currently under maintenance. Please try again later.'

let cachedStatus: PublicMaintenanceStatus | null = null
let cachedAt = 0

export function getMaintenanceStatusFromCache(maxAgeMs = CACHE_TTL_MS): PublicMaintenanceStatus | null {
  if (!cachedStatus) {
    return null
  }

  return Date.now() - cachedAt <= maxAgeMs ? cachedStatus : null
}

export function peekMaintenanceStatus(): PublicMaintenanceStatus | null {
  return cachedStatus
}

export function setMaintenanceStatus(status: PublicMaintenanceStatus | null): void {
  cachedStatus = status
  cachedAt = status ? Date.now() : 0
}

export function recordMaintenanceFromMessage(message: string): void {
  const normalizedMessage = message.trim() || DEFAULT_MESSAGE

  setMaintenanceStatus({
    is_enabled: true,
    message: normalizedMessage,
    can_bypass: false,
  })
}