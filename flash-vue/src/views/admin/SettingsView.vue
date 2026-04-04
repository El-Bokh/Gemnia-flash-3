<script setup lang="ts">
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import {
  getSettings,
  updateSetting,
  bulkUpdateSettings,
  resetSettingsGroup,
  toggleMaintenance,
  getMaintenanceStatus,
  testIntegration,
  getSettingsAuditLog,
  createSetting,
  deleteSetting,
  toggleSetting,
} from '@/services/settingsService'
import type {
  Setting,
  SettingGroup,
  ListSettingsParams,
  MaintenanceStatus,
  TestIntegrationResult,
  SettingAuditLogEntry,
  IntegrationType,
  BulkUpdateSettingItem,
} from '@/types/settings'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import Checkbox from 'primevue/checkbox'
import Skeleton from 'primevue/skeleton'
import ProgressBar from 'primevue/progressbar'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'

// ── State ───────────────────────────────────────────────────
const loading = ref(true)
const saving = ref(false)
const groups = ref<SettingGroup[]>([])
const activeGroup = ref<string>('')
const search = ref('')
const activeTab = ref('settings')

// Mobile group drawer
const showGroupDrawer = ref(false)

// Maintenance
const maintenance = ref<MaintenanceStatus | null>(null)
const maintenanceLoading = ref(false)

// Integration testing
const testingIntegration = ref<IntegrationType | null>(null)
const integrationResult = ref<TestIntegrationResult | null>(null)

// Audit log
const auditLog = ref<SettingAuditLogEntry[]>([])
const auditLoading = ref(false)
const auditPage = ref(1)
const auditTotal = ref(0)

// Inline editing
const editingId = ref<number | null>(null)
const editValue = ref<string>('')

// Create setting dialog
const showCreateDialog = ref(false)
const newSetting = ref({
  key: '',
  value: '' as string,
  group: '',
  type: 'string' as const,
  display_name: '',
  description: '',
  is_public: false,
  is_encrypted: false,
})

// Delete confirm
const showDeleteConfirm = ref(false)
const deleteTarget = ref<Setting | null>(null)
const actionLoading = ref(false)

// Reset confirm
const showResetConfirm = ref(false)
const resetTargetGroup = ref('')

// Dirty tracking for bulk save
const dirtySettings = ref<Map<string, unknown>>(new Map())

// ── Computed ────────────────────────────────────────────────
const activeGroupData = computed(() => {
  return groups.value.find(g => g.group === activeGroup.value)
})

const filteredSettings = computed(() => {
  if (!activeGroupData.value) return []
  if (!search.value) return activeGroupData.value.settings
  const q = search.value.toLowerCase()
  return activeGroupData.value.settings.filter(s =>
    s.key.toLowerCase().includes(q) ||
    (s.display_name?.toLowerCase() ?? '').includes(q) ||
    (s.description?.toLowerCase() ?? '').includes(q)
  )
})

const totalSettingsCount = computed(() =>
  groups.value.reduce((sum, g) => sum + g.count, 0)
)

const hasDirtySettings = computed(() => dirtySettings.value.size > 0)

const groupIcons: Record<string, string> = {
  general: 'pi pi-cog',
  app: 'pi pi-th-large',
  mail: 'pi pi-envelope',
  payment: 'pi pi-credit-card',
  ai: 'pi pi-sparkles',
  storage: 'pi pi-database',
  security: 'pi pi-shield',
  notification: 'pi pi-bell',
  appearance: 'pi pi-palette',
  seo: 'pi pi-search',
  social: 'pi pi-share-alt',
  api: 'pi pi-code',
  cache: 'pi pi-server',
  queue: 'pi pi-list',
  logging: 'pi pi-file',
}

const groupColors: Record<string, string> = {
  general: '#6366f1',
  app: '#3b82f6',
  mail: '#0ea5e9',
  payment: '#10b981',
  ai: '#8b5cf6',
  storage: '#f59e0b',
  security: '#ef4444',
  notification: '#f97316',
  appearance: '#ec4899',
  seo: '#06b6d4',
  social: '#14b8a6',
  api: '#6366f1',
  cache: '#84cc16',
  queue: '#a855f7',
  logging: '#64748b',
}

const integrationOptions = [
  { label: 'OpenAI', value: 'openai', icon: 'pi pi-sparkles' },
  { label: 'Stability AI', value: 'stability_ai', icon: 'pi pi-image' },
  { label: 'Stripe', value: 'stripe', icon: 'pi pi-credit-card' },
  { label: 'PayPal', value: 'paypal', icon: 'pi pi-wallet' },
  { label: 'Mailgun', value: 'mailgun', icon: 'pi pi-envelope' },
  { label: 'SMTP', value: 'smtp', icon: 'pi pi-send' },
  { label: 'Google Analytics', value: 'google_analytics', icon: 'pi pi-chart-bar' },
] as const

const settingTypeOptions = [
  { label: 'String', value: 'string' },
  { label: 'Text', value: 'text' },
  { label: 'Boolean', value: 'boolean' },
  { label: 'Integer', value: 'integer' },
  { label: 'Float', value: 'float' },
  { label: 'JSON', value: 'json' },
]

// ── Fetch ───────────────────────────────────────────────────
async function fetchSettings() {
  loading.value = true
  try {
    const params: ListSettingsParams = {}
    const res = await getSettings(params)
    groups.value = res.data
    if (groups.value.length && !activeGroup.value) {
      activeGroup.value = groups.value[0]!.group
    }
  } catch {
    loadMockSettings()
  } finally {
    loading.value = false
  }
}

async function fetchMaintenance() {
  try {
    const res = await getMaintenanceStatus()
    maintenance.value = res.data
  } catch {
    maintenance.value = { is_enabled: false, message: 'System is under maintenance', allowed_ips: ['127.0.0.1'] }
  }
}

async function fetchAuditLog() {
  auditLoading.value = true
  try {
    const res = await getSettingsAuditLog({ page: auditPage.value, per_page: 15 })
    auditLog.value = res.data
    auditTotal.value = res.meta.total
  } catch {
    loadMockAuditLog()
  } finally {
    auditLoading.value = false
  }
}

function loadMockSettings() {
  groups.value = [
    {
      group: 'general',
      count: 5,
      settings: [
        { id: 1, group: 'general', key: 'app_name', value: 'Flash AI', raw_value: 'Flash AI', type: 'string', display_name: 'Application Name', description: 'The name of the application displayed in the header and emails.', is_public: true, is_encrypted: false, options: null, sort_order: 1, updated_at: '2026-04-01T10:00:00Z' },
        { id: 2, group: 'general', key: 'app_url', value: 'https://flash.io', raw_value: 'https://flash.io', type: 'string', display_name: 'Application URL', description: 'The base URL of the application.', is_public: true, is_encrypted: false, options: null, sort_order: 2, updated_at: '2026-03-15T08:00:00Z' },
        { id: 3, group: 'general', key: 'app_description', value: 'AI-powered image generation platform', raw_value: 'AI-powered image generation platform', type: 'text', display_name: 'App Description', description: 'Short description for SEO and meta tags.', is_public: true, is_encrypted: false, options: null, sort_order: 3, updated_at: '2026-03-10T12:00:00Z' },
        { id: 4, group: 'general', key: 'registration_enabled', value: true, raw_value: '1', type: 'boolean', display_name: 'Enable Registration', description: 'Allow new users to register on the platform.', is_public: true, is_encrypted: false, options: null, sort_order: 4, updated_at: '2026-04-02T14:00:00Z' },
        { id: 5, group: 'general', key: 'default_locale', value: 'en', raw_value: 'en', type: 'string', display_name: 'Default Locale', description: 'Default language for the application.', is_public: true, is_encrypted: false, options: [{ value: 'en', label: 'English' }, { value: 'ar', label: 'Arabic' }], sort_order: 5, updated_at: '2026-03-20T09:00:00Z' },
      ],
    },
    {
      group: 'mail',
      count: 4,
      settings: [
        { id: 10, group: 'mail', key: 'mail_driver', value: 'smtp', raw_value: 'smtp', type: 'string', display_name: 'Mail Driver', description: 'The mail transport driver to use.', is_public: false, is_encrypted: false, options: [{ value: 'smtp', label: 'SMTP' }, { value: 'mailgun', label: 'Mailgun' }, { value: 'ses', label: 'Amazon SES' }], sort_order: 1, updated_at: '2026-03-01T10:00:00Z' },
        { id: 11, group: 'mail', key: 'mail_from_address', value: 'noreply@flash.io', raw_value: 'noreply@flash.io', type: 'string', display_name: 'From Address', description: 'Default email sender address.', is_public: false, is_encrypted: false, options: null, sort_order: 2, updated_at: '2026-03-01T10:00:00Z' },
        { id: 12, group: 'mail', key: 'mail_from_name', value: 'Flash AI', raw_value: 'Flash AI', type: 'string', display_name: 'From Name', description: 'Default sender name on outgoing emails.', is_public: false, is_encrypted: false, options: null, sort_order: 3, updated_at: '2026-03-01T10:00:00Z' },
        { id: 13, group: 'mail', key: 'smtp_password', value: '••••••••', raw_value: null, type: 'string', display_name: 'SMTP Password', description: 'Password for SMTP authentication.', is_public: false, is_encrypted: true, options: null, sort_order: 4, updated_at: '2026-03-01T10:00:00Z' },
      ],
    },
    {
      group: 'ai',
      count: 5,
      settings: [
        { id: 20, group: 'ai', key: 'openai_api_key', value: '••••••••', raw_value: null, type: 'string', display_name: 'OpenAI API Key', description: 'API key for OpenAI services (DALL-E).', is_public: false, is_encrypted: true, options: null, sort_order: 1, updated_at: '2026-04-01T10:00:00Z' },
        { id: 21, group: 'ai', key: 'stability_api_key', value: '••••••••', raw_value: null, type: 'string', display_name: 'Stability AI Key', description: 'API key for Stability AI image generation.', is_public: false, is_encrypted: true, options: null, sort_order: 2, updated_at: '2026-04-01T10:00:00Z' },
        { id: 22, group: 'ai', key: 'default_model', value: 'dall-e-3', raw_value: 'dall-e-3', type: 'string', display_name: 'Default AI Model', description: 'Default model used for image generation.', is_public: false, is_encrypted: false, options: [{ value: 'dall-e-3', label: 'DALL-E 3' }, { value: 'stable-diffusion-xl', label: 'Stable Diffusion XL' }], sort_order: 3, updated_at: '2026-03-28T10:00:00Z' },
        { id: 23, group: 'ai', key: 'max_concurrent_requests', value: 10, raw_value: '10', type: 'integer', display_name: 'Max Concurrent Requests', description: 'Maximum AI generation requests processed simultaneously.', is_public: false, is_encrypted: false, options: null, sort_order: 4, updated_at: '2026-03-25T10:00:00Z' },
        { id: 24, group: 'ai', key: 'nsfw_filter_enabled', value: true, raw_value: '1', type: 'boolean', display_name: 'NSFW Filter', description: 'Enable content safety filter on generated images.', is_public: false, is_encrypted: false, options: null, sort_order: 5, updated_at: '2026-04-02T10:00:00Z' },
      ],
    },
    {
      group: 'payment',
      count: 4,
      settings: [
        { id: 30, group: 'payment', key: 'stripe_key', value: '••••••••', raw_value: null, type: 'string', display_name: 'Stripe Public Key', description: 'Stripe publishable key.', is_public: false, is_encrypted: true, options: null, sort_order: 1, updated_at: '2026-03-20T10:00:00Z' },
        { id: 31, group: 'payment', key: 'stripe_secret', value: '••••••••', raw_value: null, type: 'string', display_name: 'Stripe Secret Key', description: 'Stripe secret key for server-side operations.', is_public: false, is_encrypted: true, options: null, sort_order: 2, updated_at: '2026-03-20T10:00:00Z' },
        { id: 32, group: 'payment', key: 'default_currency', value: 'USD', raw_value: 'USD', type: 'string', display_name: 'Default Currency', description: 'Default currency for payments.', is_public: true, is_encrypted: false, options: [{ value: 'USD', label: 'USD' }, { value: 'EUR', label: 'EUR' }, { value: 'GBP', label: 'GBP' }], sort_order: 3, updated_at: '2026-03-20T10:00:00Z' },
        { id: 33, group: 'payment', key: 'trial_days', value: 14, raw_value: '14', type: 'integer', display_name: 'Trial Days', description: 'Default free trial period in days.', is_public: true, is_encrypted: false, options: null, sort_order: 4, updated_at: '2026-03-20T10:00:00Z' },
      ],
    },
    {
      group: 'storage',
      count: 3,
      settings: [
        { id: 40, group: 'storage', key: 'storage_driver', value: 'local', raw_value: 'local', type: 'string', display_name: 'Storage Driver', description: 'File storage driver (local, s3, etc.).', is_public: false, is_encrypted: false, options: [{ value: 'local', label: 'Local' }, { value: 's3', label: 'Amazon S3' }, { value: 'gcs', label: 'Google Cloud' }], sort_order: 1, updated_at: '2026-03-15T10:00:00Z' },
        { id: 41, group: 'storage', key: 'max_upload_size', value: 10, raw_value: '10', type: 'integer', display_name: 'Max Upload Size (MB)', description: 'Maximum file upload size in megabytes.', is_public: true, is_encrypted: false, options: null, sort_order: 2, updated_at: '2026-03-15T10:00:00Z' },
        { id: 42, group: 'storage', key: 'image_quality', value: 90, raw_value: '90', type: 'integer', display_name: 'Image Quality (%)', description: 'Output compression quality for generated images.', is_public: false, is_encrypted: false, options: null, sort_order: 3, updated_at: '2026-03-15T10:00:00Z' },
      ],
    },
    {
      group: 'security',
      count: 3,
      settings: [
        { id: 50, group: 'security', key: 'rate_limit_per_minute', value: 60, raw_value: '60', type: 'integer', display_name: 'Rate Limit (req/min)', description: 'API rate limit per minute per user.', is_public: false, is_encrypted: false, options: null, sort_order: 1, updated_at: '2026-03-10T10:00:00Z' },
        { id: 51, group: 'security', key: 'force_https', value: true, raw_value: '1', type: 'boolean', display_name: 'Force HTTPS', description: 'Redirect all HTTP requests to HTTPS.', is_public: false, is_encrypted: false, options: null, sort_order: 2, updated_at: '2026-03-10T10:00:00Z' },
        { id: 52, group: 'security', key: 'session_lifetime', value: 120, raw_value: '120', type: 'integer', display_name: 'Session Lifetime (min)', description: 'User session duration in minutes.', is_public: false, is_encrypted: false, options: null, sort_order: 3, updated_at: '2026-03-10T10:00:00Z' },
      ],
    },
  ]
  if (!activeGroup.value && groups.value.length) {
    activeGroup.value = groups.value[0]!.group
  }
}

function loadMockAuditLog() {
  const actions = ['updated', 'created', 'deleted', 'reset_group', 'toggled_maintenance']
  const users = [
    { id: 1, name: 'Admin User', email: 'admin@flash.io', avatar: null },
    { id: 2, name: 'Sara Ahmed', email: 'sara@flash.io', avatar: null },
  ]
  auditLog.value = Array.from({ length: 12 }, (_, i) => ({
    id: i + 1,
    user_id: users[i % 2]!.id,
    action: actions[i % actions.length] ?? 'updated',
    metadata: { key: `setting_key_${i}`, group: groups.value[i % groups.value.length]?.group || 'general' } as Record<string, unknown>,
    created_at: new Date(Date.now() - i * 3600000 * 2).toISOString(),
    updated_at: new Date(Date.now() - i * 3600000 * 2).toISOString(),
    user: users[i % 2]!,
  }))
  auditTotal.value = 24
}

// ── Actions ────────────────────────────────────────────────
function selectGroup(group: string) {
  activeGroup.value = group
  showGroupDrawer.value = false
  dirtySettings.value.clear()
}

function startEdit(setting: Setting) {
  editingId.value = setting.id
  editValue.value = String(setting.value ?? '')
}

function cancelEdit() {
  editingId.value = null
  editValue.value = ''
}

async function saveInlineEdit(setting: Setting) {
  saving.value = true
  try {
    let parsedValue: unknown = editValue.value
    if (setting.type === 'integer') parsedValue = parseInt(editValue.value, 10)
    else if (setting.type === 'float') parsedValue = parseFloat(editValue.value)
    else if (setting.type === 'boolean') parsedValue = editValue.value === 'true' || editValue.value === '1'
    else if (setting.type === 'json') parsedValue = JSON.parse(editValue.value)

    await updateSetting(setting.id, { value: parsedValue })
    setting.value = parsedValue as Setting['value']
    editingId.value = null
    await fetchSettings()
  } catch {
    // keep editing open
  } finally {
    saving.value = false
  }
}

function markDirty(setting: Setting, newValue: unknown) {
  dirtySettings.value.set(setting.key, newValue)
}

async function bulkSave() {
  if (!hasDirtySettings.value) return
  saving.value = true
  try {
    const items: BulkUpdateSettingItem[] = []
    dirtySettings.value.forEach((value, key) => {
      items.push({ key, value })
    })
    await bulkUpdateSettings(items)
    dirtySettings.value.clear()
    await fetchSettings()
  } catch {
    // noop
  } finally {
    saving.value = false
  }
}

async function handleToggleSetting(setting: Setting) {
  try {
    await toggleSetting(setting.id)
    setting.value = !setting.value
  } catch {
    // noop
  }
}

function confirmReset(group: string) {
  resetTargetGroup.value = group
  showResetConfirm.value = true
}

async function handleReset() {
  actionLoading.value = true
  try {
    await resetSettingsGroup(resetTargetGroup.value)
    showResetConfirm.value = false
    await fetchSettings()
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleToggleMaintenance() {
  maintenanceLoading.value = true
  try {
    const res = await toggleMaintenance()
    maintenance.value = res.data
  } catch {
    if (maintenance.value) {
      maintenance.value.is_enabled = !maintenance.value.is_enabled
    }
  } finally {
    maintenanceLoading.value = false
  }
}

async function handleTestIntegration(integration: IntegrationType) {
  testingIntegration.value = integration
  integrationResult.value = null
  try {
    const res = await testIntegration({ integration })
    integrationResult.value = res.data
  } catch {
    integrationResult.value = { success: false, message: 'Connection test failed. Check credentials.' }
  } finally {
    testingIntegration.value = null
  }
}

function confirmDeleteSetting(setting: Setting) {
  deleteTarget.value = setting
  showDeleteConfirm.value = true
}

async function handleDelete() {
  if (!deleteTarget.value) return
  actionLoading.value = true
  try {
    await deleteSetting(deleteTarget.value.id)
    showDeleteConfirm.value = false
    await fetchSettings()
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

function openCreateDialog() {
  newSetting.value = { key: '', value: '' as string, group: activeGroup.value || 'general', type: 'string' as const, display_name: '', description: '', is_public: false, is_encrypted: false }
  showCreateDialog.value = true
}

async function handleCreate() {
  actionLoading.value = true
  try {
    await createSetting(newSetting.value)
    showCreateDialog.value = false
    await fetchSettings()
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

// ── Helpers ─────────────────────────────────────────────────
function groupIcon(group: string) {
  return groupIcons[group] || 'pi pi-cog'
}

function groupColor(group: string) {
  return groupColors[group] || '#6b7280'
}

function capitalize(str: string) {
  return str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ')
}

function typeSeverity(type: string): 'info' | 'success' | 'warn' | 'danger' | 'secondary' {
  return { string: 'info' as const, text: 'info' as const, boolean: 'success' as const, integer: 'warn' as const, float: 'warn' as const, json: 'danger' as const }[type] || 'secondary'
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function relativeTime(d: string) {
  const now = Date.now()
  const then = new Date(d).getTime()
  const diff = Math.floor((now - then) / 1000)
  if (diff < 60) return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

function actionSeverity(action: string): 'info' | 'success' | 'warn' | 'danger' | 'secondary' {
  return { updated: 'info' as const, created: 'success' as const, deleted: 'danger' as const, reset_group: 'warn' as const, toggled_maintenance: 'warn' as const }[action] || 'secondary'
}

function initials(name: string) {
  return name.split(' ').map(w => w[0]).join('').substring(0, 2)
}

function displayValue(setting: Setting): string {
  if (setting.is_encrypted) return '••••••••'
  if (setting.type === 'boolean') return setting.value ? 'Enabled' : 'Disabled'
  if (setting.type === 'json') return JSON.stringify(setting.value)
  return String(setting.value ?? '—')
}

// ── Mobile detection ────────────────────────────────────────
const isMobile = ref(window.innerWidth < 768)
function onResize() {
  isMobile.value = window.innerWidth < 768
}

onMounted(() => {
  window.addEventListener('resize', onResize)
  fetchSettings()
  fetchMaintenance()
})

onUnmounted(() => {
  window.removeEventListener('resize', onResize)
})

watch(activeTab, (tab) => {
  if (tab === 'audit' && auditLog.value.length === 0) {
    fetchAuditLog()
  }
})

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {}, 300)
})
</script>

<template>
  <div class="settings-page">
    <!-- Header -->
    <div class="page-toolbar">
      <div class="toolbar-left">
        <Button
          v-if="isMobile"
          icon="pi pi-bars"
          severity="secondary"
          text
          rounded
          size="small"
          @click="showGroupDrawer = true"
          class="drawer-trigger"
        />
        <h1 class="page-title">System Settings</h1>
      </div>
      <div class="toolbar-actions">
        <Button
          v-if="hasDirtySettings"
          icon="pi pi-save"
          label="Save All"
          size="small"
          :loading="saving"
          @click="bulkSave"
        />
        <Button
          icon="pi pi-plus"
          label="Add Setting"
          size="small"
          severity="secondary"
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Tabs -->
    <Tabs v-model:value="activeTab" class="settings-tabs">
      <TabList>
        <Tab value="settings">
          <i class="pi pi-cog" style="font-size: 0.72rem" />
          <span>Settings</span>
        </Tab>
        <Tab value="maintenance">
          <i class="pi pi-wrench" style="font-size: 0.72rem" />
          <span>Maintenance</span>
        </Tab>
        <Tab value="integrations">
          <i class="pi pi-link" style="font-size: 0.72rem" />
          <span>Integrations</span>
        </Tab>
        <Tab value="audit">
          <i class="pi pi-history" style="font-size: 0.72rem" />
          <span>Audit Log</span>
        </Tab>
      </TabList>

      <TabPanels>
        <!-- ════════ SETTINGS TAB ════════ -->
        <TabPanel value="settings">
          <!-- Summary cards -->
          <div class="summary-grid">
            <article class="summary-card">
              <span class="summary-icon" style="color: #6366f1; background: rgba(99,102,241,.12)">
                <i class="pi pi-cog" />
              </span>
              <div class="summary-copy">
                <span class="summary-label">Total</span>
                <strong class="summary-value">{{ totalSettingsCount }}</strong>
              </div>
            </article>
            <article class="summary-card">
              <span class="summary-icon" style="color: #3b82f6; background: rgba(59,130,246,.12)">
                <i class="pi pi-th-large" />
              </span>
              <div class="summary-copy">
                <span class="summary-label">Groups</span>
                <strong class="summary-value">{{ groups.length }}</strong>
              </div>
            </article>
            <article class="summary-card">
              <span class="summary-icon" :style="{ color: maintenance?.is_enabled ? '#ef4444' : '#10b981', background: maintenance?.is_enabled ? 'rgba(239,68,68,.12)' : 'rgba(16,185,129,.12)' }">
                <i :class="maintenance?.is_enabled ? 'pi pi-lock' : 'pi pi-lock-open'" />
              </span>
              <div class="summary-copy">
                <span class="summary-label">System</span>
                <strong class="summary-value">{{ maintenance?.is_enabled ? 'Maintenance' : 'Online' }}</strong>
              </div>
            </article>
            <article class="summary-card">
              <span class="summary-icon" style="color: #f59e0b; background: rgba(245,158,11,.12)">
                <i class="pi pi-shield" />
              </span>
              <div class="summary-copy">
                <span class="summary-label">Encrypted</span>
                <strong class="summary-value">{{ groups.reduce((s, g) => s + g.settings.filter(x => x.is_encrypted).length, 0) }}</strong>
              </div>
            </article>
          </div>

          <!-- Settings layout: sidebar + content -->
          <div class="settings-layout">
            <!-- Desktop sidebar -->
            <aside class="settings-sidebar d-desktop-flex">
              <div class="sidebar-header">
                <span class="sidebar-title">Groups</span>
              </div>
              <nav class="sidebar-nav">
                <button
                  v-for="g in groups"
                  :key="g.group"
                  class="sidebar-item"
                  :class="{ active: activeGroup === g.group }"
                  @click="selectGroup(g.group)"
                >
                  <span class="sidebar-icon" :style="{ color: groupColor(g.group), background: `${groupColor(g.group)}14` }">
                    <i :class="groupIcon(g.group)" />
                  </span>
                  <span class="sidebar-label">{{ capitalize(g.group) }}</span>
                  <span class="sidebar-count">{{ g.count }}</span>
                </button>
              </nav>
            </aside>

            <!-- Mobile group drawer -->
            <Dialog
              v-model:visible="showGroupDrawer"
              position="left"
              :modal="true"
              :style="{ width: '260px', height: '100vh', margin: 0, borderRadius: 0 }"
              :showHeader="true"
              header="Setting Groups"
              class="group-drawer"
            >
              <nav class="sidebar-nav">
                <button
                  v-for="g in groups"
                  :key="g.group"
                  class="sidebar-item"
                  :class="{ active: activeGroup === g.group }"
                  @click="selectGroup(g.group)"
                >
                  <span class="sidebar-icon" :style="{ color: groupColor(g.group), background: `${groupColor(g.group)}14` }">
                    <i :class="groupIcon(g.group)" />
                  </span>
                  <span class="sidebar-label">{{ capitalize(g.group) }}</span>
                  <span class="sidebar-count">{{ g.count }}</span>
                </button>
              </nav>
            </Dialog>

            <!-- Main settings content -->
            <div class="settings-main">
              <!-- Group header -->
              <div class="group-header" v-if="activeGroupData">
                <div class="group-title-row">
                  <span class="group-icon-lg" :style="{ color: groupColor(activeGroup), background: `${groupColor(activeGroup)}14` }">
                    <i :class="groupIcon(activeGroup)" />
                  </span>
                  <div>
                    <h2 class="group-name">{{ capitalize(activeGroup) }}</h2>
                    <span class="group-meta">{{ activeGroupData.count }} settings</span>
                  </div>
                </div>
                <div class="group-actions">
                  <Button icon="pi pi-refresh" severity="secondary" text rounded size="small" @click="confirmReset(activeGroup)" v-tooltip.left="'Reset Group'" />
                </div>
              </div>

              <!-- Search -->
              <div class="settings-search-bar">
                <span class="filter-search">
                  <i class="pi pi-search" />
                  <InputText v-model="search" placeholder="Search settings…" size="small" class="filter-input" />
                </span>
              </div>

              <!-- Loading skeleton -->
              <div v-if="loading" class="settings-skeleton">
                <div v-for="i in 4" :key="i" class="skeleton-row">
                  <Skeleton width="40%" height="14px" />
                  <Skeleton width="60%" height="14px" />
                </div>
              </div>

              <!-- Settings list -->
              <div v-else class="settings-list">
                <article
                  v-for="setting in filteredSettings"
                  :key="setting.id"
                  class="setting-row"
                >
                  <div class="setting-info">
                    <div class="setting-header">
                      <span class="setting-name">{{ setting.display_name || setting.key }}</span>
                      <div class="setting-badges">
                        <Tag :value="setting.type" :severity="typeSeverity(setting.type)" class="type-tag" />
                        <Tag v-if="setting.is_public" value="Public" severity="info" class="type-tag" />
                        <Tag v-if="setting.is_encrypted" value="Encrypted" severity="warn" class="type-tag" />
                      </div>
                    </div>
                    <span class="setting-key">{{ setting.key }}</span>
                    <span class="setting-desc" v-if="setting.description">{{ setting.description }}</span>
                  </div>

                  <div class="setting-value-area">
                    <!-- Boolean toggle -->
                    <template v-if="setting.type === 'boolean' && editingId !== setting.id">
                      <button
                        class="bool-toggle"
                        :class="{ enabled: !!setting.value }"
                        @click="handleToggleSetting(setting)"
                      >
                        <span class="bool-dot" />
                        <span class="bool-label">{{ setting.value ? 'ON' : 'OFF' }}</span>
                      </button>
                    </template>

                    <!-- Options select (non-editing) -->
                    <template v-else-if="setting.options?.length && editingId !== setting.id">
                      <span class="setting-display">{{ displayValue(setting) }}</span>
                    </template>

                    <!-- Inline edit mode -->
                    <template v-else-if="editingId === setting.id">
                      <div class="inline-edit">
                        <Textarea
                          v-if="setting.type === 'text' || setting.type === 'json'"
                          v-model="editValue"
                          rows="2"
                          class="edit-field"
                          autoResize
                        />
                        <Select
                          v-else-if="setting.options?.length"
                          v-model="editValue"
                          :options="setting.options"
                          optionLabel="label"
                          optionValue="value"
                          class="edit-field"
                          size="small"
                        />
                        <InputText
                          v-else
                          v-model="editValue"
                          class="edit-field"
                          size="small"
                          :type="setting.type === 'integer' || setting.type === 'float' ? 'number' : 'text'"
                        />
                        <div class="edit-actions">
                          <Button icon="pi pi-check" severity="success" text rounded size="small" :loading="saving" @click="saveInlineEdit(setting)" />
                          <Button icon="pi pi-times" severity="secondary" text rounded size="small" @click="cancelEdit" />
                        </div>
                      </div>
                    </template>

                    <!-- Display value -->
                    <template v-else>
                      <span class="setting-display" :class="{ encrypted: setting.is_encrypted }">
                        {{ displayValue(setting) }}
                      </span>
                    </template>
                  </div>

                  <div class="setting-actions">
                    <span class="setting-updated">{{ relativeTime(setting.updated_at ?? '') }}</span>
                    <Button
                      v-if="editingId !== setting.id"
                      icon="pi pi-pencil"
                      severity="secondary"
                      text
                      rounded
                      size="small"
                      @click="startEdit(setting)"
                      v-tooltip.left="'Edit'"
                    />
                    <Button
                      icon="pi pi-trash"
                      severity="danger"
                      text
                      rounded
                      size="small"
                      @click="confirmDeleteSetting(setting)"
                      v-tooltip.left="'Delete'"
                    />
                  </div>
                </article>

                <div v-if="filteredSettings.length === 0" class="empty-state">
                  <i class="pi pi-inbox" />
                  <span>No settings found</span>
                </div>
              </div>
            </div>
          </div>
        </TabPanel>

        <!-- ════════ MAINTENANCE TAB ════════ -->
        <TabPanel value="maintenance">
          <div class="maintenance-panel">
            <div class="maint-card">
              <div class="maint-header">
                <div class="maint-icon" :class="{ 'maint-active': maintenance?.is_enabled }">
                  <i :class="maintenance?.is_enabled ? 'pi pi-lock' : 'pi pi-lock-open'" />
                </div>
                <div class="maint-info">
                  <h3 class="maint-title">Maintenance Mode</h3>
                  <p class="maint-sub">{{ maintenance?.is_enabled ? 'System is currently in maintenance mode. Users cannot access the application.' : 'System is running normally. All users can access the application.' }}</p>
                </div>
              </div>

              <div class="maint-status">
                <div class="status-indicator" :class="{ active: maintenance?.is_enabled }">
                  <span class="status-dot" />
                  <span>{{ maintenance?.is_enabled ? 'Maintenance ON' : 'System Online' }}</span>
                </div>
                <Button
                  :icon="maintenance?.is_enabled ? 'pi pi-play' : 'pi pi-pause'"
                  :label="maintenance?.is_enabled ? 'Disable Maintenance' : 'Enable Maintenance'"
                  :severity="maintenance?.is_enabled ? 'success' : 'danger'"
                  size="small"
                  :loading="maintenanceLoading"
                  @click="handleToggleMaintenance"
                />
              </div>

              <div class="maint-details" v-if="maintenance">
                <div class="detail-row">
                  <span class="detail-label">Message</span>
                  <span class="detail-value">{{ maintenance.message || '—' }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Allowed IPs</span>
                  <div class="ip-chips">
                    <Tag v-for="ip in maintenance.allowed_ips" :key="ip" :value="ip" severity="info" class="ip-tag" />
                    <span v-if="!maintenance.allowed_ips.length" class="detail-muted">No IPs whitelisted</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </TabPanel>

        <!-- ════════ INTEGRATIONS TAB ════════ -->
        <TabPanel value="integrations">
          <div class="integrations-panel">
            <div class="integrations-grid">
              <article
                v-for="item in integrationOptions"
                :key="item.value"
                class="integration-card"
              >
                <div class="integ-header">
                  <span class="integ-icon">
                    <i :class="item.icon" />
                  </span>
                  <h4 class="integ-name">{{ item.label }}</h4>
                </div>
                <div class="integ-status" v-if="integrationResult && testingIntegration === null && item.value === (integrationResult as any)?._testedKey">
                  <!-- result shown briefly -->
                </div>
                <div class="integ-footer">
                  <Tag
                    v-if="integrationResult && testingIntegration === null"
                    :value="integrationResult.success ? 'Connected' : 'Failed'"
                    :severity="integrationResult.success ? 'success' : 'danger'"
                    class="type-tag"
                    style="visibility: hidden"
                  />
                  <Button
                    icon="pi pi-bolt"
                    label="Test"
                    severity="secondary"
                    size="small"
                    outlined
                    :loading="testingIntegration === item.value"
                    @click="handleTestIntegration(item.value as IntegrationType)"
                  />
                </div>
                <!-- Result toast -->
                <div
                  v-if="integrationResult && testingIntegration === null"
                  class="integ-result"
                  :class="{ success: integrationResult.success, error: !integrationResult.success }"
                  style="display: none"
                >
                  <i :class="integrationResult.success ? 'pi pi-check-circle' : 'pi pi-times-circle'" />
                  <span>{{ integrationResult.message }}</span>
                </div>
              </article>
            </div>

            <!-- Last test result banner -->
            <div v-if="integrationResult" class="test-result-banner" :class="{ success: integrationResult.success, error: !integrationResult.success }">
              <i :class="integrationResult.success ? 'pi pi-check-circle' : 'pi pi-times-circle'" />
              <span>{{ integrationResult.message }}</span>
            </div>
          </div>
        </TabPanel>

        <!-- ════════ AUDIT LOG TAB ════════ -->
        <TabPanel value="audit">
          <div class="audit-panel">
            <!-- Mobile cards -->
            <div class="audit-cards d-mobile">
              <div v-if="auditLoading" class="settings-skeleton">
                <Skeleton v-for="i in 4" :key="i" width="100%" height="60px" class="mb-2" />
              </div>
              <article v-else v-for="entry in auditLog" :key="entry.id" class="audit-card">
                <div class="audit-user-row">
                  <div class="audit-avatar">
                    <span>{{ initials(entry.user?.name || 'SY') }}</span>
                  </div>
                  <div class="audit-user-info">
                    <span class="audit-user-name">{{ entry.user?.name || 'System' }}</span>
                    <span class="audit-user-email">{{ entry.user?.email || '' }}</span>
                  </div>
                  <Tag :value="entry.action.replace('_', ' ')" :severity="actionSeverity(entry.action)" class="type-tag" />
                </div>
                <div class="audit-meta-row">
                  <span class="audit-key" v-if="entry.metadata?.key">{{ entry.metadata.key }}</span>
                  <span class="audit-time">{{ relativeTime(entry.created_at) }}</span>
                </div>
              </article>
              <div v-if="!auditLoading && auditLog.length === 0" class="empty-state">
                <i class="pi pi-history" />
                <span>No audit entries</span>
              </div>
            </div>

            <!-- Desktop table -->
            <div class="table-card d-desktop">
              <DataTable
                :value="auditLog"
                :loading="auditLoading"
                :rows="15"
                :totalRecords="auditTotal"
                stripedRows
                size="small"
                scrollable
                class="entity-table"
                dataKey="id"
              >
                <Column header="User" style="min-width: 180px">
                  <template #body="{ data }">
                    <div class="user-cell">
                      <div class="u-avatar">
                        <span>{{ initials(data.user?.name || 'SY') }}</span>
                      </div>
                      <div class="u-info">
                        <span class="u-name">{{ data.user?.name || 'System' }}</span>
                        <span class="u-email">{{ data.user?.email || '' }}</span>
                      </div>
                    </div>
                  </template>
                </Column>
                <Column field="action" header="Action" style="min-width: 120px">
                  <template #body="{ data }">
                    <Tag :value="data.action.replace('_', ' ')" :severity="actionSeverity(data.action)" class="type-tag" />
                  </template>
                </Column>
                <Column header="Key" style="min-width: 150px">
                  <template #body="{ data }">
                    <span class="audit-key">{{ data.metadata?.key || '—' }}</span>
                  </template>
                </Column>
                <Column header="Group" style="min-width: 100px">
                  <template #body="{ data }">
                    <span class="u-role">{{ capitalize(data.metadata?.group || '') }}</span>
                  </template>
                </Column>
                <Column field="created_at" header="Time" style="min-width: 140px">
                  <template #body="{ data }">
                    <span class="u-date">{{ formatDate(data.created_at) }}</span>
                  </template>
                </Column>
              </DataTable>
            </div>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- ════════ DIALOGS ════════ -->

    <!-- Create Setting -->
    <Dialog v-model:visible="showCreateDialog" header="New Setting" :modal="true" :style="{ width: '420px' }" class="compact-dialog">
      <div class="form-grid">
        <div class="form-field">
          <label class="field-label">Key</label>
          <InputText v-model="newSetting.key" placeholder="e.g. app_name" size="small" class="field-input" />
        </div>
        <div class="form-field">
          <label class="field-label">Display Name</label>
          <InputText v-model="newSetting.display_name" placeholder="Application Name" size="small" class="field-input" />
        </div>
        <div class="form-row-2">
          <div class="form-field">
            <label class="field-label">Group</label>
            <InputText v-model="newSetting.group" placeholder="general" size="small" class="field-input" />
          </div>
          <div class="form-field">
            <label class="field-label">Type</label>
            <Select v-model="newSetting.type" :options="settingTypeOptions" optionLabel="label" optionValue="value" size="small" class="field-input" />
          </div>
        </div>
        <div class="form-field">
          <label class="field-label">Value</label>
          <InputText v-model="newSetting.value" size="small" class="field-input" />
        </div>
        <div class="form-field">
          <label class="field-label">Description</label>
          <Textarea v-model="newSetting.description" rows="2" class="field-input" autoResize />
        </div>
        <div class="form-checks">
          <label class="check-label">
            <Checkbox v-model="newSetting.is_public" :binary="true" />
            <span>Public</span>
          </label>
          <label class="check-label">
            <Checkbox v-model="newSetting.is_encrypted" :binary="true" />
            <span>Encrypted</span>
          </label>
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" text size="small" @click="showCreateDialog = false" />
        <Button label="Create" size="small" :loading="actionLoading" @click="handleCreate" />
      </template>
    </Dialog>

    <!-- Delete Confirm -->
    <Dialog v-model:visible="showDeleteConfirm" header="Delete Setting" :modal="true" :style="{ width: '360px' }">
      <div class="confirm-body">
        <i class="pi pi-exclamation-triangle confirm-icon" />
        <p>Delete <strong>{{ deleteTarget?.display_name || deleteTarget?.key }}</strong>?</p>
        <p class="confirm-sub">This action cannot be undone.</p>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" text size="small" @click="showDeleteConfirm = false" />
        <Button label="Delete" severity="danger" size="small" :loading="actionLoading" @click="handleDelete" />
      </template>
    </Dialog>

    <!-- Reset Confirm -->
    <Dialog v-model:visible="showResetConfirm" header="Reset Group" :modal="true" :style="{ width: '360px' }">
      <div class="confirm-body">
        <i class="pi pi-exclamation-triangle confirm-icon" style="color: #f59e0b" />
        <p>Reset all <strong>{{ capitalize(resetTargetGroup) }}</strong> settings to defaults?</p>
        <p class="confirm-sub">Current values will be overwritten.</p>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" text size="small" @click="showResetConfirm = false" />
        <Button label="Reset" severity="warn" size="small" :loading="actionLoading" @click="handleReset" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.settings-page {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* ── Toolbar ─────────────────────────────────── */
.page-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.toolbar-left {
  display: flex;
  align-items: center;
  gap: 6px;
}
.page-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}
.toolbar-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.drawer-trigger {
  display: inline-flex;
}

/* ── Tabs ────────────────────────────────────── */
:deep(.settings-tabs .p-tablist) { background: transparent; }
:deep(.settings-tabs .p-tab) {
  font-size: 0.72rem !important;
  padding: 7px 12px !important;
  color: var(--text-muted) !important;
  background: transparent !important;
  border: none !important;
  display: flex;
  align-items: center;
  gap: 5px;
}
:deep(.settings-tabs .p-tab-active) { color: var(--active-color) !important; }
:deep(.settings-tabs .p-tabpanels) { background: transparent; padding: 8px 0 !important; }

/* ── Summary ─────────────────────────────────── */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 10px;
}
@media (min-width: 768px) {
  .summary-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.summary-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
}
.summary-icon {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.84rem;
  flex-shrink: 0;
}
.summary-copy { display: flex; flex-direction: column; min-width: 0; }
.summary-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.summary-value { font-size: 0.9rem; color: var(--text-primary); }

/* ── Settings layout ─────────────────────────── */
.settings-layout {
  display: flex;
  gap: 12px;
  min-height: 0;
}

.d-desktop-flex { display: none; }
@media (min-width: 768px) {
  .d-desktop-flex { display: flex; }
}

.d-mobile { display: block; }
.d-desktop { display: none; }
@media (min-width: 768px) {
  .d-mobile { display: none; }
  .d-desktop { display: block; }
}

/* ── Sidebar ─────────────────────────────────── */
.settings-sidebar {
  width: 200px;
  min-width: 200px;
  flex-direction: column;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
  overflow: hidden;
  position: sticky;
  top: 68px;
  align-self: flex-start;
  max-height: calc(100vh - 200px);
  overflow-y: auto;
}
.sidebar-header {
  padding: 10px 12px 6px;
  border-bottom: 1px solid var(--card-border);
}
.sidebar-title {
  font-size: 0.64rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
}
.sidebar-nav {
  display: flex;
  flex-direction: column;
  padding: 4px;
  gap: 1px;
}
.sidebar-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 8px;
  border-radius: 7px;
  border: none;
  background: transparent;
  cursor: pointer;
  transition: background 0.12s;
  width: 100%;
  text-align: left;
  color: var(--text-secondary);
  font-size: 0.74rem;
  font-weight: 500;
}
.sidebar-item:hover {
  background: var(--hover-bg);
}
.sidebar-item.active {
  background: color-mix(in srgb, var(--active-color) 12%, transparent);
  color: var(--active-color);
  font-weight: 600;
}
.sidebar-icon {
  width: 24px;
  height: 24px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.68rem;
  flex-shrink: 0;
}
.sidebar-label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sidebar-count {
  font-size: 0.6rem;
  color: var(--text-muted);
  background: var(--hover-bg);
  padding: 1px 6px;
  border-radius: 8px;
}

/* ── Main content ────────────────────────────── */
.settings-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 12px;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
}
.group-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.group-icon-lg {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  flex-shrink: 0;
}
.group-name {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}
.group-meta {
  font-size: 0.64rem;
  color: var(--text-muted);
}
.group-actions {
  display: flex;
  gap: 2px;
}

/* ── Search ──────────────────────────────────── */
.settings-search-bar {
  display: flex;
}
.filter-search {
  position: relative;
  flex: 1;
  min-width: 170px;
  max-width: 400px;
}
.filter-search i {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.8rem;
  color: var(--text-muted);
  z-index: 1;
}
.filter-input {
  width: 100%;
  padding-left: 32px !important;
  font-size: 0.78rem !important;
}

/* ── Settings list ───────────────────────────── */
.settings-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.setting-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
  transition: border-color 0.14s;
}
.setting-row:hover {
  border-color: color-mix(in srgb, var(--active-color) 30%, var(--card-border));
}

.setting-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.setting-header {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.setting-name {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-primary);
}
.setting-badges {
  display: flex;
  gap: 3px;
}
.type-tag {
  font-size: 0.56rem !important;
  padding: 1px 6px !important;
}
.setting-key {
  font-size: 0.62rem;
  color: var(--text-muted);
  font-family: 'SF Mono', 'Fira Code', monospace;
}
.setting-desc {
  font-size: 0.66rem;
  color: var(--text-muted);
  line-height: 1.3;
  margin-top: 2px;
}

.setting-value-area {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  min-width: 120px;
  max-width: 240px;
}
.setting-display {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-primary);
  word-break: break-all;
  padding: 3px 8px;
  background: var(--hover-bg);
  border-radius: 6px;
}
.setting-display.encrypted {
  color: var(--text-muted);
  font-style: italic;
}

/* ── Boolean toggle ──────────────────────────── */
.bool-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 4px;
  border-radius: 12px;
  border: 1px solid var(--card-border);
  background: var(--hover-bg);
  cursor: pointer;
  transition: all 0.2s;
  width: 56px;
  position: relative;
}
.bool-toggle.enabled {
  background: rgba(16, 185, 129, 0.15);
  border-color: #10b981;
}
.bool-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--text-muted);
  transition: all 0.2s;
  flex-shrink: 0;
}
.bool-toggle.enabled .bool-dot {
  background: #10b981;
  transform: translateX(22px);
}
.bool-label {
  font-size: 0.58rem;
  font-weight: 700;
  color: var(--text-muted);
  position: absolute;
  right: 6px;
}
.bool-toggle.enabled .bool-label {
  color: #10b981;
  left: 6px;
  right: auto;
}

/* ── Inline edit ─────────────────────────────── */
.inline-edit {
  display: flex;
  align-items: flex-start;
  gap: 4px;
  flex-wrap: wrap;
}
.edit-field {
  min-width: 140px;
  max-width: 200px;
  font-size: 0.75rem !important;
}
.edit-actions {
  display: flex;
  gap: 0;
}

.setting-actions {
  display: flex;
  align-items: center;
  gap: 2px;
  flex-shrink: 0;
}
.setting-updated {
  font-size: 0.6rem;
  color: var(--text-muted);
  white-space: nowrap;
}

/* ── Skeleton ────────────────────────────────── */
.settings-skeleton {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 8px 0;
}
.skeleton-row {
  display: flex;
  gap: 16px;
  padding: 12px;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
}

/* ── Empty state ─────────────────────────────── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 32px;
  color: var(--text-muted);
  font-size: 0.78rem;
}
.empty-state i { font-size: 1.4rem; }

/* ── Maintenance ─────────────────────────────── */
.maintenance-panel {
  max-width: 600px;
}
.maint-card {
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.maint-header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.maint-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  transition: all 0.2s;
}
.maint-icon.maint-active {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}
.maint-info { flex: 1; }
.maint-title {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}
.maint-sub {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin: 4px 0 0;
  line-height: 1.35;
}
.maint-status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 12px;
  background: var(--hover-bg);
  border-radius: 8px;
}
.status-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.74rem;
  font-weight: 600;
  color: #10b981;
}
.status-indicator.active { color: #ef4444; }
.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  animation: pulse-dot 2s ease-in-out infinite;
}
.status-indicator.active .status-dot { background: #ef4444; }
@keyframes pulse-dot {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}
.maint-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.detail-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.detail-label {
  font-size: 0.64rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted);
}
.detail-value {
  font-size: 0.75rem;
  color: var(--text-primary);
}
.detail-muted {
  font-size: 0.68rem;
  color: var(--text-muted);
}
.ip-chips {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}
.ip-tag {
  font-size: 0.6rem !important;
  padding: 2px 8px !important;
  font-family: 'SF Mono', 'Fira Code', monospace;
}

/* ── Integrations ────────────────────────────── */
.integrations-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.integrations-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}
@media (min-width: 480px) {
  .integrations-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 900px) {
  .integrations-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
.integration-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
  transition: border-color 0.14s;
}
.integration-card:hover {
  border-color: color-mix(in srgb, var(--active-color) 30%, var(--card-border));
}
.integ-header {
  display: flex;
  align-items: center;
  gap: 10px;
}
.integ-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.84rem;
  background: rgba(99, 102, 241, 0.12);
  color: #6366f1;
  flex-shrink: 0;
}
.integ-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}
.integ-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}
.test-result-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  font-size: 0.74rem;
  font-weight: 500;
}
.test-result-banner.success {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.2);
}
.test-result-banner.error {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.2);
}
.test-result-banner i { font-size: 0.9rem; }

/* ── Audit ───────────────────────────────────── */
.audit-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.audit-cards {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.audit-card {
  padding: 10px 12px;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.audit-user-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.audit-avatar {
  width: 26px;
  height: 26px;
  border-radius: 7px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 0.58rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.audit-user-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.audit-user-name {
  font-size: 0.74rem;
  font-weight: 600;
  color: var(--text-primary);
}
.audit-user-email {
  font-size: 0.6rem;
  color: var(--text-muted);
}
.audit-meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.audit-key {
  font-size: 0.66rem;
  font-family: 'SF Mono', 'Fira Code', monospace;
  color: var(--text-secondary);
  background: var(--hover-bg);
  padding: 1px 6px;
  border-radius: 4px;
}
.audit-time {
  font-size: 0.62rem;
  color: var(--text-muted);
}

/* ── Shared table styles ─────────────────────── */
.table-card {
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  overflow: hidden;
}
.entity-table { font-size: 0.75rem; }
:deep(.entity-table .p-datatable-table-container) {
  border-radius: 10px;
  overflow: hidden;
  background: var(--card-bg);
}
:deep(.entity-table .p-datatable-thead > tr > th) {
  background: var(--hover-bg);
  color: var(--text-secondary);
  border-color: var(--card-border);
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 10px 12px;
}
:deep(.entity-table .p-datatable-tbody > tr) {
  background: var(--card-bg);
  color: var(--text-primary);
  transition: background 0.12s;
}
:deep(.entity-table.p-datatable-striped .p-datatable-tbody > tr:nth-child(even)) {
  background: color-mix(in srgb, var(--card-bg) 76%, var(--hover-bg) 24%);
}
:deep(.entity-table .p-datatable-tbody > tr:hover) { background: var(--hover-bg); }
:deep(.entity-table .p-datatable-tbody > tr > td) {
  background: transparent;
  color: var(--text-primary);
  border-color: var(--card-border);
  padding: 8px 12px;
}

.user-cell { display: flex; align-items: center; gap: 8px; }
.u-avatar {
  width: 26px; height: 26px;
  border-radius: 7px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 0.58rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.u-info { display: flex; flex-direction: column; min-width: 0; }
.u-name { font-size: 0.74rem; font-weight: 600; color: var(--text-primary); }
.u-email { font-size: 0.6rem; color: var(--text-muted); }
.u-role { font-size: 0.72rem; font-weight: 500; color: var(--text-secondary); }
.u-date { font-size: 0.68rem; color: var(--text-muted); }

/* ── Confirm Dialog ──────────────────────────── */
.confirm-body { text-align: center; padding: 6px 0; }
.confirm-icon { font-size: 2rem; color: #ef4444; margin-bottom: 8px; }
.confirm-body p { font-size: 0.82rem; color: var(--text-primary); margin: 4px 0; }
.confirm-sub { font-size: 0.7rem; color: var(--text-muted); }

/* ── Create form ─────────────────────────────── */
.form-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.field-label {
  font-size: 0.68rem;
  font-weight: 600;
  color: var(--text-secondary);
}
.field-input {
  width: 100%;
  font-size: 0.78rem !important;
}
.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.form-checks {
  display: flex;
  gap: 16px;
}
.check-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.74rem;
  color: var(--text-secondary);
  cursor: pointer;
}

/* ── Mobile responsive ───────────────────────── */
@media (max-width: 767px) {
  .setting-row {
    flex-direction: column;
    gap: 8px;
  }
  .setting-value-area {
    min-width: 0;
    max-width: 100%;
    width: 100%;
  }
  .setting-actions {
    align-self: flex-end;
  }
  .settings-main {
    width: 100%;
  }
  .maint-header {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }
  .maint-status {
    flex-direction: column;
    gap: 10px;
  }
  .form-row-2 {
    grid-template-columns: 1fr;
  }
}

/* ── Group drawer mobile ─────────────────────── */
:deep(.group-drawer .p-dialog-header) {
  padding: 12px 16px;
  font-size: 0.82rem;
}
:deep(.group-drawer .p-dialog-content) {
  padding: 8px;
}
</style>
