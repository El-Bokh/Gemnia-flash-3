<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import InpaintingEditor from '@/components/chat/InpaintingEditor.vue'
import MaskHighlight from '@/components/chat/MaskHighlight.vue'
import type { ChatMessage } from '@/stores/chat'

const { t } = useI18n()

const props = defineProps<{
  message: ChatMessage
  isLast?: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{
  copy: [content: string]
  regenerate: [messageId: string]
  inpaint: [payload: { messageId: string; content: string; image: File; mask: File; renderedImage?: File }]
}>()

const showActions = ref(false)
const copied = ref(false)
const showFullscreen = ref(false)
const fullscreenImageSrc = ref('')
const showInpaintEditor = ref(false)

function handleDownloadImage() {
  const src = fullscreenImageSrc.value || props.message.imageUrl
  if (!src) return
  const link = document.createElement('a')
  link.href = src
  link.download = `generated-image-${props.message.id}.png`
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

function handleDownloadVideo() {
  const src = props.message.videoUrl
  if (!src) return

  const link = document.createElement('a')
  link.href = src
  link.download = `generated-video-${props.message.id}.mp4`
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

function handleExpandImage(src?: string) {
  fullscreenImageSrc.value = src || props.message.imageUrl || ''
  showFullscreen.value = true
}

function closeFullscreen() {
  showFullscreen.value = false
  fullscreenImageSrc.value = ''
}

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

function handleOpenInpaint() {
  if (!props.message.imageUrl || props.disabled) return
  showInpaintEditor.value = true
}

function handleInpaintSubmit(payload: { content: string; image: File; mask: File; renderedImage?: File }) {
  emit('inpaint', {
    messageId: props.message.id,
    ...payload,
  })
  showInpaintEditor.value = false
}
</script>

<template>
  <div
    class="chat-message"
    :class="[
      message.role,
      {
        'has-image': message.imageUrl,
        'has-video': message.videoUrl,
        'image-only': message.imageUrl && !message.productImages?.length && !message.content?.trim(),
        'media-only': (message.imageUrl || message.videoUrl) && !message.productImages?.length && !message.content?.trim(),
      },
    ]"
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
        <!-- Product images grid -->
        <div v-if="message.productImages?.length" class="product-images-grid">
          <div
            v-for="(img, idx) in message.productImages"
            :key="idx"
            class="product-img-item"
            @click="handleExpandImage(img)"
          >
            <img :src="img" :alt="`Product ${idx + 1}`" class="product-img" loading="lazy" />
          </div>
        </div>

        <p v-if="message.content?.trim()" class="msg-text">{{ message.content }}</p>

        <div v-if="message.status === 'processing'" class="processing-pill">
          <i class="pi pi-spin pi-spinner" />
          <span>{{ t('chat.videoProcessing') }}</span>
        </div>

        <!-- Generated image (single) -->
        <div v-if="message.imageUrl && !message.productImages?.length" class="msg-image-wrap">
          <img :src="message.imageUrl" alt="Generated image" class="msg-image" loading="lazy" />
          <MaskHighlight
            v-if="message.role === 'user' && message.maskImageUrl"
            :src="message.maskImageUrl"
          />
          <div class="image-overlay">
            <Button
              icon="pi pi-pencil"
              severity="secondary"
              text
              rounded
              size="small"
              :title="t('chat.editMaskedArea')"
              :disabled="disabled || message.status !== 'sent'"
              @click="handleOpenInpaint"
            />
            <Button icon="pi pi-download" severity="secondary" text rounded size="small" @click="handleDownloadImage" />
            <Button icon="pi pi-expand" severity="secondary" text rounded size="small" @click="handleExpandImage()" />
          </div>
        </div>

        <!-- Generated video -->
        <div v-if="message.videoUrl" class="msg-video-wrap">
          <video :src="message.videoUrl" class="msg-video" controls preload="metadata" playsinline />
          <div class="video-overlay">
            <Button icon="pi pi-download" severity="secondary" text rounded size="small" :title="t('chat.downloadVideo')" @click="handleDownloadVideo" />
          </div>
        </div>
      </div>

    <!-- Fullscreen overlay -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showFullscreen" class="fullscreen-overlay" @click="closeFullscreen">
          <img :src="fullscreenImageSrc" alt="Full image" class="fullscreen-image" @click.stop />
          <Button icon="pi pi-times" severity="secondary" text rounded class="fullscreen-close" @click="closeFullscreen" />
          <Button icon="pi pi-download" severity="secondary" text rounded class="fullscreen-download" @click.stop="handleDownloadImage" />
        </div>
      </Transition>
    </Teleport>

    <InpaintingEditor
      :visible="showInpaintEditor"
      :source-url="message.imageUrl"
      :disabled="disabled"
      @close="showInpaintEditor = false"
      @submit="handleInpaintSubmit"
    />

      <!-- Actions -->
      <Transition name="fade-slide">
        <div v-if="showActions || isLast" class="msg-actions">
          <button class="action-btn" @click="handleCopy" :title="t('chat.copy')">
            <i :class="copied ? 'pi pi-check' : 'pi pi-copy'" />
          </button>
          <button
            v-if="message.role === 'assistant' && message.status === 'sent'"
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

.chat-message.assistant.image-only .msg-bubble,
.chat-message.assistant.media-only .msg-bubble {
  flex: 0 1 auto;
  max-width: 364px;
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

.chat-message.assistant.image-only .msg-content,
.chat-message.assistant.media-only .msg-content {
  display: inline-block;
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
.product-images-grid {
  display: flex;
  gap: 6px;
  margin-bottom: 6px;
  flex-wrap: wrap;
}

.product-img-item {
  flex: 0 0 auto;
  width: 100px;
  height: 100px;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  position: relative;
  transition: transform 0.2s, box-shadow 0.2s;
}

.product-img-item:hover {
  transform: scale(1.04);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.product-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.msg-image-wrap {
  position: relative;
  margin-top: 8px;
  border-radius: 10px;
  overflow: hidden;
}

.chat-message.assistant.image-only .msg-image-wrap {
  margin-top: 0;
}

.msg-image {
  width: auto;
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

.processing-pill {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  margin-top: 6px;
  padding: 6px 9px;
  border-radius: 8px;
  background: rgba(20, 184, 166, 0.08);
  color: #0d9488;
  font-size: 0.72rem;
  font-weight: 700;
}

.processing-pill:first-child {
  margin-top: 0;
}

.msg-video-wrap {
  position: relative;
  margin-top: 8px;
  border-radius: 10px;
  overflow: hidden;
  background: #0f172a;
}

.chat-message.assistant.media-only .msg-video-wrap {
  margin-top: 0;
}

.msg-video {
  width: min(360px, 70vw);
  max-height: 420px;
  display: block;
  border-radius: 10px;
  background: #0f172a;
}

.video-overlay {
  position: absolute;
  top: 6px;
  inset-inline-end: 6px;
  opacity: 0;
  transition: opacity 0.2s;
}

.msg-video-wrap:hover .video-overlay {
  opacity: 1;
}

.video-overlay :deep(.p-button) {
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

/* ─── Fullscreen overlay ─────────────────────── */
.fullscreen-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(0, 0, 0, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.fullscreen-image {
  max-width: 90vw;
  max-height: 90vh;
  object-fit: contain;
  border-radius: 8px;
  cursor: default;
}

.fullscreen-close {
  position: absolute;
  top: 16px;
  inset-inline-end: 16px;
  color: #fff !important;
  background: rgba(255, 255, 255, 0.15) !important;
  backdrop-filter: blur(4px);
}

.fullscreen-download {
  position: absolute;
  bottom: 24px;
  inset-inline-end: 24px;
  color: #fff !important;
  background: rgba(255, 255, 255, 0.15) !important;
  backdrop-filter: blur(4px);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
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

  .chat-message.assistant.image-only .msg-bubble,
  .chat-message.assistant.media-only .msg-bubble {
    max-width: 100%;
  }

  .msg-video {
    width: min(100%, 82vw);
  }

  .product-img-item {
    width: 80px;
    height: 80px;
  }
}
</style>
