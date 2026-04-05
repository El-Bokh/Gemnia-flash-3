import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse } from '@/api/client'

// ─── Types ──────────────────────────────────────────────────

export interface MessageData {
  id: number
  conversation_id: number
  role: 'user' | 'assistant'
  content: string
  image_url: string | null
  image_style: string | null
  status: string
  created_at: string
  updated_at: string
}

export interface ConversationData {
  id: number
  user_id: number
  title: string
  pinned: boolean
  messages: MessageData[]
  created_at: string
  updated_at: string
}

export interface SendMessageResponse {
  user_message: MessageData
  ai_message: MessageData
  conversation: ConversationData
}

// ─── Service ────────────────────────────────────────────────

export function getConversations() {
  return apiGet<ApiResponse<ConversationData[]>>('/conversations')
}

export function createConversation(title?: string) {
  return apiPost<ApiResponse<ConversationData>>('/conversations', title ? { title } : {})
}

export function getConversation(id: number) {
  return apiGet<ApiResponse<ConversationData>>(`/conversations/${id}`)
}

export function updateConversation(id: number, data: { title?: string; pinned?: boolean }) {
  return apiPut<ApiResponse<ConversationData>>(`/conversations/${id}`, data)
}

export function deleteConversation(id: number) {
  return apiDelete<ApiResponse<null>>(`/conversations/${id}`)
}

export function sendMessage(conversationId: number, content: string, imageStyle?: string, image?: File, mode?: 'text' | 'image') {
  if (image) {
    const form = new FormData()
    form.append('content', content)
    if (imageStyle) form.append('image_style', imageStyle)
    if (mode) form.append('mode', mode)
    form.append('image', image)
    return apiPost<ApiResponse<SendMessageResponse>>(
      `/conversations/${conversationId}/messages`,
      form,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )
  }
  return apiPost<ApiResponse<SendMessageResponse>>(
    `/conversations/${conversationId}/messages`,
    { content, image_style: imageStyle, mode: mode ?? 'text' },
  )
}

// ─── Styles ─────────────────────────────────────────────────

export interface StyleData {
  id: number
  name: string
  slug: string
  description: string | null
  thumbnail: string | null
  category: string | null
  is_premium: boolean
  sort_order: number
}

export function getStyles() {
  return apiGet<ApiResponse<StyleData[]>>('/styles')
}
