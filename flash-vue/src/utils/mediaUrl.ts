const API_ORIGIN = (import.meta.env.VITE_API_BASE_URL || 'https://klek.studio/api').replace(/\/api\/?$/, '')

export function resolveMediaUrl(path: string | null | undefined): string | null {
  if (!path) return null

  if (/^(https?:|data:|blob:)/i.test(path)) {
    return path
  }

  const normalized = path.replace(/^public\//, '')

  if (normalized.startsWith('/')) {
    return `${API_ORIGIN}${normalized}`
  }

  if (normalized.startsWith('storage/')) {
    return `${API_ORIGIN}/${normalized}`
  }

  return `${API_ORIGIN}/storage/${normalized}`
}