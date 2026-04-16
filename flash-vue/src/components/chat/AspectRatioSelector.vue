<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const model = defineModel<string>({ default: '1:1' })

const ratios = [
  { value: '1:1', label: '1:1', w: 1, h: 1 },
  { value: '3:4', label: '3:4', w: 3, h: 4 },
  { value: '4:3', label: '4:3', w: 4, h: 3 },
  { value: '9:16', label: '9:16', w: 9, h: 16 },
  { value: '16:9', label: '16:9', w: 16, h: 9 },
]
</script>

<template>
  <div class="aspect-ratio-selector">
    <span class="ar-label">{{ t('chat.aspectRatio') }}</span>
    <div class="ar-options">
      <button
        v-for="r in ratios"
        :key="r.value"
        class="ar-option"
        :class="{ active: model === r.value }"
        :title="r.label"
        @click="model = r.value"
      >
        <div
          class="ar-shape"
          :style="{ aspectRatio: r.w + '/' + r.h }"
        />
        <span class="ar-text">{{ r.label }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.aspect-ratio-selector {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 0;
}

.ar-label {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-muted);
  white-space: nowrap;
}

.ar-options {
  display: flex;
  gap: 6px;
}

.ar-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 5px 8px;
  border: 1px solid var(--card-border);
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
  transition: all 0.2s;
  color: var(--text-muted);
}

.ar-option:hover {
  border-color: var(--active-color);
  color: var(--text-secondary);
}

.ar-option.active {
  border-color: var(--active-color);
  background: rgba(99, 102, 241, 0.08);
  color: var(--active-color);
}

.ar-shape {
  width: 22px;
  max-height: 28px;
  border: 2px solid currentColor;
  border-radius: 3px;
  transition: border-color 0.2s;
}

.ar-text {
  font-size: 0.62rem;
  font-weight: 600;
  line-height: 1;
}
</style>
