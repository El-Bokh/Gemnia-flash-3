// ─── Visual Style Types ─────────────────────────────────

export interface VisualStyle {
  id: number
  name: string
  slug: string
  description: string | null
  prompt_prefix: string | null
  prompt_suffix: string | null
  negative_prompt: string | null
  thumbnail: string | null
  category: string | null
  is_active: boolean
  is_premium: boolean
  sort_order: number
  settings: Record<string, any> | null
  metadata: Record<string, any> | null
  ai_requests_count?: number
  created_at: string
  updated_at: string
  deleted_at: string | null
}

export interface ListStylesParams {
  search?: string
  category?: string
  is_active?: boolean
  is_premium?: boolean
  with_trashed?: boolean
  sort_field?: string
  sort_order?: 'asc' | 'desc'
}

export interface StoreStylePayload {
  name: string
  slug?: string
  description?: string | null
  prompt_prefix?: string | null
  prompt_suffix?: string | null
  negative_prompt?: string | null
  category?: string | null
  is_active?: boolean
  is_premium?: boolean
  sort_order?: number
  settings?: Record<string, any> | null
  metadata?: Record<string, any> | null
}

export interface UpdateStylePayload extends Partial<StoreStylePayload> {}

export interface ReorderPayload {
  items: { id: number; sort_order: number }[]
}
