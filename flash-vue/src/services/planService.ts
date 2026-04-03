// ═══════════════════════════════════════════════════════════
//  Plan Service
//  Backend: app/Http/Controllers/Api/Admin/PlanController.php
//  Routes:  /api/admin/plans/*
//
//  كل function هنا = Endpoint في الـ Backend
// ═══════════════════════════════════════════════════════════

import { apiGet, apiPost, apiPut, apiPatch, apiDelete } from '@/api/client'
import type { ApiResponse } from '@/api/client'
import type {
  Plan,
  PlanDetail,
  ListPlansParams,
  StorePlanPayload,
  UpdatePlanPayload,
  SyncFeaturesPayload,
  UpdateFeatureLimitPayload,
  UpdateFeatureLimitResponse,
  ComparisonResponse,
} from '@/types/plans'

const PREFIX = '/admin/plans'

// ─── GET /api/admin/plans ───────────────────────────────────
// List plans with optional filters, sorting
export function getPlans(params?: ListPlansParams) {
  return apiGet<ApiResponse<Plan[]>>(PREFIX, { params })
}

// ─── GET /api/admin/plans/{id} ──────────────────────────────
// Plan detail: features, features_by_type, stats, recent_subscribers
export function getPlan(id: number) {
  return apiGet<ApiResponse<PlanDetail>>(`${PREFIX}/${id}`)
}

// ─── POST /api/admin/plans ──────────────────────────────────
// Create plan with optional features
export function createPlan(payload: StorePlanPayload) {
  return apiPost<ApiResponse<Plan>>(`${PREFIX}`, payload)
}

// ─── PUT /api/admin/plans/{id} ──────────────────────────────
// Update plan fields + optional features
export function updatePlan(id: number, payload: UpdatePlanPayload) {
  return apiPut<ApiResponse<Plan>>(`${PREFIX}/${id}`, payload)
}

// ─── DELETE /api/admin/plans/{id} ───────────────────────────
// Soft-delete plan
export function deletePlan(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}`)
}

// ─── DELETE /api/admin/plans/{id}/force ─────────────────────
// Permanently delete plan
export function forceDeletePlan(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}/force`)
}

// ─── POST /api/admin/plans/{id}/restore ─────────────────────
// Restore soft-deleted plan
export function restorePlan(id: number) {
  return apiPost<ApiResponse<Plan>>(`${PREFIX}/${id}/restore`)
}

// ─── POST /api/admin/plans/{id}/duplicate ───────────────────
// Duplicate plan with all features and pivot data
export function duplicatePlan(id: number) {
  return apiPost<ApiResponse<Plan>>(`${PREFIX}/${id}/duplicate`)
}

// ─── PUT /api/admin/plans/{id}/features ─────────────────────
// Sync features for a plan (replaces all existing pivot entries)
export function syncFeatures(id: number, payload: SyncFeaturesPayload) {
  return apiPut<ApiResponse<PlanDetail>>(`${PREFIX}/${id}/features`, payload)
}

// ─── PATCH /api/admin/plans/{planId}/features/{featureId} ───
// Update single feature limit/pivot on a plan
export function updateFeatureLimit(planId: number, featureId: number, payload: UpdateFeatureLimitPayload) {
  return apiPatch<ApiResponse<UpdateFeatureLimitResponse>>(`${PREFIX}/${planId}/features/${featureId}`, payload)
}

// ─── POST /api/admin/plans/{id}/toggle-active ───────────────
// Toggle plan is_active status
export function togglePlanActive(id: number) {
  return apiPost<ApiResponse<Plan>>(`${PREFIX}/${id}/toggle-active`)
}

// ─── GET /api/admin/plans/comparison ────────────────────────
// Get all active plans with features for comparison view
export function getPlansComparison() {
  return apiGet<ApiResponse<ComparisonResponse>>(`${PREFIX}/comparison`)
}
