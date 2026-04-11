import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import {
  getConversations as fetchConversationsApi,
  createConversation as createConversationApi,
  updateConversation as updateConversationApi,
  deleteConversation as deleteConversationApi,
  sendMessage as sendMessageApi,
  regenerateMessage as regenerateMessageApi,
  sendProductMessage as sendProductMessageApi,
} from '@/services/chatService'
import type { ConversationData, MessageData } from '@/services/chatService'
import { useAuthStore } from '@/stores/auth'

export interface ChatMessage {
  id: string
  role: 'user' | 'assistant'
  content: string
  imageUrl?: string
  imageStyle?: string
  timestamp: Date
  status: 'sending' | 'sent' | 'error'
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
    imageStyle: m.image_style ?? undefined,
    timestamp: new Date(m.created_at),
    status: m.status === 'sent' ? 'sent' : 'sent',
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

export const useChatStore = defineStore('chat', () => {
  const conversations = ref<Conversation[]>([])
  const activeConversationId = ref<string | null>(null)
  const isAiTyping = ref(false)
  const editingConversationId = ref<string | null>(null)
  const searchQuery = ref('')
  const loaded = ref(false)
  const quotaError = ref<{ code: string; message: string } | null>(null)

  const aiMode = ref<'text' | 'image'>('text')

  const activeConversation = computed(() =>
    conversations.value.find(c => c.id === activeConversationId.value) ?? null,
  )

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
        loaded.value = true
      }
    } catch {
      // Silently fail — user may not be authenticated yet
    }
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
    if (!activeConversationId.value) {
      await createConversation()
    }

    const conv = conversations.value.find(c => c.id === activeConversationId.value)
    if (!conv) return

    const currentMode = aiMode.value

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
      const res = await sendMessageApi(conv.serverId!, content, imageStyle, image, currentMode, product) as any
      if (res.success && res.data) {
        // Replace temp user message with real one
        const idx = conv.messages.findIndex(m => m.id === tempUserMsgId)
        if (idx !== -1) {
          conv.messages[idx] = apiMsgToLocal(res.data.user_message)
        }

        // Add AI message
        conv.messages.push(apiMsgToLocal(res.data.ai_message))

        // Sync title from server
        if (res.data.conversation) {
          conv.title = res.data.conversation.title
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
        userMsg.status = 'error'
      }
    } finally {
      isAiTyping.value = false
    }
  }

  function clearQuotaError() {
    quotaError.value = null
  }

  async function regenerateMessage(messageId: string) {
    const conv = conversations.value.find(c => c.id === activeConversationId.value)
    if (!conv || !conv.serverId) return

    // Find the AI message to regenerate
    const aiMsgIdx = conv.messages.findIndex(m => m.id === messageId && m.role === 'assistant')
    if (aiMsgIdx === -1) return

    // Remove old AI response
    conv.messages.splice(aiMsgIdx, 1)

    isAiTyping.value = true
    quotaError.value = null
    try {
      const res = await regenerateMessageApi(conv.serverId, Number(messageId)) as any
      if (res.success && res.data) {
        // Add new AI message
        conv.messages.push(apiMsgToLocal(res.data.ai_message))

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
      }
    } finally {
      isAiTyping.value = false
    }
  }

  async function sendProductMessage(content: string, images: File[]) {
    if (!activeConversationId.value) {
      await createConversation()
    }

    const conv = conversations.value.find(c => c.id === activeConversationId.value)
    if (!conv) return

    // Optimistic user message
    const tempUserMsgId = 'tmp-' + Date.now()
    const userMsg: ChatMessage = {
      id: tempUserMsgId,
      role: 'user',
      content,
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
        const idx = conv.messages.findIndex(m => m.id === tempUserMsgId)
        if (idx !== -1) {
          conv.messages[idx] = apiMsgToLocal(res.data.user_message)
        }
        conv.messages.push(apiMsgToLocal(res.data.ai_message))
        if (res.data.conversation) {
          conv.title = res.data.conversation.title
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
        userMsg.status = 'error'
      }
    } finally {
      isAiTyping.value = false
    }
  }

  function $reset() {
    conversations.value = []
    activeConversationId.value = null
    loaded.value = false
    quotaError.value = null
  }

  return {
    conversations,
    activeConversationId,
    activeConversation,
    pinnedConversations,
    unpinnedConversations,
    filteredConversations,
    isAiTyping,
    editingConversationId,
    searchQuery,
    loaded,
    quotaError,
    aiMode,
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
