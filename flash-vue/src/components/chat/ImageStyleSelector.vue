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
        class="style-card"
        :class="{ selected: selectedStyle === s.slug }"
        @click="selectStyle(s.slug)"
      >
        <div class="style-icon" :style="{ background: getGradient(s) }">
          <i :class="getIcon(s)" />
        </div>
        <span class="style-name">{{ s.name }}</span>
        <span v-if="s.is_premium" class="style-premium">PRO</span>
        <div v-if="selectedStyle === s.slug" class="style-check">
          <i class="pi pi-check" />
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
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 8px;
}

.style-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 14px 8px;
  border: 1.5px solid var(--card-border);
  border-radius: 14px;
  background: var(--card-bg);
  cursor: pointer;
  transition: border-color 0.2s, transform 0.15s, box-shadow 0.2s;
}

.style-card:hover {
  border-color: var(--text-muted);
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}

.style-card.selected {
  border-color: var(--active-color);
  background: var(--active-bg);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.style-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1rem;
  transition: transform 0.2s;
}

.style-card:hover .style-icon {
  transform: scale(1.08);
}

.style-name {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-primary);
  text-align: center;
}

.style-check {
  position: absolute;
  top: 6px;
  inset-inline-end: 6px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--active-color);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6rem;
  animation: checkPop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.style-premium {
  position: absolute;
  top: 6px;
  inset-inline-start: 6px;
  font-size: 0.5rem;
  font-weight: 700;
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #fff;
  padding: 1px 5px;
  border-radius: 4px;
  letter-spacing: 0.04em;
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
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
  }

  .style-card {
    padding: 10px 6px;
  }

  .style-icon {
    width: 34px;
    height: 34px;
    font-size: 0.85rem;
  }
}
</style>
