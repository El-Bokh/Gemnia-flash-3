// ═══════════════════════════════════════════════════════════
//  Feature Service
//  Backend: app/Http/Controllers/Api/Admin/FeatureController.php
//  Routes:  /api/admin/features/*
//
//  كل function هنا = Endpoint في الـ Backend
// ═══════════════════════════════════════════════════════════

import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse } from '@/api/client'
import type {
  Feature,
  ListFeaturesParams,
  StoreFeaturePayload,
  UpdateFeaturePayload,
  AssignToPlansPayload,
} from '@/types/plans'

const PREFIX = '/admin/features'

// ─── GET /api/admin/features ────────────────────────────────
// List features with optional filters, sorting
export function getFeatures(params?: ListFeaturesParams) {
  return apiGet<ApiResponse<Feature[]>>(PREFIX, { params })
}

// ─── GET /api/admin/features/{id} ───────────────────────────
// Feature detail with plans and pivot data
export function getFeature(id: number) {
  return apiGet<ApiResponse<Feature>>(`${PREFIX}/${id}`)
}

// ─── POST /api/admin/features ───────────────────────────────
// Create a new feature
export function createFeature(payload: StoreFeaturePayload) {
  return apiPost<ApiResponse<Feature>>(PREFIX, payload)
}

// ─── PUT /api/admin/features/{id} ───────────────────────────
// Update feature fields
export function updateFeature(id: number, payload: UpdateFeaturePayload) {
  return apiPut<ApiResponse<Feature>>(`${PREFIX}/${id}`, payload)
}

// ─── DELETE /api/admin/features/{id} ────────────────────────
// Delete feature
export function deleteFeature(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}`)
}

// ─── POST /api/admin/features/{id}/toggle-active ────────────
// Toggle feature is_active status
export function toggleFeatureActive(id: number) {
  return apiPost<ApiResponse<Feature>>(`${PREFIX}/${id}/toggle-active`)
}

// ─── PUT /api/admin/features/{id}/plans ─────────────────────
// Assign feature to plans with pivot data
export function assignFeatureToPlans(id: number, payload: AssignToPlansPayload) {
  return apiPut<ApiResponse<Feature>>(`${PREFIX}/${id}/plans`, payload)
}
