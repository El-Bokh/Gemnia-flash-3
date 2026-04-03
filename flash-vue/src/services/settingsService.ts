import { apiGet, apiPost, apiPut, apiDelete } from '@/api/client'
import type { ApiResponse, PaginatedResponse } from '@/api/client'
import type {
  Setting,
  SettingGroup,
  ListSettingsParams,
  CreateSettingData,
  UpdateSettingData,
  BulkUpdateSettingItem,
  BulkUpdateResult,
  MaintenanceStatus,
  TestIntegrationData,
  TestIntegrationResult,
  SettingAuditLogEntry,
  AuditLogParams,
} from '@/types/settings'

const BASE = '/admin/settings'

export function getSettings(params?: ListSettingsParams) {
  return apiGet<ApiResponse<SettingGroup[]>>(BASE, { params })
}

export function getSetting(id: number) {
  return apiGet<ApiResponse<Setting>>(`${BASE}/${id}`)
}

export function createSetting(data: CreateSettingData) {
  return apiPost<ApiResponse<Setting>>(BASE, data)
}

export function updateSetting(id: number, data: UpdateSettingData) {
  return apiPut<ApiResponse<Setting>>(`${BASE}/${id}`, data)
}

export function deleteSetting(id: number) {
  return apiDelete<ApiResponse<null>>(`${BASE}/${id}`)
}

export function toggleSetting(id: number) {
  return apiPost<ApiResponse<Setting>>(`${BASE}/${id}/toggle`)
}

export function bulkUpdateSettings(settings: BulkUpdateSettingItem[]) {
  return apiPut<ApiResponse<BulkUpdateResult>>(`${BASE}/bulk`, { settings })
}

export function resetSettingsGroup(group: string) {
  return apiPost<ApiResponse<Setting[]>>(`${BASE}/reset/${group}`)
}

export function getMaintenanceStatus() {
  return apiGet<ApiResponse<MaintenanceStatus>>(`${BASE}/maintenance`)
}

export function toggleMaintenance() {
  return apiPost<ApiResponse<MaintenanceStatus>>(`${BASE}/maintenance/toggle`)
}

export function testIntegration(data: TestIntegrationData) {
  return apiPost<ApiResponse<TestIntegrationResult>>(`${BASE}/test-integration`, data)
}

export function getSettingsAuditLog(params?: AuditLogParams) {
  return apiGet<PaginatedResponse<SettingAuditLogEntry>>(`${BASE}/audit-log`, { params })
}

export function getPublicSettings() {
  return apiGet<ApiResponse<Record<string, unknown>>>('/settings/public')
}
