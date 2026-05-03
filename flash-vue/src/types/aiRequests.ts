// ═══════════════════════════════════════════════════════════
//  AI Requests Types
//  Backend: app/Http/Resources/Admin/AiRequestResource.php
//           app/Http/Resources/Admin/AiRequestDetailResource.php
//           app/Services/Admin/AiRequestManagementService.php
// ═══════════════════════════════════════════════════════════

// ─── Shared Refs ────────────────────────────────────────────

export interface AiRequestUserRef {
  id: number
  name: string
  email: string
  avatar: string | null
}

export interface AiRequestUserDetailRef extends AiRequestUserRef {
  status: string
}

export interface AiRequestVisualStyleRef {
  id: number
  name: string
  slug: string
  thumbnail: string | null
}

export interface AiRequestVisualStyleDetailRef extends AiRequestVisualStyleRef {
  category: string | null
  prompt_prefix: string | null
  prompt_suffix: string | null
}

// ─── AI Request (list item — AiRequestResource) ────────────

export interface AiRequest {
  id: number
  uuid: string
  type: string
  status: string
  user_prompt: string
  model_used: string | null
  engine_provider: string | null
  width: number | null
  height: number | null
  num_images: number
  credits_consumed: number
  retry_count: number
  processing_time_ms: number | null
  output_video_path?: string | null
  error_message?: string | null
  error_code?: string | null
  started_at: string | null
  completed_at: string | null
  created_at: string | null
  updated_at: string | null
  deleted_at: string | null
  user?: AiRequestUserRef | null
  visual_style?: AiRequestVisualStyleRef | null
  generated_images_count?: number
}

// ─── AI Request Detail (show — AiRequestDetailResource) ─────

export interface AiRequestGeneratedImage {
  id: number
  uuid: string
  file_path: string
  file_name: string
  disk: string
  mime_type: string
  file_size: number
  width: number
  height: number
  thumbnail_path: string | null
  is_public: boolean
  is_nsfw: boolean
  download_count: number
  view_count: number
  created_at: string | null
}

export interface AiRequestUsageLogFeatureRef {
  id: number
  name: string
  slug: string
}

export interface AiRequestUsageLog {
  id: number
  action: string
  credits_used: number
  feature: AiRequestUsageLogFeatureRef | null
  created_at: string | null
}

export interface AiRequestSubscriptionRef {
  id: number
  status: string
  plan: {
    id: number
    name: string
    slug: string
  } | null
}

export interface AiRequestDetailStats {
  generated_images_count: number
  generated_videos_count: number
  usage_logs_count: number
  total_credits_logged: number
}

export interface AiRequestDetail {
  id: number
  uuid: string
  type: string
  status: string

  // Prompts
  user_prompt: string
  processed_prompt: string | null
  negative_prompt: string | null
  hidden_prompt: string | null

  // AI Engine / Model
  model_used: string | null
  engine_provider: string | null

  // Generation Parameters
  width: number | null
  height: number | null
  steps: number | null
  cfg_scale: number | null
  sampler: string | null
  seed: number | null
  num_images: number
  denoising_strength: number | null

  // Input / Output Media
  input_image_path: string | null
  output_image_path: string | null
  output_video_path: string | null
  mask_image_path: string | null

  // Credits & Performance
  credits_consumed: number
  processing_time_ms: number | null

  // Error Info
  error_message: string | null
  error_code: string | null
  retry_count: number

  // Client Info
  ip_address: string | null
  user_agent: string | null

  // Payloads
  request_payload: Record<string, unknown> | null
  response_payload: Record<string, unknown> | null
  metadata: Record<string, unknown> | null

  // Timestamps
  started_at: string | null
  completed_at: string | null
  created_at: string | null
  updated_at: string | null
  deleted_at: string | null

  // Relations
  user?: AiRequestUserDetailRef | null
  subscription?: AiRequestSubscriptionRef | null
  visual_style?: AiRequestVisualStyleDetailRef | null
  generated_images?: AiRequestGeneratedImage[]
  usage_logs?: AiRequestUsageLog[]

  // Stats
  stats: AiRequestDetailStats
}

// ─── Notify Response ────────────────────────────────────────

export interface AiRequestNotifyResponse {
  id: number
  uuid: string
  type: string
  title: string
  body: string
}

// ─── Bulk Responses ─────────────────────────────────────────

export interface BulkRetrySkippedItem {
  id: number
  status: string
  reason: string
}

export interface BulkRetryResponse {
  retried: number[]
  skipped: BulkRetrySkippedItem[]
  total: number
  retried_count: number
  skipped_count: number
}

export interface BulkDeleteSkippedItem {
  id: number
  status: string
  reason: string
}

export interface BulkDeleteResponse {
  deleted: number[]
  skipped: BulkDeleteSkippedItem[]
  total: number
  deleted_count: number
  skipped_count: number
}

// ─── Aggregations ───────────────────────────────────────────

export interface AiRequestAggregationsOverview {
  total_requests: number
  total_credits_consumed: number
  total_images_requested: number
  avg_processing_time_ms: number
  max_processing_time_ms: number
  min_processing_time_ms: number
  avg_credits_per_request: number
  avg_retry_count: number
  requests_with_retries: number
  success_rate: number
  failure_rate: number
}

export interface AiRequestDailyTrend {
  date: string
  total: number
  completed: number
  failed: number
  credits: number
}

export interface AiRequestTopUser {
  user: AiRequestUserRef | null
  request_count: number
  total_credits: number
  completed_count: number
}

export interface AiRequestTopStyleRef {
  id: number
  name: string
  slug: string
  thumbnail: string | null
}

export interface AiRequestTopStyle {
  style: AiRequestTopStyleRef | null
  usage_count: number
}

export interface AiRequestErrorCode {
  error_code: string
  count: number
}

export interface AiRequestAggregations {
  overview: AiRequestAggregationsOverview
  by_status: Record<string, number>
  by_type: Record<string, number>
  by_engine: Record<string, number>
  by_model: Record<string, number>
  daily_trend: AiRequestDailyTrend[]
  top_users: AiRequestTopUser[]
  top_visual_styles: AiRequestTopStyle[]
  error_codes: AiRequestErrorCode[]
}

// ─── Request Payloads ───────────────────────────────────────

export type AiRequestStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled' | 'timeout'
export type AiRequestType = 'text_to_image' | 'image_to_image' | 'inpainting' | 'upscale' | 'chat' | 'styled_chat' | 'multimodal' | 'regenerate' | 'product' | 'text_to_video' | 'image_to_video' | 'other'

export interface ListAiRequestsParams {
  search?: string
  status?: AiRequestStatus
  type?: AiRequestType
  user_id?: number
  visual_style_id?: number
  model_used?: string
  engine_provider?: string
  date_from?: string
  date_to?: string
  has_images?: boolean
  min_credits?: number
  max_credits?: number
  with_trashed?: boolean
  sort_by?: 'id' | 'created_at' | 'updated_at' | 'status' | 'type' | 'credits_consumed' | 'processing_time_ms' | 'retry_count' | 'user_prompt'
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface UpdateAiRequestPayload {
  status?: AiRequestStatus
  processed_prompt?: string | null
  negative_prompt?: string | null
  model_used?: string | null
  engine_provider?: string | null
  error_message?: string | null
  error_code?: string | null
  metadata?: Record<string, unknown> | null
}

export interface BulkAiRequestsPayload {
  request_ids: number[]
}

export interface NotifyUserPayload {
  type: 'completed' | 'failed'
}

export interface AiRequestAggregationsParams {
  date_from?: string
  date_to?: string
}
