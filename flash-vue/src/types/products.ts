// ─── Product Types ──────────────────────────────────────

export interface Product {
  id: number
  name: string
  slug: string
  description: string | null
  hidden_prompt: string | null
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

export interface ListProductsParams {
  search?: string
  category?: string
  is_active?: boolean
  is_premium?: boolean
  with_trashed?: boolean
  sort_field?: string
  sort_order?: 'asc' | 'desc'
}

export interface StoreProductPayload {
  name: string
  slug?: string
  description?: string | null
  hidden_prompt?: string | null
  negative_prompt?: string | null
  category?: string | null
  is_active?: boolean
  is_premium?: boolean
  sort_order?: number
  settings?: Record<string, any> | null
  metadata?: Record<string, any> | null
}

export interface UpdateProductPayload extends Partial<StoreProductPayload> {}

export interface ReorderProductPayload {
  items: { id: number; sort_order: number }[]
}
