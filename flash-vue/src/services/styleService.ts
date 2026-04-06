// ═══════════════════════════════════════════════════════════
//  Visual Style Service
//  Backend: app/Http/Controllers/Api/Admin/VisualStyleController.php
//  Routes:  /api/admin/styles/*
// ═══════════════════════════════════════════════════════════

import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse } from '@/api/client'
import type {
  VisualStyle,
  ListStylesParams,
  ReorderPayload,
} from '@/types/styles'

const PREFIX = '/admin/styles'

// ─── GET /api/admin/styles ──────────────────────────────
export function getStyles(params?: ListStylesParams) {
  return apiGet<ApiResponse<VisualStyle[]> & { categories: string[] }>(PREFIX, { params })
}

// ─── GET /api/admin/styles/{id} ─────────────────────────
export function getStyle(id: number) {
  return apiGet<ApiResponse<VisualStyle>>(`${PREFIX}/${id}`)
}

// ─── POST /api/admin/styles (multipart) ─────────────────
export function createStyle(payload: FormData) {
  return apiPost<ApiResponse<VisualStyle>>(PREFIX, payload, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

// ─── PUT /api/admin/styles/{id} (multipart via POST) ────
export function updateStyle(id: number, payload: FormData) {
  payload.append('_method', 'PUT')
  return apiPost<ApiResponse<VisualStyle>>(`${PREFIX}/${id}`, payload, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

// ─── DELETE /api/admin/styles/{id} ──────────────────────
export function deleteStyle(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}`)
}

// ─── DELETE /api/admin/styles/{id}/force ─────────────────
export function forceDeleteStyle(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}/force`)
}

// ─── POST /api/admin/styles/{id}/restore ────────────────
export function restoreStyle(id: number) {
  return apiPost<ApiResponse<VisualStyle>>(`${PREFIX}/${id}/restore`)
}

// ─── POST /api/admin/styles/{id}/duplicate ──────────────
export function duplicateStyle(id: number) {
  return apiPost<ApiResponse<VisualStyle>>(`${PREFIX}/${id}/duplicate`)
}

// ─── POST /api/admin/styles/{id}/toggle-active ──────────
export function toggleStyleActive(id: number) {
  return apiPost<ApiResponse<VisualStyle>>(`${PREFIX}/${id}/toggle-active`)
}

// ─── POST /api/admin/styles/{id}/upload-thumbnail ───────
export function uploadStyleThumbnail(id: number, file: File) {
  const fd = new FormData()
  fd.append('thumbnail', file)
  return apiPost<ApiResponse<VisualStyle>>(`${PREFIX}/${id}/upload-thumbnail`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

// ─── POST /api/admin/styles/reorder ─────────────────────
export function reorderStyles(payload: ReorderPayload) {
  return apiPost<ApiResponse<null>>(`${PREFIX}/reorder`, payload)
}
