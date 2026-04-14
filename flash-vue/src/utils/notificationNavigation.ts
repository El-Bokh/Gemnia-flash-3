import type { RouteLocationRaw } from 'vue-router'

export function resolveNotificationTarget(actionUrl: string | null): RouteLocationRaw | null {
  if (!actionUrl) {
    return null
  }

  const normalized = actionUrl.trim()

  const adminTicketMatch = normalized.match(/^\/admin\/support-tickets\/(\d+)$/)
  if (adminTicketMatch) {
    return {
      name: 'admin-support',
      query: { ticket: adminTicketMatch[1] },
    }
  }

  const clientTicketMatch = normalized.match(/^\/support-tickets\/(\d+)$/)
  if (clientTicketMatch) {
    return {
      name: 'support',
      query: { ticket: clientTicketMatch[1] },
    }
  }

  return normalized
}