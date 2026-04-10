<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { getProducts } from '@/services/chatService'
import type { ProductData } from '@/services/chatService'

const { t } = useI18n()

const emit = defineEmits<{
  select: [info: { slug: string; name: string; thumbnail: string | null }]
  close: []
}>()

const selectedProduct = ref<string | null>(null)
const products = ref<ProductData[]>([])
const loading = ref(false)

const categoryIcons: Record<string, string> = {
  electronics: 'pi pi-microchip-ai',
  clothing: 'pi pi-tag',
  food: 'pi pi-apple',
  beauty: 'pi pi-sparkles',
  home: 'pi pi-home',
  sports: 'pi pi-heart',
  toys: 'pi pi-gift',
}

const categoryGradients: Record<string, string> = {
  electronics: 'linear-gradient(135deg, #0ea5e9, #06b6d4)',
  clothing: 'linear-gradient(135deg, #a855f7, #d946ef)',
  food: 'linear-gradient(135deg, #10b981, #059669)',
  beauty: 'linear-gradient(135deg, #f59e0b, #d97706)',
  home: 'linear-gradient(135deg, #6366f1, #8b5cf6)',
  sports: 'linear-gradient(135deg, #ef4444, #dc2626)',
  toys: 'linear-gradient(135deg, #ec4899, #f43f5e)',
}

const apiOrigin = (import.meta.env.VITE_API_BASE_URL || 'https://klek.studio/api').replace(/\/api\/?$/, '')

onMounted(async () => {
  loading.value = true
  try {
    const res = await getProducts()
    if (res.success && res.data) {
      products.value = res.data
    }
  } catch {
    // Fallback — keep empty
  } finally {
    loading.value = false
  }
})

function getIcon(product: ProductData) {
  return categoryIcons[product.category ?? ''] ?? 'pi pi-shopping-bag'
}

function getGradient(product: ProductData) {
  return categoryGradients[product.category ?? ''] ?? 'linear-gradient(135deg, #6366f1, #8b5cf6)'
}

function resolveThumbnail(product: ProductData) {
  const thumbnail = product.thumbnail ?? null
  if (!thumbnail) return null
  if (/^https?:\/\//i.test(thumbnail)) return thumbnail

  const normalizedPath = thumbnail.startsWith('/') ? thumbnail : `/${thumbnail}`
  return `${apiOrigin}${normalizedPath}`
}

function formatCategory(category: string | null) {
  if (!category) return ''
  return category.charAt(0).toUpperCase() + category.slice(1)
}

function selectProduct(slug: string) {
  const product = products.value.find(p => p.slug === slug)
  if (selectedProduct.value === slug) {
    selectedProduct.value = null
    emit('select', { slug: '', name: '', thumbnail: null })
  } else {
    selectedProduct.value = slug
    emit('select', {
      slug,
      name: product?.name ?? slug,
      thumbnail: product ? resolveThumbnail(product) : null,
    })
  }
}
</script>

<template>
  <div class="product-selector">
    <div class="product-header">
      <span class="product-label">{{ t('chat.selectProduct') }}</span>
      <button class="product-close" @click="emit('close')">
        <i class="pi pi-times" />
      </button>
    </div>

    <div v-if="loading" class="product-loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <div v-else class="product-grid">
      <button
        v-for="p in products"
        :key="p.slug"
        type="button"
        class="product-card"
        :class="{ selected: selectedProduct === p.slug }"
        @click="selectProduct(p.slug)"
      >
        <div class="product-preview" :style="{ background: getGradient(p) }">
          <img
            v-if="resolveThumbnail(p)"
            :src="resolveThumbnail(p) || undefined"
            :alt="p.name"
            class="product-preview-image"
            loading="lazy"
            decoding="async"
          >
          <div v-else class="product-icon">
            <i :class="getIcon(p)" />
          </div>
          <div class="product-preview-scrim" />
          <span v-if="formatCategory(p.category)" class="product-category">{{ formatCategory(p.category) }}</span>
          <span v-if="p.is_premium" class="product-premium">PRO</span>
          <div v-if="selectedProduct === p.slug" class="product-check">
            <i class="pi pi-check" />
          </div>
        </div>
        <div class="product-meta">
          <span class="product-name">{{ p.name }}</span>
          <span v-if="p.description" class="product-description">{{ p.description }}</span>
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.product-selector {
  width: 100%;
  max-width: 780px;
  margin: 0 auto;
  padding: 0 16px 8px;
}

.product-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.product-label {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.product-close {
  border: none;
  background: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: color 0.14s, background 0.14s;
  font-size: 0.8rem;
}

.product-close:hover {
  color: var(--text-primary);
  background: var(--hover-bg);
}

.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
  gap: 12px;
}

.product-card {
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

.product-card:hover {
  border-color: rgba(99, 102, 241, 0.26);
  transform: translateY(-3px);
  box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
}

.product-card.selected {
  border-color: var(--active-color);
  background: var(--active-bg);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14), 0 16px 30px rgba(99, 102, 241, 0.14);
}

.product-preview {
  position: relative;
  width: 100%;
  aspect-ratio: 4 / 3;
  border-radius: 14px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #dbeafe;
}

.product-preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.28s ease;
}

.product-card:hover .product-preview-image {
  transform: scale(1.05);
}

.product-preview-scrim {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(15, 23, 42, 0.04) 0%, rgba(15, 23, 42, 0.12) 58%, rgba(15, 23, 42, 0.72) 100%);
  pointer-events: none;
}

.product-icon {
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

.product-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-height: 56px;
}

.product-name {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.25;
}

.product-description {
  font-size: 0.66rem;
  line-height: 1.45;
  color: var(--text-muted);
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
}

.product-check {
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

.product-premium {
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

.product-category {
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

.product-loading {
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
  .product-selector {
    padding: 0 8px 8px;
  }

  .product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }

  .product-card {
    padding: 8px;
  }

  .product-name {
    font-size: 0.76rem;
  }

  .product-description {
    font-size: 0.62rem;
  }
}
</style>
