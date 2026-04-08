import { useHead } from '@unhead/vue'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

const SITE_NAME = 'Klek AI'
const SITE_URL = 'https://klek.studio'
const DEFAULT_OG_IMAGE = `${SITE_URL}/icons/icon-512x512.png`

export interface SeoOptions {
  title: MaybeRefOrGetter<string>
  description: MaybeRefOrGetter<string>
  path?: MaybeRefOrGetter<string>
  ogImage?: string
  type?: 'website' | 'article'
  noindex?: boolean
  jsonLd?: MaybeRefOrGetter<Record<string, unknown> | null>
}

export function useSeo(options: SeoOptions) {
  const fullTitle = computed(() => {
    const t = toValue(options.title)
    return t ? `${t} | ${SITE_NAME}` : SITE_NAME
  })

  const description = computed(() => toValue(options.description))
  const canonicalUrl = computed(() => {
    const p = toValue(options.path ?? '')
    return p ? `${SITE_URL}${p}` : SITE_URL
  })
  const ogImage = options.ogImage ?? DEFAULT_OG_IMAGE
  const pageType = options.type ?? 'website'

  const jsonLdScript = computed(() => {
    const ld = toValue(options.jsonLd ?? null)
    if (!ld) return []
    return [
      {
        type: 'application/ld+json',
        innerHTML: JSON.stringify(ld),
      },
    ]
  })

  useHead({
    title: fullTitle,
    meta: [
      { name: 'description', content: description },
      { name: 'robots', content: options.noindex ? 'noindex, nofollow' : 'index, follow' },

      // Open Graph
      { property: 'og:type', content: pageType },
      { property: 'og:title', content: fullTitle },
      { property: 'og:description', content: description },
      { property: 'og:url', content: canonicalUrl },
      { property: 'og:image', content: ogImage },
      { property: 'og:site_name', content: SITE_NAME },

      // Twitter Card
      { name: 'twitter:card', content: 'summary_large_image' },
      { name: 'twitter:title', content: fullTitle },
      { name: 'twitter:description', content: description },
      { name: 'twitter:image', content: ogImage },
    ],
    link: [
      { rel: 'canonical', href: canonicalUrl },
    ],
    script: jsonLdScript,
  })
}
