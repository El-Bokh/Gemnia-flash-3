const API_ORIGIN = (import.meta.env.VITE_API_BASE_URL || 'https://klek.studio/api').replace(/\/api\/?$/, '')

function getDevStorageProxyPath(pathname: string, search = '', hash = ''): string | null {
  if (!import.meta.env.DEV || !pathname.startsWith('/storage/')) {
    return null
  }

  return `${pathname}${search}${hash}`
}

export function resolveMediaUrl(path: string | null | undefined): string | null {
  if (!path) return null

  if (path.startsWith('gs://')) {
    const gcsPath = path.slice(5)
    const [bucket = '', ...objectParts] = gcsPath.split('/')
    return `https://storage.googleapis.com/${encodeURIComponent(bucket)}/${objectParts.map(encodeURIComponent).join('/')}`
  }

  if (/^(data:|blob:)/i.test(path)) {
    return path
  }

  if (/^https?:/i.test(path)) {
    try {
      const url = new URL(path)

      if (url.origin === API_ORIGIN) {
        return getDevStorageProxyPath(url.pathname, url.search, url.hash) ?? path
      }
    } catch {
      return path
    }

    return path
  }

  const normalized = path.replace(/^public\//, '')

  if (normalized.startsWith('/')) {
    return getDevStorageProxyPath(normalized) ?? `${API_ORIGIN}${normalized}`
  }

  if (normalized.startsWith('storage/')) {
    const storagePath = `/${normalized}`
    return getDevStorageProxyPath(storagePath) ?? `${API_ORIGIN}${storagePath}`
  }

  const storagePath = `/storage/${normalized}`
  return getDevStorageProxyPath(storagePath) ?? `${API_ORIGIN}${storagePath}`
}