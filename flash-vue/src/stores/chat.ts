import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import {
  getConversations as fetchConversationsApi,
  getConversation as getConversationApi,
  createConversation as createConversationApi,
  updateConversation as updateConversationApi,
  deleteConversation as deleteConversationApi,
  sendMessage as sendMessageApi,
  regenerateMessage as regenerateMessageApi,
  sendProductMessage as sendProductMessageApi,
} from '@/services/chatService'
import type { AiMode, ConversationData, MessageData } from '@/services/chatService'
import { useAuthStore } from '@/stores/auth'

export interface ChatMessage {
  id: string
  role: 'user' | 'assistant'
  content: string
  imageUrl?: string
  videoUrl?: string
  imageStyle?: string
  productImages?: string[]
  timestamp: Date
  status: 'sending' | 'processing' | 'sent' | 'error'
}

export interface Conversation {
  id: string
  serverId: number | null
  title: string
  pinned: boolean
  messages: ChatMessage[]
  createdAt: Date
  updatedAt: Date
}

function resolveStorageUrl(path: string | null | undefined): string | undefined {
  if (!path) return undefined
  if (path.startsWith('http')) return path
  if (path.startsWith('gs://')) {
    const gcsPath = path.slice(5)
    const [bucket = '', ...objectParts] = gcsPath.split('/')
    return `https://storage.googleapis.com/${encodeURIComponent(bucket)}/${objectParts.map(encodeURIComponent).join('/')}`
  }
  // Strip /api suffix to get the backend origin
  const apiBase = import.meta.env.VITE_API_BASE_URL || ''
  const origin = apiBase.replace(/\/api\/?$/, '')
  return origin + path
}

function apiMsgToLocal(m: MessageData): ChatMessage {
  return {
    id: String(m.id),
    role: m.role,
    content: m.content,
    imageUrl: resolveStorageUrl(m.image_url),
    videoUrl: resolveStorageUrl(m.video_url),
    imageStyle: m.image_style ?? undefined,
    productImages: m.product_images?.map(url => resolveStorageUrl(url)!).filter(Boolean) ?? undefined,
    timestamp: new Date(m.created_at),
    status: m.status === 'error' ? 'error' : m.status === 'processing' ? 'processing' : m.status === 'sending' ? 'sending' : 'sent',
  }
}

function apiConvToLocal(c: ConversationData): Conversation {
  return {
    id: String(c.id),
    serverId: c.id,
    title: c.title,
    pinned: c.pinned,
    messages: (c.messages ?? []).map(apiMsgToLocal),
    createdAt: new Date(c.created_at),
    updatedAt: new Date(c.updated_at),
  }
}

function upsertConversationFromApi(conversations: Conversation[], apiConversation: ConversationData): Conversation {
  const serverConversation = apiConvToLocal(apiConversation)
  const existingIndex = conversations.findIndex(c => c.serverId === serverConversation.serverId || c.id === serverConversation.id)

  if (existingIndex === -1) {
    conversations.unshift(serverConversation)
    return serverConversation
  }

  const existing = conversations[existingIndex]
  const merged: Conversation = {
    ...existing,
    ...serverConversation,
    id: serverConversation.id,
    serverId: serverConversation.serverId,
    messages: serverConversation.messages,
  }

  conversations.splice(existingIndex, 1, merged)
  return merged
}

export const useChatStore = defineStore('chat', () => {
  const conversations = ref<Conversation[]>([])
  const activeConversationId = ref<string | null>(null)
  const isAiTyping = ref(false)
  const editingConversationId = ref<string | null>(null)
  const searchQuery = ref('')
  const loaded = ref(false)
  const quotaError = ref<{ code: string; message: string } | null>(null)

  const aiMode = ref<AiMode>('text')
  const aspectRatio = ref<string>('1:1')
  const videoAspectRatio = ref<string>('16:9')
  const videoDurationSeconds = ref<number>(8)
  const videoResolution = ref<'720p' | '1080p'>('720p')
  const videoGenerateAudio = ref(true)
  const processingPollers = new Map<number, number>()

  const activeConversation = computed(() =>
    conversations.value.find(c => c.id === activeConversationId.value) ?? null,
  )

  const activeConversationBusy = computed(() => {
    const conv = activeConversation.value

    return isAiTyping.value
      || conv?.messages.some(message => message.status === 'sending' || message.status === 'processing') === true
  })

  const pinnedConversations = computed(() =>
    conversations.value.filter(c => c.pinned),
  )

  const unpinnedConversations = computed(() =>
    conversations.value.filter(c => !c.pinned),
  )

  const filteredConversations = computed(() => {
    if (!searchQuery.value.trim()) return null
    const q = searchQuery.value.toLowerCase()
    return conversations.value.filter(c =>
      c.title.toLowerCase().includes(q),
    )
  })

  async function loadConversations() {
    if (loaded.value) return
    // Skip API call for unauthenticated visitors
    if (!localStorage.getItem('auth_token')) return
    try {
      const res = await fetchConversationsApi()
      if (res.success && res.data) {
        conversations.value = res.data.map(apiConvToLocal)
        conversations.value.forEach(conv => {
          if (conv.serverId && hasProcessingMessages(conv)) {
            startProcessingPoller(conv.serverId)
          }
        })
        loaded.value = true
      }
    } catch {
      // Silently fail — user may not be authenticated yet
    }
  }

  async function refreshConversation(serverId: number): Promise<Conversation | null> {
    try {
      const res = await getConversationApi(serverId)
      if (res.success && res.data) {
        const conv = upsertConversationFromApi(conversations.value, res.data)
        if (activeConversationId.value === String(serverId)) {
          activeConversationId.value = conv.id
        }
        if (hasProcessingMessages(conv)) {
          startProcessingPoller(serverId)
        }
        return conv
      }
    } catch (error: unknown) {
      if (isAxiosError(error) && error.response?.status === 404) {
        stopProcessingPoller(serverId)

        const staleIndex = conversations.value.findIndex(c => c.serverId === serverId)
        if (staleIndex !== -1) {
          const staleConversation = conversations.value[staleIndex]
          if (!staleConversation) return null

          conversations.value.splice(staleIndex, 1)
          if (activeConversationId.value === staleConversation.id || activeConversationId.value === String(serverId)) {
            activeConversationId.value = null
          }
        }
      }
    }

    return null
  }

  function setActiveConversation(id: string | null) {
    activeConversationId.value = id
  }

  async function createConversation(): Promise<string> {
    try {
      const res = await createConversationApi()
      if (res.success && res.data) {
        const conv = apiConvToLocal(res.data)
        conversations.value.unshift(conv)
        activeConversationId.value = conv.id
        return conv.id
      }
    } catch {
      // Fallback: create local-only conversation
    }

    const id = 'local-' + Date.now()
    const conv: Conversation = {
      id,
      serverId: null,
      title: 'New Chat',
      pinned: false,
      messages: [],
      createdAt: new Date(),
      updatedAt: new Date(),
    }
    conversations.value.unshift(conv)
    activeConversationId.value = id
    return id
  }

  async function deleteConversation(id: string) {
    const conv = conversations.value.find(c => c.id === id)
    if (conv?.serverId) {
      try {
        await deleteConversationApi(conv.serverId)
      } catch {
        // Continue with local removal
      }
    }

    const idx = conversations.value.findIndex(c => c.id === id)
    if (idx !== -1) {
      const conversation = conversations.value[idx]
      if (conversation?.serverId) {
        stopProcessingPoller(conversation.serverId)
      }
      conversations.value.splice(idx, 1)
      if (activeConversationId.value === id) {
        activeConversationId.value = null
      }
    }
  }

  async function renameConversation(id: string, newTitle: string) {
    const conv = conversations.value.find(c => c.id === id)
    if (!conv) return

    const title = newTitle.trim() || conv.title
    conv.title = title
    conv.updatedAt = new Date()
    editingConversationId.value = null

    if (conv.serverId) {
      try {
        await updateConversationApi(conv.serverId, { title })
      } catch {
        // Already updated locally
      }
    }
  }

  async function togglePin(id: string) {
    const conv = conversations.value.find(c => c.id === id)
    if (!conv) return

    conv.pinned = !conv.pinned
    conv.updatedAt = new Date()

    if (conv.serverId) {
      try {
        await updateConversationApi(conv.serverId, { pinned: conv.pinned })
      } catch {
        // Already updated locally
      }
    }
  }

  function startEditing(id: string) {
    editingConversationId.value = id
  }

  function cancelEditing() {
    editingConversationId.value = null
  }

  async function sendMessage(content: string, imageStyle?: string, image?: File, product?: string) {
    if (activeConversationBusy.value) return

    if (!activeConversationId.value) {
      await createConversation()
    }

    const conv = conversations.value.find(c => c.id === activeConversationId.value)
    if (!conv) return

    const currentMode = aiMode.value
    const currentAspectRatio = currentMode === 'image'
      ? aspectRatio.value
      : currentMode === 'video'
        ? videoAspectRatio.value
        : undefined
    const currentVideoOptions = currentMode === 'video'
      ? {
          durationSeconds: videoDurationSeconds.value,
          resolution: videoResolution.value,
          generateAudio: videoGenerateAudio.value,
        }
      : undefined

    // Build optimistic preview URL for attached image
    const optimisticImageUrl = image ? URL.createObjectURL(image) : undefined

    // Optimistic user message
    const tempUserMsgId = 'tmp-' + Date.now()
    const userMsg: ChatMessage = {
      id: tempUserMsgId,
      role: 'user',
      content,
      imageStyle,
      imageUrl: optimisticImageUrl,
      timestamp: new Date(),
      status: 'sending',
    }
    conv.messages.push(userMsg)
    conv.updatedAt = new Date()

    if (conv.messages.filter(m => m.role === 'user').length === 1) {
      conv.title = content.slice(0, 40) + (content.length > 40 ? '…' : '')
    }

    // If no server ID yet, create conversation on server first
    if (!conv.serverId) {
      try {
        const res = await createConversationApi(conv.title)
        if (res.success && res.data) {
          conv.serverId = res.data.id
          conv.id = String(res.data.id)
          activeConversationId.value = conv.id
        }
      } catch {
        userMsg.status = 'error'
        return
      }
    }

    isAiTyping.value = true
    quotaError.value = null
    try {
      const res = await sendMessageApi(conv.serverId!, content, imageStyle, image, currentMode, product, currentAspectRatio, currentVideoOptions) as any
      if (res.success && res.data) {
        if (res.data.conversation) {
          const updatedConv = upsertConversationFromApi(conversations.value, res.data.conversation)
          if (activeConversationId.value === conv.id || activeConversationId.value === String(conv.serverId)) {
            activeConversationId.value = updatedConv.id
          }
          if (hasProcessingMessages(updatedConv)) {
            startProcessingPoller(updatedConv.serverId!)
          }
        } else {
          const idx = conv.messages.findIndex(m => m.id === tempUserMsgId)
          if (idx !== -1) {
            conv.messages[idx] = apiMsgToLocal(res.data.user_message)
          }

          conv.messages.push(apiMsgToLocal(res.data.ai_message))
          if (conv.serverId && hasProcessingMessages(conv)) {
            startProcessingPoller(conv.serverId)
          }
        }

        // Update quota from response
        if (res.quota) {
          const auth = useAuthStore()
          auth.quota.credits_remaining = res.quota.remaining
          // Always update warning level (including 'none') to stay in sync
          auth.quota.warning_level = res.quota.warning
        }
      }
    } catch (err: unknown) {
      // Handle 402 — quota exhausted
      if (isAxiosError(err) && err.response?.status === 402) {
        const body = err.response.data
        quotaError.value = {
          code: body.error_code ?? 'insufficient_credits',
          message: body.message ?? 'Credits exhausted',
        }
        // Remove the optimistic message
        const idx = conv.messages.findIndex(m => m.id === tempUserMsgId)
        if (idx !== -1) conv.messages.splice(idx, 1)

        // Sync quota with server to ensure consistency
        const auth = useAuthStore()
        await auth.refreshQuota()
      } else {
        const recoveredConv = conv.serverId ? await refreshConversation(conv.serverId) : null
        if (!recoveredConv) {
          userMsg.status = 'error'
        }
      }
    } finally {
      isAiTyping.value = false
    }
  }

  function clearQuotaError() {
    quotaError.value = null
  }

  function hasProcessingMessages(conv: Conversation): boolean {
    return conv.messages.some(message => message.status === 'processing')
  }

  function stopProcessingPoller(serverId: number) {
    const poller = processingPollers.get(serverId)
    if (!poller) return

    window.clearInterval(poller)
    processingPollers.delete(serverId)
  }

  function startProcessingPoller(serverId: number) {
    if (processingPollers.has(serverId)) return

    let attempts = 0
    const maxAttempts = 120

    const tick = async () => {
      attempts += 1
      const conv = await refreshConversation(serverId)

      if (!conv || !hasProcessingMessages(conv) || attempts >= maxAttempts) {
        stopProcessingPoller(serverId)
      }
    }

    const poller = window.setInterval(() => { void tick() }, 5000)
    processingPollers.set(serverId, poller)
  }

  async function regenerateMessage(messageId: string) {
    if (activeConversationBusy.value) return

    const conv = conversations.value.find(c => c.id === activeConversationId.value)
    if (!conv || !conv.serverId) return

    // Find the AI message to regenerate
    const aiMsgIdx = conv.messages.findIndex(m => m.id === messageId && m.role === 'assistant')
    if (aiMsgIdx === -1) return

    isAiTyping.value = true
    quotaError.value = null
    try {
      const res = await regenerateMessageApi(conv.serverId, Number(messageId)) as any
      if (res.success && res.data) {
        if (res.data.conversation) {
          const updatedConv = upsertConversationFromApi(conversations.value, res.data.conversation)
          if (activeConversationId.value === String(conv.serverId)) {
            activeConversationId.value = updatedConv.id
          }
          if (hasProcessingMessages(updatedConv)) {
            startProcessingPoller(updatedConv.serverId!)
          }
        } else if (res.data.ai_message) {
          const regeneratedMessage = apiMsgToLocal(res.data.ai_message)
          if (regeneratedMessage.status === 'sent') {
            conv.messages.splice(aiMsgIdx, 1, regeneratedMessage)
          } else {
            conv.messages.splice(aiMsgIdx + 1, 0, regeneratedMessage)
          }
          if (conv.serverId && regeneratedMessage.status === 'processing') {
            startProcessingPoller(conv.serverId)
          }
        } else {
          await refreshConversation(conv.serverId)
        }

        // Update quota
        if (res.quota) {
          const auth = useAuthStore()
          auth.quota.credits_remaining = res.quota.remaining
          auth.quota.warning_level = res.quota.warning
        }
      }
    } catch (err: unknown) {
      if (isAxiosError(err) && err.response?.status === 402) {
        const body = err.response.data
        quotaError.value = {
          code: body.error_code ?? 'insufficient_credits',
          message: body.message ?? 'Credits exhausted',
        }
        const auth = useAuthStore()
        await auth.refreshQuota()
      } else {
        await refreshConversation(conv.serverId)
      }
    } finally {
      isAiTyping.value = false
    }
  }

  async function sendProductMessage(content: string, images: File[]) {
    if (activeConversationBusy.value) return

    if (!activeConversationId.value) {
      await createConversation()
    }

    const conv = conversations.value.find(c => c.id === activeConversationId.value)
    if (!conv) return

    // Optimistic user message with product image previews
    const tempUserMsgId = 'tmp-' + Date.now()
    const optimisticPreviews = images.map(f => URL.createObjectURL(f))
    const userMsg: ChatMessage = {
      id: tempUserMsgId,
      role: 'user',
      content,
      productImages: optimisticPreviews,
      timestamp: new Date(),
      status: 'sending',
    }
    conv.messages.push(userMsg)
    conv.updatedAt = new Date()

    if (conv.messages.filter(m => m.role === 'user').length === 1) {
      conv.title = content.slice(0, 40) + (content.length > 40 ? '…' : '')
    }

    // Ensure server conversation
    if (!conv.serverId) {
      try {
        const res = await createConversationApi(conv.title)
        if (res.success && res.data) {
          conv.serverId = res.data.id
          conv.id = String(res.data.id)
          activeConversationId.value = conv.id
        }
      } catch {
        userMsg.status = 'error'
        return
      }
    }

    isAiTyping.value = true
    quotaError.value = null
    try {
      const res = await sendProductMessageApi(conv.serverId!, content, images) as any
      if (res.success && res.data) {
        if (res.data.conversation) {
          const updatedConv = upsertConversationFromApi(conversations.value, res.data.conversation)
          if (activeConversationId.value === conv.id || activeConversationId.value === String(conv.serverId)) {
            activeConversationId.value = updatedConv.id
          }
        } else {
          const idx = conv.messages.findIndex(m => m.id === tempUserMsgId)
          if (idx !== -1) {
            conv.messages[idx] = apiMsgToLocal(res.data.user_message)
          }

          conv.messages.push(apiMsgToLocal(res.data.ai_message))
        }

        if (res.quota) {
          const auth = useAuthStore()
          auth.quota.credits_remaining = res.quota.remaining
          auth.quota.warning_level = res.quota.warning
        }
      }
    } catch (err: unknown) {
      if (isAxiosError(err) && err.response?.status === 402) {
        const body = err.response.data
        quotaError.value = {
          code: body.error_code ?? 'insufficient_credits',
          message: body.message ?? 'Credits exhausted',
        }
        const idx = conv.messages.findIndex(m => m.id === tempUserMsgId)
        if (idx !== -1) conv.messages.splice(idx, 1)
        const auth = useAuthStore()
        await auth.refreshQuota()
      } else {
        const recoveredConv = conv.serverId ? await refreshConversation(conv.serverId) : null
        if (!recoveredConv) {
          userMsg.status = 'error'
        }
      }
    } finally {
      isAiTyping.value = false
      // Always reset to text mode after product send so follow-ups use multimodal text
      aiMode.value = 'text'
    }
  }

  function $reset() {
    processingPollers.forEach(poller => window.clearInterval(poller))
    processingPollers.clear()
    conversations.value = []
    activeConversationId.value = null
    loaded.value = false
    quotaError.value = null
  }

  return {
    conversations,
    activeConversationId,
    activeConversation,
    activeConversationBusy,
    pinnedConversations,
    unpinnedConversations,
    filteredConversations,
    isAiTyping,
    editingConversationId,
    searchQuery,
    loaded,
    quotaError,
    aiMode,
    aspectRatio,
    videoAspectRatio,
    videoDurationSeconds,
    videoResolution,
    videoGenerateAudio,
    loadConversations,
    setActiveConversation,
    createConversation,
    deleteConversation,
    renameConversation,
    togglePin,
    startEditing,
    cancelEditing,
    sendMessage,
    regenerateMessage,
    sendProductMessage,
    clearQuotaError,
    $reset,
  }
})
