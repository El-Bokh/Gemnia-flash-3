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
            class="action-btn"
            @click="handleRegenerate"
            :title="t('chat.regenerate')"
          >
            <i class="pi pi-refresh" />
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
  gap: 12px;
  max-width: 780px;
  margin: 0 auto;
  padding: 8px 16px;
  animation: msgSlide 0.3s ease-out;
}

@keyframes msgSlide {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.chat-message.user {
  flex-direction: row-reverse;
}

.msg-avatar {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.78rem;
  margin-top: 4px;
}

.msg-avatar.assistant {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
}

.msg-avatar.user {
  background: var(--hover-bg);
  color: var(--text-secondary);
}

.msg-bubble {
  min-width: 0;
  flex: 1;
  max-width: 600px;
}

.chat-message.user .msg-bubble {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.msg-content {
  padding: 12px 16px;
  border-radius: 18px;
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

.msg-text {
  margin: 0;
  font-size: 0.88rem;
  line-height: 1.65;
  white-space: pre-wrap;
  word-break: break-word;
}

.msg-image-wrap {
  position: relative;
  margin-top: 10px;
  border-radius: 12px;
  overflow: hidden;
}

.msg-image {
  width: 100%;
  max-width: 400px;
  border-radius: 12px;
  display: block;
  transition: transform 0.3s;
}

.msg-image-wrap:hover .msg-image {
  transform: scale(1.02);
}

.image-overlay {
  position: absolute;
  bottom: 8px;
  inset-inline-end: 8px;
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
}

.msg-actions {
  display: flex;
  gap: 2px;
  margin-top: 4px;
  padding-inline-start: 4px;
}

.action-btn {
  border: none;
  background: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  transition: color 0.14s, background 0.14s;
}

.action-btn:hover {
  color: var(--text-primary);
  background: var(--hover-bg);
}

.msg-time {
  display: block;
  font-size: 0.64rem;
  color: var(--text-muted);
  margin-top: 4px;
  padding-inline-start: 4px;
}

/* Animations */
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

@media (max-width: 640px) {
  .chat-message {
    padding: 6px 8px;
    gap: 8px;
  }

  .msg-avatar {
    width: 28px;
    height: 28px;
    font-size: 0.7rem;
  }

  .msg-content {
    padding: 10px 14px;
  }

  .msg-text {
    font-size: 0.84rem;
  }
}
</style>
