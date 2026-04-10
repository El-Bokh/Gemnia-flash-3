<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import type { ChatMessage } from '@/stores/chat'

const { t } = useI18n()

const props = defineProps<{
  message: ChatMessage
  isLast?: boolean
}>()

const emit = defineEmits<{
  copy: [content: string]
  regenerate: [messageId: string]
}>()

const showActions = ref(false)
const copied = ref(false)

function handleCopy() {
  const text = props.message.content
  navigator.clipboard.writeText(text)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
  emit('copy', text)
}

function handleRegenerate() {
  emit('regenerate', props.message.id)
}
</script>

<template>
  <div
    class="chat-message"
    :class="[message.role, { 'has-image': message.imageUrl }]"
    @mouseenter="showActions = true"
    @mouseleave="showActions = false"
  >
    <!-- Avatar -->
    <div class="msg-avatar" :class="message.role">
      <i v-if="message.role === 'assistant'" class="pi pi-sparkles" />
      <i v-else class="pi pi-user" />
    </div>

    <!-- Bubble -->
    <div class="msg-bubble">
      <div class="msg-content">
        <p class="msg-text">{{ message.content }}</p>

        <!-- Generated image -->
        <div v-if="message.imageUrl" class="msg-image-wrap">
          <img :src="message.imageUrl" alt="Generated image" class="msg-image" loading="lazy" />
          <div class="image-overlay">
            <Button icon="pi pi-download" severity="secondary" text rounded size="small" />
            <Button icon="pi pi-expand" severity="secondary" text rounded size="small" />
          </div>
        </div>
      </div>

      <!-- Actions -->
      <Transition name="fade-slide">
        <div v-if="showActions || isLast" class="msg-actions">
          <button class="action-btn" @click="handleCopy" :title="t('chat.copy')">
            <i :class="copied ? 'pi pi-check' : 'pi pi-copy'" />
          </button>
          <button
            v-if="message.role === 'assistant'"
            class="action-btn regen-btn"
            @click="handleRegenerate"
            :title="t('chat.regenerateWithSame')"
          >
            <i class="pi pi-refresh" />
            <span class="regen-label">{{ t('chat.regenerate') }}</span>
          </button>
        </div>
      </Transition>

      <!-- Timestamp -->
      <span class="msg-time">
        {{ new Date(message.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.chat-message {
  display: flex;
  gap: 10px;
  max-width: 720px;
  margin: 0 auto;
  padding: 6px 16px;
  animation: msgSlide 0.25s ease-out;
}

@keyframes msgSlide {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.chat-message.user {
  flex-direction: row-reverse;
}

/* ─── Avatar ─────────────────────────────────── */
.msg-avatar {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.62rem;
  margin-top: 2px;
}

.msg-avatar.assistant {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
}

.msg-avatar.user {
  background: var(--hover-bg);
  color: var(--text-secondary);
}

/* ─── Bubble ─────────────────────────────────── */
.msg-bubble {
  min-width: 0;
  flex: 1;
  max-width: 560px;
}

.chat-message.user .msg-bubble {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.msg-content {
  padding: 8px 12px;
  border-radius: 16px;
  position: relative;
}

.chat-message.assistant .msg-content {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-start-start-radius: 4px;
}

.chat-message.user .msg-content {
  background: var(--active-color);
  color: #fff;
  border-end-end-radius: 4px;
}

/* ─── Text ───────────────────────────────────── */
.msg-text {
  margin: 0;
  font-size: 0.82rem;
  line-height: 1.55;
  white-space: pre-wrap;
  word-break: break-word;
}

/* ─── Image ──────────────────────────────────── */
.msg-image-wrap {
  position: relative;
  margin-top: 8px;
  border-radius: 10px;
  overflow: hidden;
}

.msg-image {
  width: 100%;
  max-width: 340px;
  border-radius: 10px;
  display: block;
  transition: transform 0.3s;
}

.msg-image-wrap:hover .msg-image {
  transform: scale(1.02);
}

.image-overlay {
  position: absolute;
  bottom: 6px;
  inset-inline-end: 6px;
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.2s;
}

.msg-image-wrap:hover .image-overlay {
  opacity: 1;
}

.image-overlay :deep(.p-button) {
  background: rgba(0, 0, 0, 0.6) !important;
  color: #fff !important;
  backdrop-filter: blur(4px);
  width: 28px !important;
  height: 28px !important;
}

/* ─── Actions ────────────────────────────────── */
.msg-actions {
  display: flex;
  gap: 1px;
  margin-top: 2px;
  padding-inline-start: 2px;
}

.action-btn {
  border: none;
  background: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 3px 6px;
  border-radius: 5px;
  font-size: 0.68rem;
  transition: color 0.14s, background 0.14s;
}

.action-btn:hover {
  color: var(--text-primary);
  background: var(--hover-bg);
}

.regen-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.regen-label {
  font-size: 0.64rem;
  font-weight: 600;
}

.regen-btn:hover {
  color: var(--active-color);
  background: var(--active-bg);
}

.regen-btn:hover i {
  animation: spin 0.5s ease-in-out;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* ─── Time ───────────────────────────────────── */
.msg-time {
  display: block;
  font-size: 0.58rem;
  color: var(--text-muted);
  margin-top: 2px;
  padding-inline-start: 2px;
}

/* ─── Animations ─────────────────────────────── */
.fade-slide-enter-active {
  transition: opacity 0.15s, transform 0.15s;
}

.fade-slide-leave-active {
  transition: opacity 0.1s, transform 0.1s;
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.fade-slide-leave-to {
  opacity: 0;
}

/* ─── Mobile ─────────────────────────────────── */
@media (max-width: 640px) {
  .chat-message {
    padding: 4px 8px;
    gap: 8px;
  }

  .msg-avatar {
    width: 22px;
    height: 22px;
    font-size: 0.56rem;
  }

  .msg-content {
    padding: 7px 10px;
  }

  .msg-text {
    font-size: 0.78rem;
  }

  .msg-actions {
    flex-wrap: wrap;
  }

  .regen-label {
    display: none;
  }

  .msg-image {
    max-width: 100%;
  }
}
</style>
