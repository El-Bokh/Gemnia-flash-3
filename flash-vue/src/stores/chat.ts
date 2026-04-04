import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import {
  getConversations as fetchConversationsApi,
  createConversation as createConversationApi,
  updateConversation as updateConversationApi,
  deleteConversation as deleteConversationApi,
  sendMessage as sendMessageApi,
} from '@/services/chatService'
import type { ConversationData, MessageData } from '@/services/chatService'

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

function apiMsgToLocal(m: MessageData): ChatMessage {
  return {
    id: String(m.id),
    role: m.role,
    content: m.content,
    imageUrl: m.image_url ?? undefined,
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

  async function sendMessage(content: string, imageStyle?: string) {
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
      imageStyle,
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
    try {
      const res = await sendMessageApi(conv.serverId!, content, imageStyle)
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
      }
    } catch {
      userMsg.status = 'error'
    } finally {
      isAiTyping.value = false
    }
  }

  function $reset() {
    conversations.value = []
    activeConversationId.value = null
    loaded.value = false
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
    loadConversations,
    setActiveConversation,
    createConversation,
    deleteConversation,
    renameConversation,
    togglePin,
    startEditing,
    cancelEditing,
    sendMessage,
    $reset,
  }
})
