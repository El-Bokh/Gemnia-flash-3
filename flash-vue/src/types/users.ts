// ═══════════════════════════════════════════════════════════
//  Users Types — matches Laravel UserResource / UserDetailResource
//  Backend: app/Http/Resources/Admin/UserResource.php
//           app/Http/Resources/Admin/UserDetailResource.php
//           app/Http/Resources/Admin/UserCollection.php
//           app/Services/Admin/UserManagementService.php
// ═══════════════════════════════════════════════════════════

// ─── Shared Sub-Types ───────────────────────────────────────

export interface RoleRef {
  id: number
  name: string
  slug: string
}

export interface PlanRef {
  id: number
  name: string
  slug: string
}

// ─── Active Subscription (UserResource) ─────────────────────

export interface UserActiveSubscription {
  id: number
  plan: PlanRef | null
  billing_cycle: string
  status: string
  price: number
  currency: string
  credits_remaining: number
  credits_total: number
  starts_at: string | null
  ends_at: string | null
  trial_ends_at: string | null
  auto_renew: boolean
}

// ─── Subscription (UserDetailResource — all subscriptions) ──

export interface UserSubscription {
  id: number
  plan: PlanRef | null
  billing_cycle: string
  status: string
  price: number
  currency: string
  credits_remaining: number
  credits_total: number
  starts_at: string | null
  ends_at: string | null
  trial_starts_at: string | null
  trial_ends_at: string | null
  cancelled_at: string | null
  auto_renew: boolean
  created_at: string | null
}

// ─── User Stats (list — UserResource) ───────────────────────

export interface UserListStats {
  ai_requests_count: number
  generated_images_count: number
  total_credits_used: number
}

// ─── User Stats (detail — UserDetailResource) ───────────────

export interface UserDetailStats {
  ai_requests_total: number
  ai_requests_completed: number
  ai_requests_failed: number
  ai_requests_pending: number
  generated_images: number
  total_payments: number
  payments_count: number
  credit_balance: number
}

// ─── Recent AI Request (detail) ─────────────────────────────

export interface UserRecentAiRequest {
  id: number
  uuid: string
  type: string
  status: string
  prompt: string
  style: string | null
  model: string
  credits: number
  created_at: string | null
}

// ─── Recent Generated Image (detail) ────────────────────────

export interface UserRecentGeneratedImage {
  id: number
  uuid: string
  file_path: string
  file_name: string
  width: number
  height: number
  file_size: number
  is_public: boolean
  is_nsfw: boolean
  created_at: string | null
}

// ─── Recent Payment (detail) ────────────────────────────────

export interface UserRecentPayment {
  id: number
  uuid: string
  amount: number
  net_amount: number
  currency: string
  status: string
  method: string
  paid_at: string | null
  created_at: string | null
}

// ─── Credit Ledger Entry (detail) ───────────────────────────

export interface CreditLedgerEntry {
  id: number
  type: string
  amount: number
  balance_after: number
  source: string
  description: string | null
  created_at: string | null
}

export interface UserCreditLedger {
  balance: number
  recent: CreditLedgerEntry[]
}

// ─── User (list item — UserResource) ────────────────────────

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  avatar: string | null
  status: string
  locale: string | null
  timezone: string | null
  email_verified_at: string | null
  last_login_at: string | null
  last_login_ip: string | null
  created_at: string | null
  updated_at: string | null
  roles?: RoleRef[]
  active_subscription?: UserActiveSubscription | null
  stats?: UserListStats
}

// ─── User Detail (show — UserDetailResource) ────────────────

export interface UserDetail {
  id: number
  name: string
  email: string
  phone: string | null
  avatar: string | null
  status: string
  locale: string | null
  timezone: string | null
  email_verified_at: string | null
  last_login_at: string | null
  last_login_ip: string | null
  created_at: string | null
  updated_at: string | null
  roles: RoleRef[]
  subscriptions: UserSubscription[]
  stats: UserDetailStats
  recent_ai_requests: UserRecentAiRequest[]
  recent_generated_images: UserRecentGeneratedImage[]
  recent_output_images: UserRecentGeneratedImage[]
  recent_payments: UserRecentPayment[]
  credit_ledger: UserCreditLedger
}

// ─── Paginated AI Request (getUserAiRequests endpoint) ──────

export interface PaginatedAiRequest {
  id: number
  uuid: string
  user_id: number
  visual_style_id: number | null
  type: string
  status: string
  user_prompt: string
  model_used: string
  credits_consumed: number
  processing_time_ms: number | null
  created_at: string | null
  visual_style?: { id: number; name: string } | null
}

// ─── Paginated Generated Image (getUserGeneratedImages) ─────

export interface PaginatedGeneratedImage {
  id: number
  uuid: string
  ai_request_id: number
  file_path: string
  file_name: string
  width: number
  height: number
  file_size: number
  is_public: boolean
  is_favorite: boolean
  is_nsfw: boolean
  created_at: string | null
}

// ─── Aggregations ───────────────────────────────────────────

export interface UsersPerRole {
  role: string
  slug: string
  count: number
}

export interface UsersPerPlan {
  id: number
  name: string
  slug: string
  users_count: number
}

export interface UserAggregations {
  total_users: number
  users_per_role: UsersPerRole[]
  users_per_plan: UsersPerPlan[]
  users_per_status: Record<string, number>
  registration_trend: Record<string, number>
}

// ─── Request Payloads (matches Form Requests) ───────────────

export interface ListUsersParams {
  page?: number
  search?: string
  status?: 'active' | 'suspended' | 'banned' | 'pending'
  role?: string
  plan?: string
  subscription_status?: 'active' | 'cancelled' | 'expired' | 'past_due' | 'trialing' | 'paused' | 'pending'
  sort_by?: 'name' | 'email' | 'created_at' | 'last_login_at' | 'status'
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  with_trashed?: boolean
}

export interface StoreUserPayload {
  name: string
  email: string
  password: string
  phone?: string | null
  avatar?: string | null
  status?: 'active' | 'suspended' | 'banned' | 'pending'
  locale?: string | null
  timezone?: string | null
  roles?: number[]
  plan_id?: number | null
  billing_cycle?: 'monthly' | 'yearly'
}

export interface UpdateUserPayload {
  name?: string
  email?: string
  password?: string
  phone?: string | null
  avatar?: string | null
  status?: 'active' | 'suspended' | 'banned' | 'pending'
  locale?: string | null
  timezone?: string | null
  roles?: number[]
}

export interface ResetPasswordPayload {
  password: string
  password_confirmation: string
  revoke_tokens?: boolean
}

export interface AssignRolesPayload {
  roles: number[]
}
