import { apiGet, apiPost, apiPut } from '@/api/client'
import type { ApiResponse, PaginatedResponse } from '@/api/client'
import type {
  MaintenanceStatus,
  UpdateMaintenanceData,
  AiIntegrationSetting,
  UpdateAiIntegrationItem,
  UpdateAiIntegrationResult,
  TestAiIntegrationData,
  TestAiIntegrationResult,
  AuditLogEntry,
  AuditLogParams,
} from '@/types/settings'

const BASE = '/admin/settings'

// ── Audit Log ───────────────────────────────────────────
export function getAuditLog(params?: AuditLogParams) {
  return apiGet<PaginatedResponse<AuditLogEntry>>(`${BASE}/audit-log`, { params })
}

// ── Maintenance Mode ────────────────────────────────────
export function getMaintenanceStatus() {
  return apiGet<ApiResponse<MaintenanceStatus>>(`${BASE}/maintenance`)
}

export function toggleMaintenance() {
  return apiPost<ApiResponse<MaintenanceStatus>>(`${BASE}/maintenance/toggle`)
}

export function updateMaintenance(data: UpdateMaintenanceData) {
  return apiPut<ApiResponse<MaintenanceStatus>>(`${BASE}/maintenance`, data)
}

// ── AI Integrations ─────────────────────────────────────
export function getAiIntegrations() {
  return apiGet<ApiResponse<AiIntegrationSetting[]>>(`${BASE}/ai-integrations`)
}

export function updateAiIntegrations(settings: UpdateAiIntegrationItem[]) {
  return apiPut<ApiResponse<UpdateAiIntegrationResult>>(`${BASE}/ai-integrations`, { settings })
}

export function testAiIntegration(data: TestAiIntegrationData) {
  return apiPost<ApiResponse<TestAiIntegrationResult>>(`${BASE}/ai-integrations/test`, data)
}
