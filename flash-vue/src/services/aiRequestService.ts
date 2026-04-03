// ═══════════════════════════════════════════════════════════
//  AI Request Service
//  Backend: app/Http/Controllers/Api/Admin/AiRequestController.php
//  Routes:  /api/admin/ai-requests/*
//
//  كل function هنا = Endpoint في الـ Backend
// ═══════════════════════════════════════════════════════════

import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse, PaginatedResponse } from '@/api/client'
import type {
  AiRequest,
  AiRequestDetail,
  ListAiRequestsParams,
  UpdateAiRequestPayload,
  BulkAiRequestsPayload,
  BulkRetryResponse,
  BulkDeleteResponse,
  NotifyUserPayload,
  AiRequestNotifyResponse,
  AiRequestAggregations,
  AiRequestAggregationsParams,
} from '@/types/aiRequests'

const PREFIX = '/admin/ai-requests'

// ─── GET /api/admin/ai-requests ─────────────────────────────
// List AI requests with filters, sorting, pagination
export function getAiRequests(params?: ListAiRequestsParams) {
  return apiGet<PaginatedResponse<AiRequest>>(PREFIX, { params })
}

// ─── GET /api/admin/ai-requests/{id} ────────────────────────
// AI request detail: prompts, params, user, subscription, images, logs, stats
export function getAiRequest(id: number) {
  return apiGet<ApiResponse<AiRequestDetail>>(`${PREFIX}/${id}`)
}

// ─── PUT /api/admin/ai-requests/{id} ────────────────────────
// Update AI request status, prompts, model, error info
export function updateAiRequest(id: number, payload: UpdateAiRequestPayload) {
  return apiPut<ApiResponse<AiRequest>>(`${PREFIX}/${id}`, payload)
}

// ─── DELETE /api/admin/ai-requests/{id} ─────────────────────
// Soft-delete AI request
export function deleteAiRequest(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}`)
}

// ─── DELETE /api/admin/ai-requests/{id}/force ───────────────
// Permanently delete AI request + related images and logs
export function forceDeleteAiRequest(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}/force`)
}

// ─── POST /api/admin/ai-requests/{id}/restore ───────────────
// Restore soft-deleted AI request + related images
export function restoreAiRequest(id: number) {
  return apiPost<ApiResponse<AiRequest>>(`${PREFIX}/${id}/restore`)
}

// ─── POST /api/admin/ai-requests/{id}/retry ─────────────────
// Retry failed/timed-out/cancelled request
export function retryAiRequest(id: number) {
  return apiPost<ApiResponse<AiRequest>>(`${PREFIX}/${id}/retry`)
}

// ─── POST /api/admin/ai-requests/{id}/cancel ────────────────
// Cancel pending/processing request
export function cancelAiRequest(id: number) {
  return apiPost<ApiResponse<AiRequest>>(`${PREFIX}/${id}/cancel`)
}

// ─── POST /api/admin/ai-requests/bulk-retry ─────────────────
// Bulk retry multiple failed requests
export function bulkRetryAiRequests(payload: BulkAiRequestsPayload) {
  return apiPost<ApiResponse<BulkRetryResponse>>(`${PREFIX}/bulk-retry`, payload)
}

// ─── POST /api/admin/ai-requests/bulk-delete ────────────────
// Bulk soft-delete multiple requests
export function bulkDeleteAiRequests(payload: BulkAiRequestsPayload) {
  return apiPost<ApiResponse<BulkDeleteResponse>>(`${PREFIX}/bulk-delete`, payload)
}

// ─── POST /api/admin/ai-requests/{id}/notify ────────────────
// Send notification to user about completed/failed request
export function notifyAiRequestUser(id: number, payload: NotifyUserPayload) {
  return apiPost<ApiResponse<AiRequestNotifyResponse>>(`${PREFIX}/${id}/notify`, payload)
}

// ─── GET /api/admin/ai-requests/aggregations ────────────────
// Aggregated statistics: overview, by_status, by_type, trends, top_users, etc.
export function getAiRequestAggregations(params?: AiRequestAggregationsParams) {
  return apiGet<ApiResponse<AiRequestAggregations>>(`${PREFIX}/aggregations`, { params })
}
