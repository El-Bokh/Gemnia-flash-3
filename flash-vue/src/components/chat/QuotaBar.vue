<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const q = computed(() => auth.quota)
const barWidth = computed(() => Math.min(q.value.usage_percentage, 100))
const barColor = computed(() => {
  if (q.value.warning_level === 'depleted') return '#ef4444'
  if (q.value.warning_level === 'critical') return '#f97316'
  if (q.value.warning_level === 'low') return '#eab308'
  return '#6366f1'
})
</script>

<template>
  <div v-if="q.has_subscription" class="quota-bar">
    <div class="quota-info">
      <span class="quota-plan">{{ q.plan_name }}</span>
      <span class="quota-count">
        {{ q.credits_remaining }} / {{ q.credits_total }}
        {{ t('quota.creditsLeft') }}
      </span>
    </div>
    <div class="quota-track">
      <div
        class="quota-fill"
        :style="{ width: barWidth + '%', background: barColor }"
      />
    </div>
    <div v-if="q.warning_level === 'low'" class="quota-warning low">
      <i class="pi pi-exclamation-triangle" />
      {{ t('quota.warningLow') }}
    </div>
    <div v-else-if="q.warning_level === 'critical'" class="quota-warning critical">
      <i class="pi pi-exclamation-circle" />
      {{ t('quota.warningCritical') }}
    </div>
  </div>
</template>

<style scoped>
.quota-bar {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 8px 12px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 10px;
  margin: 0 auto 6px;
  max-width: 720px;
  width: 100%;
}

.quota-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.quota-plan {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-primary);
}

.quota-count {
  font-size: 0.68rem;
  color: var(--text-muted);
  font-variant-numeric: tabular-nums;
}

.quota-track {
  height: 4px;
  background: var(--hover-bg);
  border-radius: 4px;
  overflow: hidden;
}

.quota-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease, background 0.3s ease;
}

.quota-warning {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.65rem;
  font-weight: 500;
  margin-top: 2px;
}

.quota-warning.low {
  color: #eab308;
}

.quota-warning.critical {
  color: #f97316;
}

.quota-warning i {
  font-size: 0.7rem;
}
</style>
