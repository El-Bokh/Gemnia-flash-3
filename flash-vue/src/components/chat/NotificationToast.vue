<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

interface Toast {
  id: number
  type: 'success' | 'info' | 'warn' | 'error'
  message: string
  duration: number
}

const toasts = ref<Toast[]>([])
let counter = 0

function addToast(type: Toast['type'], message: string, duration = 4000) {
  const id = ++counter
  toasts.value.push({ id, type, message, duration })
  setTimeout(() => removeToast(id), duration)
}

function removeToast(id: number) {
  const idx = toasts.value.findIndex(t => t.id === id)
  if (idx !== -1) toasts.value.splice(idx, 1)
}

const iconMap: Record<string, string> = {
  success: 'pi pi-check-circle',
  info: 'pi pi-info-circle',
  warn: 'pi pi-exclamation-triangle',
  error: 'pi pi-times-circle',
}

// Expose for parent components
defineExpose({ addToast })

// Global event bus
function handleCustomToast(e: Event) {
  const detail = (e as CustomEvent).detail
  addToast(detail.type || 'info', detail.message, detail.duration)
}

onMounted(() => {
  window.addEventListener('app-toast', handleCustomToast)
})

onUnmounted(() => {
  window.removeEventListener('app-toast', handleCustomToast)
})
</script>

<template>
  <Teleport to="body">
    <div class="toast-container">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="toast-item"
          :class="toast.type"
          @click="removeToast(toast.id)"
        >
          <i :class="iconMap[toast.type]" />
          <span class="toast-msg">{{ toast.message }}</span>
          <button class="toast-close" @click.stop="removeToast(toast.id)">
            <i class="pi pi-times" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-container {
  position: fixed;
  top: 16px;
  inset-inline-end: 16px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 8px;
  pointer-events: none;
}

.toast-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 12px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
  font-size: 0.82rem;
  color: var(--text-primary);
  pointer-events: auto;
  cursor: pointer;
  min-width: 280px;
  max-width: 420px;
  backdrop-filter: blur(12px);
}

.toast-item i:first-child {
  font-size: 1rem;
  flex-shrink: 0;
}

.toast-item.success i:first-child { color: #10b981; }
.toast-item.info i:first-child { color: #6366f1; }
.toast-item.warn i:first-child { color: #f59e0b; }
.toast-item.error i:first-child { color: #ef4444; }

.toast-msg {
  flex: 1;
  line-height: 1.4;
}

.toast-close {
  border: none;
  background: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 2px;
  font-size: 0.72rem;
  border-radius: 4px;
  transition: color 0.14s;
}

.toast-close:hover {
  color: var(--text-primary);
}

/* Transitions */
.toast-enter-active {
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.toast-leave-active {
  transition: all 0.2s ease-in;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(40px) scale(0.9);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(40px) scale(0.9);
}

.toast-move {
  transition: transform 0.3s;
}

@media (max-width: 640px) {
  .toast-container {
    inset-inline-start: 8px;
    inset-inline-end: 8px;
  }

  .toast-item {
    min-width: auto;
  }
}
</style>
