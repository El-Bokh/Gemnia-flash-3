<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'

const { t } = useI18n()
const router = useRouter()

defineProps<{
  visible: boolean
  errorCode?: string
}>()

const emit = defineEmits<{
  close: []
}>()

function goToUpgrade() {
  emit('close')
  router.push({ name: 'pricing' })
}
</script>

<template>
  <Transition name="fade">
    <div v-if="visible" class="modal-overlay" @click.self="emit('close')">
      <div class="modal-card">
        <div class="modal-icon">
          <i class="pi pi-exclamation-triangle" />
        </div>

        <h3 class="modal-title">
          {{ errorCode === 'no_subscription'
            ? t('quota.noSubscriptionTitle')
            : t('quota.exhaustedTitle')
          }}
        </h3>

        <p class="modal-desc">
          {{ errorCode === 'no_subscription'
            ? t('quota.noSubscriptionDesc')
            : t('quota.exhaustedDesc')
          }}
        </p>

        <div class="modal-actions">
          <Button
            :label="t('quota.upgradePlan')"
            icon="pi pi-arrow-up"
            class="upgrade-btn"
            @click="goToUpgrade"
          />
          <Button
            :label="t('quota.maybeLater')"
            severity="secondary"
            text
            @click="emit('close')"
          />
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}

.modal-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 20px;
  padding: 32px;
  max-width: 400px;
  width: 90%;
  text-align: center;
  animation: modalPop 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes modalPop {
  from { transform: scale(0.9); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.modal-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, #fef3c7, #fde68a);
  color: #d97706;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  margin: 0 auto 16px;
}

.modal-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 8px;
}

.modal-desc {
  font-size: 0.82rem;
  color: var(--text-muted);
  line-height: 1.5;
  margin: 0 0 24px;
}

.modal-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.upgrade-btn {
  background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
  border: none !important;
  font-weight: 600 !important;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
