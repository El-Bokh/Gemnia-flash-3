<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useChatStore } from '@/stores/chat'
import { useAuthStore } from '@/stores/auth'
import { useLayoutStore } from '@/stores/layout'
import { useSeo } from '@/composables/useSeo'
import Button from 'primevue/button'
import ChatInput from '@/components/chat/ChatInput.vue'
import ChatMessage from '@/components/chat/ChatMessage.vue'
import ImageStyleSelector from '@/components/chat/ImageStyleSelector.vue'
import ProductGallery from '@/components/chat/ProductGallery.vue'
import TypingIndicator from '@/components/chat/TypingIndicator.vue'
import QuotaBar from '@/components/chat/QuotaBar.vue'
import QuotaExhaustedModal from '@/components/chat/QuotaExhaustedModal.vue'
import BrandLogo from '@/components/branding/BrandLogo.vue'

const { t } = useI18n()
const router = useRouter()
const chat = useChatStore()
const auth = useAuthStore()
const layout = useLayoutStore()

useSeo({
  title: computed(() => t('seo.homeTitle')),
  description: computed(() => t('seo.homeDescription')),
  path: '/',
  jsonLd: {
    '@context': 'https://schema.org',
    '@type': 'WebApplication',
    name: 'Klek AI',
    url: 'https://klek.studio',
    applicationCategory: 'DesignApplication',
    operatingSystem: 'Web',
    description: 'AI-powered platform for generating stunning images, creative designs, and visual content.',
    offers: {
      '@type': 'Offer',
      price: '0',
      priceCurrency: 'USD',
    },
  },
})

const messagesContainerRef = ref<HTMLDivElement | null>(null)
const showStyles = ref(false)
const selectedStyle = ref('')
const selectedStyleName = ref('')
const selectedStyleThumb = ref<string | null>(null)
const showProducts = ref(false)
const selectedProduct = ref('')
const selectedProductName = ref('')
const selectedProductThumb = ref<string | null>(null)
const showScrollBtn = ref(false)
const showQuotaModal = ref(false)
const chatInputRef = ref<{
  applySuggestionAction: (action: {
    prompt?: string
    mode?: 'text' | 'image' | 'video'
    open?: 'upload' | 'styles' | 'products' | 'productGallery' | 'none'
  }) => void
} | null>(null)

interface SuggestionOption {
  label: string
  prompt: string
  mode: 'text' | 'image' | 'video'
  open?: 'upload' | 'styles' | 'products' | 'productGallery' | 'none'
}

interface SuggestionGroup {
  icon: string
  label: string
  color: string
  subs: SuggestionOption[]
}

// Watch for quota errors from the chat store
watch(() => chat.quotaError, (err) => {
  if (err) {
    showQuotaModal.value = true
  }
})

const hasActiveChat = computed(() => !!chat.activeConversation)
const messages = computed(() => chat.activeConversation?.messages ?? [])
const inputDisabled = computed(() => chat.activeConversationBusy || auth.quotaDepleted)

const suggestions = computed<SuggestionGroup[]>(() => [
  {
    icon: 'pi pi-image', label: t('client.suggestDesign'), color: '#8b5cf6',
    subs: [
      { label: t('chat.suggestLogoMinimalist'), prompt: t('chat.suggestLogoMinimalist'), mode: 'image', open: 'none' },
      { label: t('chat.suggestLogoVintage'), prompt: t('chat.suggestLogoVintage'), mode: 'image', open: 'none' },
      { label: t('chat.suggestLogoBold'), prompt: t('chat.suggestLogoBold'), mode: 'image', open: 'none' },
      { label: t('chat.suggestLogoElegant'), prompt: t('chat.suggestLogoElegant'), mode: 'image', open: 'none' },
    ],
  },
  {
    icon: 'pi pi-pencil', label: t('client.suggestEdit'), color: '#0ea5e9',
    subs: [
      { label: t('chat.suggestEditBackground'), prompt: t('chat.suggestEditBackground'), mode: 'image', open: 'upload' },
      { label: t('chat.suggestEditLighting'), prompt: t('chat.suggestEditLighting'), mode: 'image', open: 'upload' },
      { label: t('chat.suggestEditFilters'), prompt: t('chat.suggestEditFilters'), mode: 'image', open: 'upload' },
      { label: t('chat.suggestEditResize'), prompt: t('chat.suggestEditResize'), mode: 'image', open: 'upload' },
    ],
  },
  {
    icon: 'pi pi-palette', label: t('client.suggestStyle'), color: '#f59e0b',
    subs: [
      { label: t('chat.suggestStyleOil'), prompt: t('chat.suggestStyleOil'), mode: 'image', open: 'styles' },
      { label: t('chat.suggestStyleWatercolor'), prompt: t('chat.suggestStyleWatercolor'), mode: 'image', open: 'styles' },
      { label: t('chat.suggestStyleComic'), prompt: t('chat.suggestStyleComic'), mode: 'image', open: 'styles' },
      { label: t('chat.suggestStylePixel'), prompt: t('chat.suggestStylePixel'), mode: 'image', open: 'styles' },
    ],
  },
  {
    icon: 'pi pi-sparkles', label: t('client.suggestCreate'), color: '#10b981',
    subs: [
      { label: t('chat.suggestCreateForest'), prompt: t('chat.suggestCreateForest'), mode: 'image', open: 'none' },
      { label: t('chat.suggestCreateCity'), prompt: t('chat.suggestCreateCity'), mode: 'image', open: 'none' },
      { label: t('chat.suggestCreateCharacter'), prompt: t('chat.suggestCreateCharacter'), mode: 'image', open: 'none' },
      { label: t('chat.suggestCreateProduct'), prompt: t('chat.suggestCreateProduct'), mode: 'image', open: 'none' },
    ],
  },
])

const activeSuggestionIndex = ref<number | null>(null)

function toggleSuggestionPanel(index: number) {
  activeSuggestionIndex.value = activeSuggestionIndex.value === index ? null : index
}

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

function handleSend(content: string, image?: File) {
  // Guest → must login first
  if (!auth.isAuthenticated) {
    void router.push({ name: 'login', query: { redirect: '/' } })
    return
  }
  // Authenticated but no subscription → go to pricing
  if (auth.noSubscription) {
    void router.push({ name: 'pricing' })
    return
  }

  const isNewChat = !chat.activeConversationId
  chat.sendMessage(content, selectedStyle.value || undefined, image, selectedProduct.value || undefined)
  selectedStyle.value = ''
  selectedStyleName.value = ''
  selectedStyleThumb.value = null
  selectedProduct.value = ''
  selectedProductName.value = ''
  selectedProductThumb.value = null
  showStyles.value = false
  showProducts.value = false
  if (isNewChat) {
    layout.sidebarCollapsed = true
    const title = content.slice(0, 40) + (content.length > 40 ? '…' : '')
    window.dispatchEvent(
      new CustomEvent('app-toast', {
        detail: { type: 'info', message: `${t('chat.newChatStarted')}: ${title}` },
      }),
    )
  }
}

function openStylesPanel() {
  showProducts.value = false
  showStyles.value = true
}

function toggleStylesPanel() {
  showProducts.value = false
  showStyles.value = !showStyles.value
}

function openProductsPanel() {
  showStyles.value = false
  showProducts.value = true
}

function toggleProductsPanel() {
  showStyles.value = false
  showProducts.value = !showProducts.value
}

function prepareSuggestion(suggestion: SuggestionOption) {
  if (!auth.isAuthenticated) {
    void router.push({ name: 'login', query: { redirect: '/' } })
    return
  }
  if (auth.noSubscription) {
    void router.push({ name: 'pricing' })
    return
  }

  selectedProduct.value = ''
  selectedProductName.value = ''
  selectedProductThumb.value = null
  showProducts.value = false

  if (suggestion.open !== 'styles') {
    showStyles.value = false
  }

  activeSuggestionIndex.value = null
  chatInputRef.value?.applySuggestionAction({
    prompt: suggestion.prompt,
    mode: suggestion.mode,
    open: suggestion.open ?? 'none',
  })

  if (suggestion.open === 'styles') {
    openStylesPanel()
  }
}

function handleStyleSelect(info: { slug: string; name: string; thumbnail: string | null }) {
  selectedStyle.value = info.slug
  selectedStyleName.value = info.name
  selectedStyleThumb.value = info.thumbnail
    if (info.slug) {
      showStyles.value = false
    }
}

function handleProductSelect(info: { slug: string; name: string; thumbnail: string | null }) {
  selectedProduct.value = info.slug
  selectedProductName.value = info.name
  selectedProductThumb.value = info.thumbnail
    if (info.slug) {
      showProducts.value = false
    }
}

function handleCopy(content: string) {
  // Toast event
  window.dispatchEvent(
    new CustomEvent('app-toast', {
      detail: { type: 'success', message: t('chat.copied') },
    }),
  )
}

function handleRegenerate(messageId: string) {
  chat.regenerateMessage(messageId)
  window.dispatchEvent(
    new CustomEvent('app-toast', {
      detail: { type: 'info', message: t('chat.regenerating') },
    }),
  )
}

function handleInpaint(payload: { messageId?: string; content: string; image: File; mask: File; renderedImage?: File }) {
  if (!auth.isAuthenticated) {
    void router.push({ name: 'login', query: { redirect: '/' } })
    return
  }
  if (auth.noSubscription) {
    void router.push({ name: 'pricing' })
    return
  }

  const isNewChat = !chat.activeConversationId
  const sourceMessageId = payload.messageId && /^\d+$/.test(payload.messageId)
    ? Number(payload.messageId)
    : undefined

  chat.sendInpaintingMessage(payload.content, payload.image, payload.mask, sourceMessageId, payload.renderedImage)

  if (isNewChat) {
    layout.sidebarCollapsed = true
    const title = payload.content.slice(0, 40) + (payload.content.length > 40 ? '…' : '')
    window.dispatchEvent(
      new CustomEvent('app-toast', {
        detail: { type: 'info', message: `${t('chat.newChatStarted')}: ${title}` },
      }),
    )
  } else {
    window.dispatchEvent(
      new CustomEvent('app-toast', {
        detail: { type: 'info', message: t('chat.inpaintingStarted') },
      }),
    )
  }
}

function handleInputInpaint(content: string, image: File, mask: File, renderedImage?: File) {
  handleInpaint({ content, image, mask, renderedImage })
}

function handleSendProducts(content: string, images: File[]) {
  if (!auth.isAuthenticated) {
    void router.push({ name: 'login', query: { redirect: '/' } })
    return
  }
  if (auth.noSubscription) {
    void router.push({ name: 'pricing' })
    return
  }

  const isNewChat = !chat.activeConversationId
  chat.sendProductMessage(content, images)
  if (isNewChat) {
    layout.sidebarCollapsed = true
    const title = content.slice(0, 40) + (content.length > 40 ? '…' : '')
    window.dispatchEvent(
      new CustomEvent('app-toast', {
        detail: { type: 'info', message: `${t('chat.newChatStarted')}: ${title}` },
      }),
    )
  }
}
</script>

<template>
  <div class="home-page" :class="{ 'has-chat': hasActiveChat }">
    <!-- Quota Exhausted Modal -->
    <QuotaExhaustedModal
      :visible="showQuotaModal"
      :error-code="chat.quotaError?.code"
      @close="showQuotaModal = false; chat.clearQuotaError()"
    />

    <!-- ═══════ Empty State / Landing ═══════ -->
    <div v-if="!hasActiveChat" class="home-center">
      <div class="hero-logo">
        <BrandLogo class="logo-circle" />
      </div>

      <h1 class="hero-title">{{ t('client.heroTitle') }}</h1>
      <p class="hero-sub">{{ t('client.heroSub') }}</p>

      <!-- Selected style badge -->
      <div v-if="selectedStyle" class="selected-badge">
        <img v-if="selectedStyleThumb" :src="selectedStyleThumb" class="badge-thumb" :alt="selectedStyleName" />
        <i v-else class="pi pi-palette badge-icon" />
        <span class="badge-name">{{ selectedStyleName }}</span>
        <button class="badge-remove" @click="selectedStyle = ''; selectedStyleThumb = null">
          <i class="pi pi-times" />
        </button>
      </div>

      <!-- Selected product badge -->
      <div v-if="selectedProduct" class="selected-badge">
        <img v-if="selectedProductThumb" :src="selectedProductThumb" class="badge-thumb" :alt="selectedProductName" />
        <i v-else class="pi pi-shopping-bag badge-icon" />
        <span class="badge-name">{{ selectedProductName }}</span>
        <button class="badge-remove" @click="selectedProduct = ''; selectedProductThumb = null">
          <i class="pi pi-times" />
        </button>
      </div>

      <!-- Prompt box for empty state -->
      <ChatInput
        ref="chatInputRef"
        :disabled="inputDisabled"
        @send="handleSend"
        @send-products="handleSendProducts"
        @inpaint="handleInputInpaint"
        @toggle-styles="toggleStylesPanel"
        @toggle-products="toggleProductsPanel"
        @open-styles="openStylesPanel"
        @open-products="openProductsPanel"
      />

      <!-- Style selector -->
      <Transition name="slide-up">
        <ImageStyleSelector
          v-if="showStyles"
          @select="handleStyleSelect"
          @close="showStyles = false"
        />
      </Transition>

      <!-- Product gallery -->
      <Transition name="slide-up">
        <ProductGallery
          v-if="showProducts"
          @select="handleProductSelect"
          @close="showProducts = false"
        />
      </Transition>

      <!-- Suggestions -->
      <div class="suggestions">
        <button
          v-for="(s, i) in suggestions"
          :key="i"
          class="suggestion-chip"
          :class="{ active: activeSuggestionIndex === i }"
          @click="toggleSuggestionPanel(i)"
        >
          <i :class="s.icon" :style="{ color: s.color }" />
          <span>{{ s.label }}</span>
          <i class="pi pi-chevron-down chip-arrow" :class="{ rotated: activeSuggestionIndex === i }" />
        </button>
      </div>

      <!-- Sub-suggestions panel -->
      <Transition name="slide-up">
        <div v-if="activeSuggestionIndex !== null" class="sub-suggestions-panel">
          <p class="sub-suggestions-label">{{ t('chat.trySuggestion') }}</p>
          <div class="sub-suggestions-grid">
            <button
              v-for="(sub, si) in suggestions[activeSuggestionIndex]?.subs"
              :key="si"
              class="sub-suggestion-chip"
              @click="prepareSuggestion(sub)"
            >
              <i class="pi pi-arrow-up-right" />
              <span>{{ sub.label }}</span>
            </button>
          </div>
        </div>
      </Transition>

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
            <BrandLogo class="chat-header-icon" />
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
            :disabled="inputDisabled"
            @copy="handleCopy"
            @regenerate="handleRegenerate"
            @inpaint="handleInpaint"
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
        <!-- Quota bar -->
        <QuotaBar />

        <!-- Style selector -->
        <Transition name="slide-up">
          <ImageStyleSelector
            v-if="showStyles"
            @select="handleStyleSelect"
            @close="showStyles = false"
          />
        </Transition>

        <!-- Product gallery -->
        <Transition name="slide-up">
          <ProductGallery
            v-if="showProducts"
            @select="handleProductSelect"
            @close="showProducts = false"
          />
        </Transition>

        <!-- Selected style badge -->
        <div v-if="selectedStyle" class="selected-badge">
          <img v-if="selectedStyleThumb" :src="selectedStyleThumb" class="badge-thumb" :alt="selectedStyleName" />
          <i v-else class="pi pi-palette badge-icon" />
          <span class="badge-name">{{ selectedStyleName }}</span>
          <button class="badge-remove" @click="selectedStyle = ''; selectedStyleThumb = null">
            <i class="pi pi-times" />
          </button>
        </div>

        <!-- Selected product badge -->
        <div v-if="selectedProduct" class="selected-badge">
          <img v-if="selectedProductThumb" :src="selectedProductThumb" class="badge-thumb" :alt="selectedProductName" />
          <i v-else class="pi pi-shopping-bag badge-icon" />
          <span class="badge-name">{{ selectedProductName }}</span>
          <button class="badge-remove" @click="selectedProduct = ''; selectedProductThumb = null">
            <i class="pi pi-times" />
          </button>
        </div>

        <ChatInput
          ref="chatInputRef"
          :disabled="inputDisabled"
          @send="handleSend"
          @send-products="handleSendProducts"
          @inpaint="handleInputInpaint"
          @toggle-styles="toggleStylesPanel"
          @toggle-products="toggleProductsPanel"
          @open-styles="openStylesPanel"
          @open-products="openProductsPanel"
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
  padding-top: 12vh;
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
  width: 76px;
  height: 76px;
  display: flex;
  object-fit: contain;
  filter: drop-shadow(0 8px 20px rgba(99, 102, 241, 0.24));
}

.hero-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
  text-align: center;
  letter-spacing: -0.02em;
}

.hero-sub {
  font-size: 0.82rem;
  color: var(--text-muted);
  margin: 0;
  text-align: center;
  max-width: 400px;
  line-height: 1.45;
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
  gap: 6px;
  padding: 8px 14px;
  border-radius: 12px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  color: var(--text-secondary);
  font-size: 0.74rem;
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
  font-size: 0.76rem;
}

.chip-arrow {
  font-size: 0.6rem;
  transition: transform 0.2s;
  opacity: 0.5;
}

.chip-arrow.rotated {
  transform: rotate(180deg);
}

.suggestion-chip.active {
  border-color: var(--active-color);
  background: var(--active-bg);
  color: var(--text-primary);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

/* Sub-suggestions panel */
.sub-suggestions-panel {
  width: 100%;
  max-width: 780px;
  padding: 0 16px;
}

.sub-suggestions-label {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin: 0 0 8px;
  font-weight: 600;
}

.sub-suggestions-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.sub-suggestion-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  color: var(--text-secondary);
  font-size: 0.76rem;
  cursor: pointer;
  transition: all 0.2s;
  text-align: start;
}

.sub-suggestion-chip:hover {
  border-color: var(--active-color);
  background: var(--active-bg);
  color: var(--text-primary);
  transform: translateY(-1px);
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
}

.sub-suggestion-chip i {
  font-size: 0.68rem;
  color: var(--active-color);
  flex-shrink: 0;
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
  padding: 20px 16px 8px;
}

.chat-header-content {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 12px;
  max-width: 420px;
  width: 100%;
}

.chat-header-icon {
  width: 36px;
  height: 36px;
  display: flex;
  flex-shrink: 0;
  object-fit: contain;
}

.chat-header-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.chat-header-sub {
  font-size: 0.66rem;
  color: var(--text-muted);
  margin: 1px 0 0;
}

.messages-list {
  padding: 4px 0 16px;
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

.selected-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 6px 12px 6px 6px;
  margin: 0 auto 8px;
  max-width: 780px;
  margin-inline-start: calc((100% - min(780px, 100%)) / 2 + 16px);
  background: var(--card-bg);
  border: 1.5px solid var(--active-color);
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-primary);
  box-shadow: 0 4px 16px rgba(99, 102, 241, 0.1);
}

.badge-thumb {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  object-fit: cover;
  flex-shrink: 0;
}

.badge-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--active-bg);
  color: var(--active-color);
  font-size: 0.9rem;
  flex-shrink: 0;
}

.badge-name {
  font-size: 0.78rem;
  color: var(--text-primary);
  font-weight: 600;
}

.badge-remove {
  border: none;
  background: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px;
  font-size: 0.6rem;
  opacity: 0.7;
  transition: opacity 0.14s;
  margin-inline-start: auto;
}

.badge-remove:hover {
  opacity: 1;
  color: var(--text-primary);
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

  .sub-suggestions-grid {
    grid-template-columns: 1fr;
  }

  .sub-suggestion-chip {
    padding: 10px 12px;
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
