// ═══════════════════════════════════════════════════════════
//  Product Service
//  Backend: app/Http/Controllers/Api/Admin/ProductController.php
//  Routes:  /api/admin/products/*
// ═══════════════════════════════════════════════════════════

import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse } from '@/api/client'
import type {
  Product,
  ListProductsParams,
  ReorderProductPayload,
} from '@/types/products'

const PREFIX = '/admin/products'

// ─── GET /api/admin/products ────────────────────────────
export function getProducts(params?: ListProductsParams) {
  return apiGet<ApiResponse<Product[]> & { categories: string[] }>(PREFIX, { params })
}

// ─── GET /api/admin/products/{id} ───────────────────────
export function getProduct(id: number) {
  return apiGet<ApiResponse<Product>>(`${PREFIX}/${id}`)
}

// ─── POST /api/admin/products (multipart) ───────────────
export function createProduct(payload: FormData) {
  return apiPost<ApiResponse<Product>>(PREFIX, payload, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

// ─── PUT /api/admin/products/{id} (multipart via POST) ──
export function updateProduct(id: number, payload: FormData) {
  payload.append('_method', 'PUT')
  return apiPost<ApiResponse<Product>>(`${PREFIX}/${id}`, payload, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

// ─── DELETE /api/admin/products/{id} ────────────────────
export function deleteProduct(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}`)
}

// ─── DELETE /api/admin/products/{id}/force ───────────────
export function forceDeleteProduct(id: number) {
  return apiDelete<ApiResponse<null>>(`${PREFIX}/${id}/force`)
}

// ─── POST /api/admin/products/{id}/restore ──────────────
export function restoreProduct(id: number) {
  return apiPost<ApiResponse<Product>>(`${PREFIX}/${id}/restore`)
}

// ─── POST /api/admin/products/{id}/duplicate ────────────
export function duplicateProduct(id: number) {
  return apiPost<ApiResponse<Product>>(`${PREFIX}/${id}/duplicate`)
}

// ─── POST /api/admin/products/{id}/toggle-active ────────
export function toggleProductActive(id: number) {
  return apiPost<ApiResponse<Product>>(`${PREFIX}/${id}/toggle-active`)
}

// ─── POST /api/admin/products/{id}/upload-thumbnail ─────
export function uploadProductThumbnail(id: number, file: File) {
  const fd = new FormData()
  fd.append('thumbnail', file)
  return apiPost<ApiResponse<Product>>(`${PREFIX}/${id}/upload-thumbnail`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

// ─── POST /api/admin/products/reorder ───────────────────
export function reorderProducts(payload: ReorderProductPayload) {
  return apiPost<ApiResponse<null>>(`${PREFIX}/reorder`, payload)
}
