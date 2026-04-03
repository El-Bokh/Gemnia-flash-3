// ─── Setting Types ──────────────────────────────────────

export type SettingType = 'string' | 'text' | 'boolean' | 'integer' | 'float' | 'json'

export interface Setting {
  id: number
  group: string
  key: string
  value: string | number | boolean | Record<string, unknown> | unknown[] | null
  raw_value?: string | null
  type: SettingType
  display_name: string | null
  description: string | null
  is_public: boolean
  is_encrypted: boolean
  options: Record<string, unknown>[] | null
  sort_order: number
  updated_at: string | null
}

export interface SettingGroup {
  group: string
  count: number
  settings: Setting[]
}

// ─── Request Params ─────────────────────────────────────

export interface ListSettingsParams {
  group?: string
  search?: string
  type?: SettingType
  is_public?: boolean
}

export interface CreateSettingData {
  key: string
  value: unknown
  group?: string
  type?: SettingType
  display_name?: string
  description?: string | null
  is_public?: boolean
  is_encrypted?: boolean
  options?: Record<string, unknown>[] | null
  sort_order?: number
}

export interface UpdateSettingData {
  value: unknown
}

export interface BulkUpdateSettingItem {
  key: string
  value: unknown
}

export interface BulkUpdateResult {
  updated: Setting[]
  errors: BulkUpdateError[]
}

export interface BulkUpdateError {
  key: string
  message: string
}

// ─── Maintenance ────────────────────────────────────────

export interface MaintenanceStatus {
  is_enabled: boolean
  message: string
  allowed_ips: string[]
}

// ─── Test Integration ───────────────────────────────────

export type IntegrationType = 'openai' | 'stability_ai' | 'stripe' | 'paypal' | 'mailgun' | 'smtp' | 'google_analytics'

export interface TestIntegrationData {
  integration: IntegrationType
}

export interface TestIntegrationResult {
  success: boolean
  message: string
}

// ─── Audit Log ──────────────────────────────────────────

export interface SettingAuditLogUser {
  id: number
  name: string
  email: string
  avatar: string | null
}

export interface SettingAuditLogEntry {
  id: number
  user_id: number
  action: string
  metadata: Record<string, unknown> | null
  created_at: string
  updated_at: string
  user: SettingAuditLogUser | null
}

export interface AuditLogParams {
  action?: string
  per_page?: number
  page?: number
}
