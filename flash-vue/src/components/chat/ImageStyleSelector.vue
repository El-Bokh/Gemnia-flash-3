<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { getStyles } from '@/services/chatService'
import type { StyleData } from '@/services/chatService'

const { t } = useI18n()

const emit = defineEmits<{
  select: [style: string]
  close: []
}>()

const selectedStyle = ref<string | null>(null)
const styles = ref<StyleData[]>([])
const loading = ref(false)

const categoryIcons: Record<string, string> = {
  photography: 'pi pi-camera',
  illustration: 'pi pi-star',
  art: 'pi pi-palette',
  digital: 'pi pi-bolt',
  design: 'pi pi-objects-column',
}

const categoryGradients: Record<string, string> = {
  photography: 'linear-gradient(135deg, #10b981, #059669)',
  illustration: 'linear-gradient(135deg, #a855f7, #d946ef)',
  art: 'linear-gradient(135deg, #f59e0b, #d97706)',
  digital: 'linear-gradient(135deg, #0ea5e9, #06b6d4)',
  design: 'linear-gradient(135deg, #6366f1, #8b5cf6)',
}

const styleThumbnailMap: Record<string, string> = {
  realistic: '/style-gallery/realistic.jpg',
  anime: '/style-gallery/anime.jpg',
  watercolor: '/style-gallery/watercolor.jpg',
  'oil-painting': '/style-gallery/oil-painting.jpg',
  'digital-art': '/style-gallery/digital-art.jpg',
  cyberpunk: '/style-gallery/cyberpunk.jpg',
  minimalist: '/style-gallery/minimalist.jpg',
  'pop-art': '/style-gallery/pop-art.jpg',
}

const apiOrigin = (import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8099/api').replace(/\/api\/?$/, '')

onMounted(async () => {
  loading.value = true
  try {
    const res = await getStyles()
    if (res.success && res.data) {
      styles.value = res.data
    }
  } catch {
    // Fallback — keep empty
  } finally {
    loading.value = false
  }
})

function getIcon(style: StyleData) {
  return categoryIcons[style.category ?? ''] ?? 'pi pi-image'
}

function getGradient(style: StyleData) {
  return categoryGradients[style.category ?? ''] ?? 'linear-gradient(135deg, #6366f1, #8b5cf6)'
}

function resolveThumbnail(style: StyleData) {
  const thumbnail = style.thumbnail ?? styleThumbnailMap[style.slug] ?? null
  if (!thumbnail) return null
  if (/^https?:\/\//i.test(thumbnail)) return thumbnail

  const normalizedPath = thumbnail.startsWith('/') ? thumbnail : `/${thumbnail}`

  return `${apiOrigin}${normalizedPath}`
}

function formatCategory(category: string | null) {
  if (!category) return ''

  return category.charAt(0).toUpperCase() + category.slice(1)
}

function selectStyle(slug: string) {
  if (selectedStyle.value === slug) {
    selectedStyle.value = null
    emit('select', '')
  } else {
    selectedStyle.value = slug
    emit('select', slug)
  }
}
</script>

<template>
  <div class="style-selector">
    <div class="style-header">
      <span class="style-label">{{ t('chat.selectStyle') }}</span>
      <button class="style-close" @click="emit('close')">
        <i class="pi pi-times" />
      </button>
    </div>

    <div v-if="loading" class="style-loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <div v-else class="style-grid">
      <button
        v-for="s in styles"
        :key="s.slug"
        type="button"
        class="style-card"
        :class="{ selected: selectedStyle === s.slug }"
        @click="selectStyle(s.slug)"
      >
        <div class="style-preview" :style="{ background: getGradient(s) }">
          <img
            v-if="resolveThumbnail(s)"
            :src="resolveThumbnail(s) || undefined"
            :alt="s.name"
            class="style-preview-image"
            loading="lazy"
            decoding="async"
          >
          <div v-else class="style-icon">
            <i :class="getIcon(s)" />
          </div>
          <div class="style-preview-scrim" />
          <span v-if="formatCategory(s.category)" class="style-category">{{ formatCategory(s.category) }}</span>
          <span v-if="s.is_premium" class="style-premium">PRO</span>
          <div v-if="selectedStyle === s.slug" class="style-check">
            <i class="pi pi-check" />
          </div>
        </div>
        <div class="style-meta">
          <span class="style-name">{{ s.name }}</span>
          <span v-if="s.description" class="style-description">{{ s.description }}</span>
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.style-selector {
  width: 100%;
  max-width: 780px;
  margin: 0 auto;
  padding: 0 16px 8px;
}

.style-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.style-label {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.style-close {
  border: none;
  background: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: color 0.14s, background 0.14s;
  font-size: 0.8rem;
}

.style-close:hover {
  color: var(--text-primary);
  background: var(--hover-bg);
}

.style-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(152px, 1fr));
  gap: 12px;
}

.style-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 10px;
  padding: 10px;
  border: 1.5px solid var(--card-border);
  border-radius: 18px;
  background: var(--card-bg);
  cursor: pointer;
  text-align: start;
  overflow: hidden;
  transition: border-color 0.2s, transform 0.18s, box-shadow 0.2s;
}

.style-card:hover {
  border-color: rgba(99, 102, 241, 0.26);
  transform: translateY(-3px);
  box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
}

.style-card.selected {
  border-color: var(--active-color);
  background: var(--active-bg);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14), 0 16px 30px rgba(99, 102, 241, 0.14);
}

.style-preview {
  position: relative;
  width: 100%;
  aspect-ratio: 9 / 16;
  border-radius: 14px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #dbeafe;
}

.style-preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.28s ease;
}

.style-card:hover .style-preview-image {
  transform: scale(1.05);
}

.style-preview-scrim {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(15, 23, 42, 0.04) 0%, rgba(15, 23, 42, 0.12) 58%, rgba(15, 23, 42, 0.72) 100%);
  pointer-events: none;
}

.style-icon {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.2rem;
  background: rgba(255, 255, 255, 0.16);
  backdrop-filter: blur(10px);
}

.style-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-height: 56px;
}

.style-name {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.25;
}

.style-description {
  font-size: 0.66rem;
  line-height: 1.45;
  color: var(--text-muted);
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
}

.style-check {
  position: absolute;
  top: 8px;
  inset-inline-end: 8px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--active-color);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  animation: checkPop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
  box-shadow: 0 6px 16px rgba(79, 70, 229, 0.28);
}

.style-premium {
  position: absolute;
  top: 8px;
  inset-inline-start: 8px;
  font-size: 0.5rem;
  font-weight: 700;
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #fff;
  padding: 3px 6px;
  border-radius: 999px;
  letter-spacing: 0.04em;
  z-index: 1;
}

.style-category {
  position: absolute;
  inset-inline-start: 8px;
  inset-block-end: 8px;
  z-index: 1;
  max-width: calc(100% - 16px);
  padding: 4px 7px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.62);
  color: #fff;
  font-size: 0.58rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  backdrop-filter: blur(10px);
}

.style-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  color: var(--text-muted);
  font-size: 1.2rem;
}

@keyframes checkPop {
  0% { transform: scale(0); }
  100% { transform: scale(1); }
}

@media (max-width: 640px) {
  .style-selector {
    padding: 0 8px 8px;
  }

  .style-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }

  .style-card {
    padding: 8px;
  }

  .style-name {
    font-size: 0.76rem;
  }

  .style-description {
    font-size: 0.62rem;
  }
}
</style>
