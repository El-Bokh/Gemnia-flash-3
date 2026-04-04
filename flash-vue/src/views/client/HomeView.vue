<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useChatStore } from '@/stores/chat'
import Button from 'primevue/button'
import ChatInput from '@/components/chat/ChatInput.vue'
import ChatMessage from '@/components/chat/ChatMessage.vue'
import ImageStyleSelector from '@/components/chat/ImageStyleSelector.vue'
import TypingIndicator from '@/components/chat/TypingIndicator.vue'

const { t } = useI18n()
const chat = useChatStore()

const messagesContainerRef = ref<HTMLDivElement | null>(null)
const showStyles = ref(false)
const selectedStyle = ref('')
const showScrollBtn = ref(false)

const hasActiveChat = computed(() => !!chat.activeConversation)
const messages = computed(() => chat.activeConversation?.messages ?? [])

const suggestions = computed(() => [
  { icon: 'pi pi-image', label: t('client.suggestDesign'), color: '#8b5cf6' },
  { icon: 'pi pi-pencil', label: t('client.suggestEdit'), color: '#0ea5e9' },
  { icon: 'pi pi-palette', label: t('client.suggestStyle'), color: '#f59e0b' },
  { icon: 'pi pi-sparkles', label: t('client.suggestCreate'), color: '#10b981' },
])

function scrollToBottom(smooth = true) {
  nextTick(() => {
    const el = messagesContainerRef.value
    if (el) {
      el.scrollTo({
        top: el.scrollHeight,
        behavior: smooth ? 'smooth' : 'instant',
      })
    }
  })
}

function handleScroll() {
  const el = messagesContainerRef.value
  if (!el) return
  const distFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight
  showScrollBtn.value = distFromBottom > 100
}

watch(messages, () => {
  scrollToBottom()
}, { deep: true })

watch(() => chat.isAiTyping, (typing) => {
  if (typing) scrollToBottom()
})

onMounted(() => {
  chat.loadConversations()
  if (messages.value.length) {
    scrollToBottom(false)
  }
})

function handleSend(content: string) {
  chat.sendMessage(content, selectedStyle.value || undefined)
  selectedStyle.value = ''
  showStyles.value = false
}

function useSuggestion(text: string) {
  if (!chat.activeConversationId) {
    chat.createConversation()
  }
  chat.sendMessage(text)
}

function handleStyleSelect(style: string) {
  selectedStyle.value = style
}

function handleCopy(content: string) {
  // Toast event
  window.dispatchEvent(
    new CustomEvent('app-toast', {
      detail: { type: 'success', message: t('chat.copied') },
    }),
  )
}

function handleRegenerate(_messageId: string) {
  // Future: regenerate
  window.dispatchEvent(
    new CustomEvent('app-toast', {
      detail: { type: 'info', message: t('chat.regenerating') },
    }),
  )
}
</script>

<template>
  <div class="home-page" :class="{ 'has-chat': hasActiveChat }">
    <!-- ═══════ Empty State / Landing ═══════ -->
    <div v-if="!hasActiveChat" class="home-center">
      <div class="hero-logo">
        <div class="logo-circle">
          <i class="pi pi-sparkles" />
        </div>
      </div>

      <h1 class="hero-title">{{ t('client.heroTitle') }}</h1>
      <p class="hero-sub">{{ t('client.heroSub') }}</p>

      <!-- Prompt box for empty state -->
      <ChatInput
        @send="handleSend"
        @toggle-styles="showStyles = !showStyles"
      />

      <!-- Style selector -->
      <Transition name="slide-up">
        <ImageStyleSelector
          v-if="showStyles"
          @select="handleStyleSelect"
          @close="showStyles = false"
        />
      </Transition>

      <!-- Suggestions -->
      <div class="suggestions">
        <button
          v-for="(s, i) in suggestions"
          :key="i"
          class="suggestion-chip"
          @click="useSuggestion(s.label)"
        >
          <i :class="s.icon" :style="{ color: s.color }" />
          <span>{{ s.label }}</span>
        </button>
      </div>

      <!-- Footer -->
      <footer class="home-footer">
        <span>{{ t('client.footerDisclaimer') }}</span>
      </footer>
    </div>

    <!-- ═══════ Active Chat ═══════ -->
    <template v-else>
      <!-- Messages area -->
      <div
        ref="messagesContainerRef"
        class="messages-container"
        @scroll="handleScroll"
      >
        <!-- Chat header / conversation info -->
        <div class="chat-header-area">
          <div class="chat-header-content">
            <div class="chat-header-icon">
              <i class="pi pi-sparkles" />
            </div>
            <div>
              <h2 class="chat-header-title">{{ chat.activeConversation?.title }}</h2>
              <p class="chat-header-sub">{{ t('chat.chatHeaderSub') }}</p>
            </div>
          </div>
        </div>

        <!-- Messages -->
        <div class="messages-list">
          <ChatMessage
            v-for="(msg, idx) in messages"
            :key="msg.id"
            :message="msg"
            :is-last="idx === messages.length - 1 && !chat.isAiTyping"
            @copy="handleCopy"
            @regenerate="handleRegenerate"
          />

          <!-- Typing indicator -->
          <TypingIndicator v-if="chat.isAiTyping" />
        </div>

        <!-- Scroll to bottom button -->
        <Transition name="pop">
          <button
            v-if="showScrollBtn"
            class="scroll-bottom-btn"
            @click="scrollToBottom()"
          >
            <i class="pi pi-arrow-down" />
          </button>
        </Transition>
      </div>

      <!-- Bottom input area -->
      <div class="chat-bottom">
        <!-- Style selector -->
        <Transition name="slide-up">
          <ImageStyleSelector
            v-if="showStyles"
            @select="handleStyleSelect"
            @close="showStyles = false"
          />
        </Transition>

        <!-- Selected style badge -->
        <div v-if="selectedStyle && !showStyles" class="selected-style-badge">
          <i class="pi pi-palette" />
          <span>{{ t(`chat.style_${selectedStyle}`) }}</span>
          <button class="badge-remove" @click="selectedStyle = ''">
            <i class="pi pi-times" />
          </button>
        </div>

        <ChatInput
          :disabled="chat.isAiTyping"
          @send="handleSend"
          @toggle-styles="showStyles = !showStyles"
        />

        <div class="chat-footer-disclaimer">
          {{ t('client.footerDisclaimer') }}
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.home-page {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 52px);
}

/* ══ Empty State ══ */
.home-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  max-width: 780px;
  width: 100%;
  margin: 0 auto;
  gap: 16px;
  padding: 24px 16px;
}

.hero-logo {
  margin-bottom: 8px;
  animation: logoFloat 3s ease-in-out infinite;
}

@keyframes logoFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}

.logo-circle {
  width: 60px;
  height: 60px;
  border-radius: 18px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.5rem;
  box-shadow: 0 8px 32px rgba(99, 102, 241, 0.25);
}

.hero-title {
  font-size: 1.7rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
  text-align: center;
  letter-spacing: -0.02em;
}

.hero-sub {
  font-size: 0.88rem;
  color: var(--text-muted);
  margin: 0;
  text-align: center;
  max-width: 460px;
  line-height: 1.5;
}

/* Suggestions */
.suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: center;
  margin-top: 4px;
  max-width: 780px;
  padding: 0 16px;
}

.suggestion-chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 10px 18px;
  border-radius: 14px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  color: var(--text-secondary);
  font-size: 0.78rem;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.suggestion-chip:hover {
  border-color: var(--active-color);
  background: var(--active-bg);
  color: var(--text-primary);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.suggestion-chip i {
  font-size: 0.82rem;
}

.home-footer {
  padding: 16px;
  text-align: center;
  font-size: 0.68rem;
  color: var(--text-muted);
  margin-top: auto;
}

/* ══ Active Chat ══ */
.messages-container {
  flex: 1;
  overflow-y: auto;
  position: relative;
  scroll-behavior: smooth;
  scrollbar-width: thin;
  scrollbar-color: var(--card-border) transparent;
}

.chat-header-area {
  display: flex;
  justify-content: center;
  padding: 32px 16px 16px;
}

.chat-header-content {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 24px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 16px;
  max-width: 500px;
  width: 100%;
}

.chat-header-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}

.chat-header-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.chat-header-sub {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin: 2px 0 0;
}

.messages-list {
  padding: 8px 0 24px;
}

/* Scroll to bottom */
.scroll-bottom-btn {
  position: sticky;
  bottom: 16px;
  left: 50%;
  transform: translateX(-50%);
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  color: var(--text-secondary);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  z-index: 10;
  font-size: 0.78rem;
  transition: background 0.15s, color 0.15s;
}

.scroll-bottom-btn:hover {
  background: var(--active-bg);
  color: var(--active-color);
}

/* Chat bottom */
.chat-bottom {
  border-top: 1px solid var(--card-border);
  background: var(--layout-bg);
  padding-top: 8px;
}

.selected-style-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  margin: 0 auto 8px;
  max-width: 780px;
  margin-inline-start: calc((100% - min(780px, 100%)) / 2 + 16px);
  background: var(--active-bg);
  border: 1px solid var(--active-color);
  border-radius: 10px;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--active-color);
}

.badge-remove {
  border: none;
  background: none;
  color: var(--active-color);
  cursor: pointer;
  padding: 2px;
  font-size: 0.6rem;
  opacity: 0.7;
  transition: opacity 0.14s;
}

.badge-remove:hover {
  opacity: 1;
}

.chat-footer-disclaimer {
  text-align: center;
  font-size: 0.64rem;
  color: var(--text-muted);
  padding: 4px 16px 12px;
}

/* ══ Transitions ══ */
.slide-up-enter-active {
  transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.slide-up-leave-active {
  transition: all 0.15s ease-in;
}

.slide-up-enter-from {
  opacity: 0;
  transform: translateY(12px);
}

.slide-up-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.pop-enter-active {
  transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.pop-leave-active {
  transition: all 0.15s;
}

.pop-enter-from {
  opacity: 0;
  transform: translateX(-50%) scale(0.8);
}

.pop-leave-to {
  opacity: 0;
  transform: translateX(-50%) scale(0.9);
}

/* ══ Responsive ══ */
@media (max-width: 640px) {
  .hero-title {
    font-size: 1.3rem;
  }

  .suggestions {
    gap: 6px;
  }

  .suggestion-chip {
    padding: 8px 14px;
    font-size: 0.74rem;
  }

  .chat-header-content {
    padding: 12px 16px;
  }

  .chat-header-area {
    padding: 16px 8px 8px;
  }
}
</style>
