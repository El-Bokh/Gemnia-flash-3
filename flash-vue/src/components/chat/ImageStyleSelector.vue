<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const emit = defineEmits<{
  select: [style: string]
  close: []
}>()

const selectedStyle = ref<string | null>(null)

const styles = [
  { key: 'modern', icon: 'pi pi-bolt', gradient: 'linear-gradient(135deg, #6366f1, #8b5cf6)' },
  { key: 'classic', icon: 'pi pi-clock', gradient: 'linear-gradient(135deg, #d97706, #f59e0b)' },
  { key: 'portrait', icon: 'pi pi-user', gradient: 'linear-gradient(135deg, #ec4899, #f43f5e)' },
  { key: 'cinematic', icon: 'pi pi-video', gradient: 'linear-gradient(135deg, #0ea5e9, #06b6d4)' },
  { key: 'anime', icon: 'pi pi-star', gradient: 'linear-gradient(135deg, #a855f7, #d946ef)' },
  { key: 'realistic', icon: 'pi pi-eye', gradient: 'linear-gradient(135deg, #10b981, #059669)' },
]

function selectStyle(key: string) {
  if (selectedStyle.value === key) {
    selectedStyle.value = null
    emit('select', '')
  } else {
    selectedStyle.value = key
    emit('select', key)
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
    <div class="style-grid">
      <button
        v-for="s in styles"
        :key="s.key"
        class="style-card"
        :class="{ selected: selectedStyle === s.key }"
        @click="selectStyle(s.key)"
      >
        <div class="style-icon" :style="{ background: s.gradient }">
          <i :class="s.icon" />
        </div>
        <span class="style-name">{{ t(`chat.style_${s.key}`) }}</span>
        <div v-if="selectedStyle === s.key" class="style-check">
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
