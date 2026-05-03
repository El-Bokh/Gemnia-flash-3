import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse } from '@/api/client'

// ─── Types ──────────────────────────────────────────────────

export interface MessageData {
  id: number
  conversation_id: number
  role: 'user' | 'assistant'
  content: string
  image_url: string | null
  video_url: string | null
  image_style: string | null
  product_images: string[] | null
  metadata: Record<string, unknown> | null
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

const LONG_RUNNING_CHAT_TIMEOUT_MS = 240_000
export type AiMode = 'text' | 'image' | 'video'

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

export function sendMessage(
  conversationId: number,
  content: string,
  imageStyle?: string,
  image?: File,
  mode?: AiMode,
  product?: string,
  aspectRatio?: string,
  videoOptions?: { durationSeconds?: number; resolution?: string; generateAudio?: boolean },
) {
  const requestConfig = mode === 'image' || mode === 'video'
    ? { timeout: LONG_RUNNING_CHAT_TIMEOUT_MS }
    : undefined

  if (image) {
    const form = new FormData()
    form.append('content', content)
    if (imageStyle) form.append('image_style', imageStyle)
    if (product) form.append('product', product)
    if (mode) form.append('mode', mode)
    if (aspectRatio) form.append('aspect_ratio', aspectRatio)
    if (mode === 'video' && videoOptions?.durationSeconds) form.append('duration_seconds', String(videoOptions.durationSeconds))
    if (mode === 'video' && videoOptions?.resolution) form.append('resolution', videoOptions.resolution)
    if (mode === 'video' && videoOptions?.generateAudio !== undefined) form.append('generate_audio', videoOptions.generateAudio ? '1' : '0')
    form.append('image', image)
    return apiPost<ApiResponse<SendMessageResponse>>(
      `/conversations/${conversationId}/messages`,
      form,
      {
        ...requestConfig,
        headers: { 'Content-Type': 'multipart/form-data' },
      },
    )
  }
  return apiPost<ApiResponse<SendMessageResponse>>(
    `/conversations/${conversationId}/messages`,
    {
      content,
      image_style: imageStyle,
      product,
      mode: mode ?? 'text',
      aspect_ratio: aspectRatio,
      duration_seconds: mode === 'video' ? videoOptions?.durationSeconds : undefined,
      resolution: mode === 'video' ? videoOptions?.resolution : undefined,
      generate_audio: mode === 'video' ? videoOptions?.generateAudio : undefined,
    },
    requestConfig,
  )
}

export function regenerateMessage(conversationId: number, messageId: number) {
  return apiPost<ApiResponse<SendMessageResponse>>(
    `/conversations/${conversationId}/messages/${messageId}/regenerate`,
    {},
    { timeout: LONG_RUNNING_CHAT_TIMEOUT_MS },
  )
}

export function sendProductMessage(conversationId: number, content: string, images: File[]) {
  const form = new FormData()
  form.append('content', content)
  form.append('mode', 'product')
  images.forEach((img, i) => form.append(`images[${i}]`, img))
  return apiPost<ApiResponse<SendMessageResponse>>(
    `/conversations/${conversationId}/messages`,
    form,
    {
      timeout: LONG_RUNNING_CHAT_TIMEOUT_MS,
      headers: { 'Content-Type': 'multipart/form-data' },
    },
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

// ─── Products ───────────────────────────────────────────────

export interface ProductData {
  id: number
  name: string
  slug: string
  description: string | null
  thumbnail: string | null
  category: string | null
  is_premium: boolean
  sort_order: number
}

export function getProducts() {
  return apiGet<ApiResponse<ProductData[]>>('/products')
}
