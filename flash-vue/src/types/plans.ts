// ═══════════════════════════════════════════════════════════
//  Plans & Features Types
//  Backend: app/Http/Resources/Admin/PlanResource.php
//           app/Http/Resources/Admin/PlanDetailResource.php
//           app/Http/Resources/Admin/FeatureResource.php
//           app/Services/Admin/PlanManagementService.php
//           app/Services/Admin/FeatureManagementService.php
// ═══════════════════════════════════════════════════════════

// ─── Feature Pivot (detail context — nested under pivot key) ──

export interface FeaturePivotWithId {
  id: number
  is_enabled: boolean
  usage_limit: number | null
  limit_period: string | null
  credits_per_use: number
  constraints: string | null
}

// ─── Plan Feature Items (nested in plan list — flat, no pivot object) ──

export interface PlanFeatureItem {
  id: number
  name: string
  slug: string
  type: string
  is_active: boolean
  is_enabled: boolean
  usage_limit: number | null
  limit_period: string | null
  credits_per_use: number
  constraints: string | null
}

// ─── Plan Detail Feature Items (nested in plan detail — with pivot) ──

export interface PlanDetailFeatureItem {
  id: number
  name: string
  slug: string
  description: string | null
  type: string
  is_active: boolean
  sort_order: number
  pivot: FeaturePivotWithId
}

// ─── Plan (list item — PlanResource) ────────────────────────

export interface Plan {
  id: number
  name: string
  slug: string
  description: string | null
  price_monthly: number
  price_yearly: number
  currency: string
  credits_monthly: number
  credits_yearly: number
  is_free: boolean
  is_active: boolean
  is_featured: boolean
  sort_order: number
  trial_days: number
  metadata: Record<string, unknown> | null
  created_at: string | null
  updated_at: string | null
  deleted_at: string | null
  features?: PlanFeatureItem[]
  features_count?: number
  subscriptions_count?: number
  active_subscriptions_count?: number
}

// ─── Plan Detail (show — PlanDetailResource) ────────────────

export interface PlanFeaturesByTypeItem {
  id: number
  name: string
  slug: string
  is_enabled: boolean
  usage_limit: number | null
  limit_period: string | null
}

export interface PlanFeaturesByType {
  type: string
  features: PlanFeaturesByTypeItem[]
}

export interface PlanDetailStats {
  total_features: number
  enabled_features: number
  total_subscriptions: number
  active_subscriptions: number
}

export interface PlanRecentSubscriberUser {
  id: number
  name: string
  email: string
  avatar: string | null
}

export interface PlanRecentSubscriber {
  id: number
  user: PlanRecentSubscriberUser
  billing_cycle: string
  status: string
  price: number
  starts_at: string | null
  ends_at: string | null
  created_at: string | null
}

export interface PlanDetail {
  id: number
  name: string
  slug: string
  description: string | null
  price_monthly: number
  price_yearly: number
  currency: string
  credits_monthly: number
  credits_yearly: number
  is_free: boolean
  is_active: boolean
  is_featured: boolean
  sort_order: number
  trial_days: number
  metadata: Record<string, unknown> | null
  created_at: string | null
  updated_at: string | null
  deleted_at: string | null
  features: PlanDetailFeatureItem[]
  features_by_type: PlanFeaturesByType[]
  stats: PlanDetailStats
  recent_subscribers: PlanRecentSubscriber[]
}

// ─── Feature (list/show — FeatureResource) ──────────────────

export interface FeaturePlanItem {
  id: number
  name: string
  slug: string
  is_active: boolean
  is_enabled: boolean
  usage_limit: number | null
  limit_period: string | null
  credits_per_use: number
}

export interface Feature {
  id: number
  name: string
  slug: string
  description: string | null
  type: string
  is_active: boolean
  sort_order: number
  metadata: Record<string, unknown> | null
  created_at: string | null
  updated_at: string | null
  plans?: FeaturePlanItem[]
  plans_count?: number
}

// ─── Update Feature Limit Response ──────────────────────────

export interface UpdateFeatureLimitResponse {
  plan_id: number
  feature_id: number
  feature: {
    id: number
    name: string
    slug: string
  }
  is_enabled: boolean
  usage_limit: number | null
  limit_period: string | null
  credits_per_use: number
  constraints: string | null
}

// ─── Comparison Response ────────────────────────────────────

export interface ComparisonPlanInfo {
  id: number
  name: string
  slug: string
  price_monthly: number
  price_yearly: number
  credits_monthly: number
  is_free: boolean
  is_featured: boolean
  trial_days: number
  active_subscribers: number
}

export interface ComparisonFeatureItem {
  id: number
  name: string
  slug: string
  type: string
  included: boolean
  is_enabled: boolean
  usage_limit: number | null
  limit_period: string | null
  credits_per_use: number
}

export interface ComparisonPlan {
  plan: ComparisonPlanInfo
  features: ComparisonFeatureItem[]
}

export interface ComparisonFeatureRef {
  id: number
  name: string
  slug: string
  type: string
}

export interface ComparisonResponse {
  plans: ComparisonPlan[]
  features: ComparisonFeatureRef[]
}

// ─── Request Payloads — Plans ───────────────────────────────

export interface ListPlansParams {
  search?: string
  is_active?: boolean
  is_free?: boolean
  with_trashed?: boolean
  sort_by?: 'name' | 'slug' | 'price_monthly' | 'price_yearly' | 'sort_order' | 'created_at' | 'subscriptions_count'
  sort_dir?: 'asc' | 'desc'
}

export interface StorePlanFeaturePayload {
  feature_id: number
  is_enabled?: boolean
  usage_limit?: number | null
  limit_period?: 'day' | 'week' | 'month' | 'year' | 'lifetime'
  credits_per_use?: number
  constraints?: Record<string, unknown> | null
}

export interface StorePlanPayload {
  name: string
  slug: string
  description?: string | null
  price_monthly: number
  price_yearly?: number
  currency?: string
  credits_monthly: number
  credits_yearly?: number
  is_free?: boolean
  is_active?: boolean
  is_featured?: boolean
  sort_order?: number
  trial_days?: number
  metadata?: Record<string, unknown> | null
  features?: StorePlanFeaturePayload[]
}

export interface UpdatePlanPayload {
  name?: string
  slug?: string
  description?: string | null
  price_monthly?: number
  price_yearly?: number
  currency?: string
  credits_monthly?: number
  credits_yearly?: number
  is_free?: boolean
  is_active?: boolean
  is_featured?: boolean
  sort_order?: number
  trial_days?: number
  metadata?: Record<string, unknown> | null
  features?: StorePlanFeaturePayload[]
}

export interface SyncFeaturesPayload {
  features: StorePlanFeaturePayload[]
}

export interface UpdateFeatureLimitPayload {
  is_enabled?: boolean
  usage_limit?: number | null
  limit_period?: 'day' | 'week' | 'month' | 'year' | 'lifetime'
  credits_per_use?: number
  constraints?: Record<string, unknown> | null
}

// ─── Request Payloads — Features ────────────────────────────

export type FeatureType = 'text_to_image' | 'image_to_image' | 'inpainting' | 'upscale' | 'other'

export interface ListFeaturesParams {
  search?: string
  type?: FeatureType
  is_active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
}

export interface StoreFeaturePayload {
  name: string
  slug: string
  description?: string | null
  type: FeatureType
  is_active?: boolean
  sort_order?: number
  metadata?: Record<string, unknown> | null
}

export interface UpdateFeaturePayload {
  name?: string
  slug?: string
  description?: string | null
  type?: FeatureType
  is_active?: boolean
  sort_order?: number
  metadata?: Record<string, unknown> | null
}

export interface AssignToPlansItem {
  plan_id: number
  is_enabled?: boolean
  usage_limit?: number | null
  limit_period?: 'day' | 'week' | 'month' | 'year' | 'lifetime'
  credits_per_use?: number
}

export interface AssignToPlansPayload {
  plans: AssignToPlansItem[]
}
