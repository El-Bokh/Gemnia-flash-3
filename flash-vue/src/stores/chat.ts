import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

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
  title: string
  pinned: boolean
  messages: ChatMessage[]
  createdAt: Date
  updatedAt: Date
}

export const useChatStore = defineStore('chat', () => {
  const conversations = ref<Conversation[]>([
    {
      id: 'c1',
      title: 'تصميم شعار احترافي',
      pinned: true,
      messages: [
        { id: 'm1', role: 'user', content: 'أريد شعار بألوان زرقاء وذهبية لشركة تقنية', timestamp: new Date('2026-04-04T10:00:00'), status: 'sent' },
        { id: 'm2', role: 'assistant', content: 'سأقوم بتصميم شعار عصري يجمع بين الأزرق والذهبي مع عناصر تقنية متميزة.', imageUrl: 'https://placehold.co/512x512/6366f1/ffffff?text=Logo+Design', timestamp: new Date('2026-04-04T10:00:15'), status: 'sent' },
      ],
      createdAt: new Date('2026-04-04'),
      updatedAt: new Date('2026-04-04'),
    },
    {
      id: 'c2',
      title: 'صورة منتج للتسويق',
      pinned: false,
      messages: [
        { id: 'm3', role: 'user', content: 'صورة عالية الدقة لمنتج إلكتروني', timestamp: new Date('2026-04-03T14:00:00'), status: 'sent' },
        { id: 'm4', role: 'assistant', content: 'تم إنشاء صورة منتج احترافية بجودة عالية.', imageUrl: 'https://placehold.co/512x512/8b5cf6/ffffff?text=Product+Shot', timestamp: new Date('2026-04-03T14:00:20'), status: 'sent' },
      ],
      createdAt: new Date('2026-04-03'),
      updatedAt: new Date('2026-04-03'),
    },
    {
      id: 'c3',
      title: 'تعديل خلفية الصورة',
      pinned: false,
      messages: [
        { id: 'm5', role: 'user', content: 'إزالة الخلفية وتبديلها', timestamp: new Date('2026-04-02T09:00:00'), status: 'sent' },
      ],
      createdAt: new Date('2026-04-02'),
      updatedAt: new Date('2026-04-02'),
    },
    {
      id: 'c4',
      title: 'رسم توضيحي تعليمي',
      pinned: true,
      messages: [],
      createdAt: new Date('2026-04-01'),
      updatedAt: new Date('2026-04-01'),
    },
    {
      id: 'c5',
      title: 'تحويل صورة لكرتون',
      pinned: false,
      messages: [],
      createdAt: new Date('2026-03-31'),
      updatedAt: new Date('2026-03-31'),
    },
  ])

  const activeConversationId = ref<string | null>(null)
  const isAiTyping = ref(false)
  const editingConversationId = ref<string | null>(null)
  const searchQuery = ref('')

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

  function setActiveConversation(id: string | null) {
    activeConversationId.value = id
  }

  function createConversation(): string {
    const id = 'c' + Date.now()
    const conv: Conversation = {
      id,
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

  function deleteConversation(id: string) {
    const idx = conversations.value.findIndex(c => c.id === id)
    if (idx !== -1) {
      conversations.value.splice(idx, 1)
      if (activeConversationId.value === id) {
        activeConversationId.value = null
      }
    }
  }

  function renameConversation(id: string, newTitle: string) {
    const conv = conversations.value.find(c => c.id === id)
    if (conv) {
      conv.title = newTitle.trim() || conv.title
      conv.updatedAt = new Date()
    }
    editingConversationId.value = null
  }

  function togglePin(id: string) {
    const conv = conversations.value.find(c => c.id === id)
    if (conv) {
      conv.pinned = !conv.pinned
      conv.updatedAt = new Date()
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
      createConversation()
    }

    const conv = conversations.value.find(c => c.id === activeConversationId.value)
    if (!conv) return

    const userMsg: ChatMessage = {
      id: 'm' + Date.now(),
      role: 'user',
      content,
      imageStyle,
      timestamp: new Date(),
      status: 'sent',
    }
    conv.messages.push(userMsg)
    conv.updatedAt = new Date()

    // Update title from first message
    if (conv.messages.length === 1) {
      conv.title = content.slice(0, 40) + (content.length > 40 ? '…' : '')
    }

    // Simulate AI response
    isAiTyping.value = true
    await new Promise(r => setTimeout(r, 1500 + Math.random() * 2000))

    const aiMsg: ChatMessage = {
      id: 'm' + Date.now(),
      role: 'assistant',
      content: 'تم معالجة طلبك بنجاح! إليك النتيجة المطلوبة.',
      imageUrl: imageStyle
        ? `https://placehold.co/512x512/6366f1/ffffff?text=${encodeURIComponent(imageStyle)}`
        : undefined,
      timestamp: new Date(),
      status: 'sent',
    }
    conv.messages.push(aiMsg)
    conv.updatedAt = new Date()
    isAiTyping.value = false
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
    setActiveConversation,
    createConversation,
    deleteConversation,
    renameConversation,
    togglePin,
    startEditing,
    cancelEditing,
    sendMessage,
  }
})
