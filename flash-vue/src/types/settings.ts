// ─── Maintenance ────────────────────────────────────────

export interface MaintenanceStatus {
  is_enabled: boolean
  message: string
  allowed_ips: string[]
}

export interface UpdateMaintenanceData {
  message?: string
  allowed_ips?: string[]
}

// ─── AI Integrations ────────────────────────────────────

export type AiIntegrationType = 'openai' | 'stability_ai'

export interface AiIntegrationSetting {
  id: number
  key: string
  value: string | number | boolean | null
  type: string
  display_name: string | null
  description: string | null
  is_encrypted: boolean
  group: string
}

export interface UpdateAiIntegrationItem {
  key: string
  value: unknown
}

export interface UpdateAiIntegrationResult {
  updated: { key: string; value: unknown }[]
  errors: { key: string; message: string }[]
}

export interface TestAiIntegrationData {
  integration: AiIntegrationType
}

export interface TestAiIntegrationResult {
  success: boolean
  message: string
}

// ─── Audit Log ──────────────────────────────────────────

export interface AuditLogUser {
  id: number
  name: string
  email: string
  avatar: string | null
}

export interface AuditLogEntry {
  id: number
  user_id: number
  action: string
  metadata: Record<string, unknown> | null
  created_at: string
  updated_at: string
  user: AuditLogUser | null
}

export interface AuditLogParams {
  action?: string
  per_page?: number
  page?: number
}
