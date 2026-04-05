<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getAuditLog,
  getMaintenanceStatus,
  toggleMaintenance,
  updateMaintenance,
  getAiIntegrations,
  updateAiIntegrations,
  testAiIntegration,
} from '@/services/settingsService'
import type {
  MaintenanceStatus,
  AiIntegrationSetting,
  AuditLogEntry,
  AiIntegrationType,
} from '@/types/settings'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import Skeleton from 'primevue/skeleton'
import Message from 'primevue/message'

const { t } = useI18n()

// ── Inline flash message (no ToastService needed) ───────────
const flash = ref<{ severity: 'success' | 'warn' | 'error' | 'info'; text: string } | null>(null)
let flashTimer: ReturnType<typeof setTimeout> | null = null
function showFlash(severity: 'success' | 'warn' | 'error' | 'info', text: string, ms = 4000) {
  flash.value = { severity, text }
  if (flashTimer) clearTimeout(flashTimer)
  flashTimer = setTimeout(() => { flash.value = null }, ms)
}

// ── State ───────────────────────────────────────────────────
const activeTab = ref('audit-log')
const loading = ref({ audit: true, maintenance: true, integrations: true })

// Audit Log
const auditEntries = ref<AuditLogEntry[]>([])
const auditTotal = ref(0)
const auditPage = ref(1)
const auditPerPage = ref(20)
const auditActionFilter = ref('')

// Maintenance
const maintenance = ref<MaintenanceStatus | null>(null)
const maintenanceMessage = ref('')
const maintenanceIps = ref<string[]>([])
const newIp = ref('')
const togglingMaintenance = ref(false)
const savingMaintenance = ref(false)

// AI Integrations
const integrations = ref<AiIntegrationSetting[]>([])
const editValues = ref<Record<string, string>>({})
const testingIntegration = ref<string | null>(null)
const testResult = ref<{ type: string; success: boolean; message: string } | null>(null)
const savingIntegrations = ref(false)

// ── Computed ────────────────────────────────────────────────
const geminiSettings = computed(() => integrations.value.filter(s => s.key.startsWith('gemini_')))

const auditActions = [
  { label: t('settings.allActions'), value: '' },
  { label: 'setting_updated', value: 'setting_updated' },
  { label: 'maintenance_enabled', value: 'maintenance_enabled' },
  { label: 'maintenance_disabled', value: 'maintenance_disabled' },
  { label: 'maintenance_settings_updated', value: 'maintenance_settings_updated' },
  { label: 'integration_tested', value: 'integration_tested' },
]

// ── Audit Log ───────────────────────────────────────────────
async function loadAuditLog() {
  loading.value.audit = true
  try {
    const res = await getAuditLog({
      page: auditPage.value,
      per_page: auditPerPage.value,
      action: auditActionFilter.value || undefined,
    })
    auditEntries.value = res.data ?? []
    auditTotal.value = res.meta?.total ?? 0
  } catch {
    // silent
  } finally {
    loading.value.audit = false
  }
}

function onAuditPage(event: { page: number; rows: number }) {
  auditPage.value = event.page + 1
  auditPerPage.value = event.rows
  loadAuditLog()
}

function onAuditFilter() {
  auditPage.value = 1
  loadAuditLog()
}

// ── Maintenance ─────────────────────────────────────────────
async function loadMaintenance() {
  loading.value.maintenance = true
  try {
    const res = await getMaintenanceStatus()
    maintenance.value = res.data
    maintenanceMessage.value = res.data.message
    maintenanceIps.value = Array.isArray(res.data.allowed_ips) ? [...res.data.allowed_ips] : []
  } catch {
    // silent
  } finally {
    loading.value.maintenance = false
  }
}

async function handleToggleMaintenance() {
  togglingMaintenance.value = true
  try {
    const res = await toggleMaintenance()
    maintenance.value = res.data
    maintenanceMessage.value = res.data.message
    maintenanceIps.value = Array.isArray(res.data.allowed_ips) ? [...res.data.allowed_ips] : []
    showFlash(
      res.data.is_enabled ? 'warn' : 'success',
      res.data.is_enabled ? t('settings.maintenanceEnabled') : t('settings.maintenanceDisabled'),
    )
  } catch {
    showFlash('error', 'Error')
  } finally {
    togglingMaintenance.value = false
  }
}

async function saveMaintenanceSettings() {
  savingMaintenance.value = true
  try {
    const res = await updateMaintenance({
      message: maintenanceMessage.value,
      allowed_ips: maintenanceIps.value,
    })
    maintenance.value = res.data
    showFlash('success', t('settings.saveMaintenanceSettings'))
  } catch {
    showFlash('error', 'Error')
  } finally {
    savingMaintenance.value = false
  }
}

function addIp() {
  const ip = newIp.value.trim()
  if (ip && !maintenanceIps.value.includes(ip)) {
    maintenanceIps.value.push(ip)
    newIp.value = ''
  }
}

function removeIp(index: number) {
  maintenanceIps.value.splice(index, 1)
}

// ── AI Integrations ─────────────────────────────────────────
async function loadIntegrations() {
  loading.value.integrations = true
  try {
    const res = await getAiIntegrations()
    integrations.value = res.data
    editValues.value = {}
    res.data.forEach((s: AiIntegrationSetting) => {
      editValues.value[s.key] = String(s.value ?? '')
    })
  } catch {
    // silent
  } finally {
    loading.value.integrations = false
  }
}

async function saveIntegrations() {
  savingIntegrations.value = true
  try {
    const items = Object.entries(editValues.value).map(([key, value]) => ({ key, value }))
    const res = await updateAiIntegrations(items)
    if (res.data.errors?.length) {
      showFlash('warn', `${res.data.errors.length} errors`)
    } else {
      showFlash('success', t('settings.saveIntegrations'))
    }
    await loadIntegrations()
  } catch {
    showFlash('error', 'Error')
  } finally {
    savingIntegrations.value = false
  }
}

async function handleTestIntegration(type: AiIntegrationType) {
  testingIntegration.value = type
  testResult.value = null
  try {
    const res = await testAiIntegration({ integration: type })
    testResult.value = { type, success: res.data.success, message: res.data.message }
    showFlash(
      res.data.success ? 'success' : 'error',
      res.data.success ? t('settings.connectionSuccess') : t('settings.connectionFailed'),
      5000,
    )
  } catch {
    showFlash('error', t('settings.connectionFailed'))
  } finally {
    testingIntegration.value = null
  }
}

function settingLabel(key: string): string {
  const map: Record<string, string> = {
    gemini_api_key: t('settings.apiKey'),
    gemini_text_model: t('settings.textModel'),
    gemini_image_model: t('settings.imageModel'),
  }
  return map[key] || key
}

// ── Helpers ─────────────────────────────────────────────────
function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', {
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function actionSeverity(action: string): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  if (action.includes('enabled') || action.includes('created')) return 'success'
  if (action.includes('disabled') || action.includes('deleted')) return 'danger'
  if (action.includes('tested')) return 'info'
  return 'secondary'
}

// ── Init ────────────────────────────────────────────────────
onMounted(() => {
  loadAuditLog()
  loadMaintenance()
  loadIntegrations()
})
</script>

<template>
  <div class="settings-page">
    <div class="settings-header">
      <h1 class="settings-title">{{ t('settings.title') }}</h1>
    </div>

    <!-- Inline flash message -->
    <transition name="flash">
      <Message v-if="flash" :severity="flash.severity" :closable="true" @close="flash = null" class="flash-msg">
        {{ flash.text }}
      </Message>
    </transition>

    <Tabs v-model:value="activeTab" class="settings-tabs">
      <TabList>
        <Tab value="audit-log"><i class="pi pi-list" /> <span>{{ t('settings.auditLogTab') }}</span></Tab>
        <Tab value="maintenance"><i class="pi pi-wrench" /> <span>{{ t('settings.maintenanceTab') }}</span></Tab>
        <Tab value="ai-integrations"><i class="pi pi-microchip-ai" /> <span>{{ t('settings.aiIntegrationsTab') }}</span></Tab>
      </TabList>

      <TabPanels>
      <!-- ══════ Audit Log Tab ══════ -->
      <TabPanel value="audit-log">
        <div class="tab-content">
          <!-- Filter -->
          <div class="audit-toolbar">
            <Select
              v-model="auditActionFilter"
              :options="auditActions"
              optionLabel="label"
              optionValue="value"
              :placeholder="t('settings.filterByAction')"
              class="audit-filter"
              @change="onAuditFilter"
            />
          </div>

          <template v-if="loading.audit">
            <div v-for="n in 5" :key="n" class="skeleton-row">
              <Skeleton width="100%" height="2.5rem" />
            </div>
          </template>

          <template v-else-if="auditEntries.length">
            <DataTable
              :value="auditEntries"
              :paginator="true"
              :rows="auditPerPage"
              :totalRecords="auditTotal"
              :lazy="true"
              :loading="loading.audit"
              @page="onAuditPage"
              stripedRows
              class="audit-table"
            >
              <Column :header="t('settings.action')" field="action" style="min-width: 12rem">
                <template #body="{ data }">
                  <Tag :value="data.action" :severity="actionSeverity(data.action)" />
                </template>
              </Column>
              <Column :header="t('settings.performedBy')" style="min-width: 12rem">
                <template #body="{ data }">
                  <span v-if="data.user">{{ data.user.name }}</span>
                  <span v-else class="text-muted">System</span>
                </template>
              </Column>
              <Column :header="t('settings.details')" style="min-width: 16rem">
                <template #body="{ data }">
                  <div v-if="data.metadata" class="audit-meta">
                    <span v-if="data.metadata.setting_key" class="meta-key">{{ data.metadata.setting_key }}</span>
                    <span v-if="data.metadata.message" class="meta-msg">{{ data.metadata.message }}</span>
                    <span v-if="data.metadata.integration" class="meta-msg">{{ data.metadata.integration }}</span>
                  </div>
                  <span v-else class="text-muted">—</span>
                </template>
              </Column>
              <Column :header="t('settings.when')" style="min-width: 10rem">
                <template #body="{ data }">
                  {{ formatDate(data.created_at) }}
                </template>
              </Column>
            </DataTable>
          </template>

          <div v-else class="empty-msg">
            <i class="pi pi-list" />
            <p>{{ t('settings.noLogs') }}</p>
          </div>
        </div>
      </TabPanel>

      <!-- ══════ Maintenance Tab ══════ -->
      <TabPanel value="maintenance">
        <div class="tab-content">
          <template v-if="loading.maintenance">
            <Skeleton width="100%" height="8rem" />
          </template>

          <template v-else-if="maintenance">
            <!-- Toggle Card -->
            <div class="maint-toggle-card" :class="{ active: maintenance.is_enabled }">
              <div class="maint-toggle-info">
                <i class="pi" :class="maintenance.is_enabled ? 'pi-lock' : 'pi-lock-open'" />
                <div>
                  <h3>{{ t('settings.maintenance') }}</h3>
                  <p>{{ maintenance.is_enabled ? t('settings.maintenanceEnabled') : t('settings.maintenanceDisabled') }}</p>
                </div>
              </div>
              <ToggleSwitch
                :modelValue="maintenance.is_enabled"
                @update:modelValue="handleToggleMaintenance"
                :disabled="togglingMaintenance"
              />
            </div>

            <!-- Settings Form -->
            <div class="maint-form">
              <div class="form-group">
                <label>{{ t('settings.maintenanceMessage') }}</label>
                <Textarea
                  v-model="maintenanceMessage"
                  :placeholder="t('settings.maintenanceMessagePlaceholder')"
                  rows="3"
                  class="w-full"
                />
              </div>

              <div class="form-group">
                <label>{{ t('settings.allowedIps') }}</label>
                <p class="form-help">{{ t('settings.allowedIpsHelp') }}</p>
                <div class="ip-list">
                  <div v-for="(ip, idx) in maintenanceIps" :key="idx" class="ip-chip">
                    <span>{{ ip }}</span>
                    <button class="ip-remove" @click="removeIp(idx)"><i class="pi pi-times" /></button>
                  </div>
                </div>
                <div class="ip-add-row">
                  <InputText v-model="newIp" placeholder="192.168.1.1" class="ip-input" @keyup.enter="addIp" />
                  <Button :label="t('settings.addIp')" icon="pi pi-plus" severity="secondary" size="small" @click="addIp" />
                </div>
              </div>

              <Button
                :label="t('settings.saveMaintenanceSettings')"
                icon="pi pi-save"
                :loading="savingMaintenance"
                @click="saveMaintenanceSettings"
                class="save-btn"
              />
            </div>
          </template>
        </div>
      </TabPanel>

      <!-- ══════ AI Integrations Tab ══════ -->
      <TabPanel value="ai-integrations">
        <div class="tab-content">
          <template v-if="loading.integrations">
            <Skeleton width="100%" height="12rem" />
          </template>

          <template v-else-if="integrations.length">
            <!-- Google Gemini -->
            <div class="integration-card">
              <div class="integration-header">
                <div class="integration-title">
                  <i class="pi pi-sparkles" />
                  <h3>{{ t('settings.gemini') }}</h3>
                </div>
                <Button
                  :label="testingIntegration === 'gemini' ? t('settings.testing') : t('settings.testConnection')"
                  icon="pi pi-bolt"
                  severity="secondary"
                  size="small"
                  :loading="testingIntegration === 'gemini'"
                  @click="handleTestIntegration('gemini')"
                />
              </div>
              <div class="integration-fields">
                <div v-for="s in geminiSettings" :key="s.key" class="field-row">
                  <label>{{ settingLabel(s.key) }}</label>
                  <InputText
                    v-model="editValues[s.key]"
                    :type="s.is_encrypted ? 'password' : 'text'"
                    :placeholder="s.display_name || s.key"
                    class="w-full"
                  />
                </div>
              </div>
              <div v-if="testResult && testResult.type === 'gemini'" class="test-result" :class="{ success: testResult.success, error: !testResult.success }">
                <i class="pi" :class="testResult.success ? 'pi-check-circle' : 'pi-times-circle'" />
                <span>{{ testResult.message }}</span>
              </div>
            </div>

            <Button
              :label="t('settings.saveIntegrations')"
              icon="pi pi-save"
              :loading="savingIntegrations"
              @click="saveIntegrations"
              class="save-btn"
            />
          </template>

          <div v-else class="empty-msg">
            <i class="pi pi-cog" />
            <p>{{ t('settings.noIntegrations') }}</p>
          </div>
        </div>
      </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.settings-page {
  padding: 1.5rem;
  max-width: 1100px;
}

.settings-header {
  margin-bottom: 1.5rem;
}

.settings-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
}

.settings-tabs :deep(.p-tabpanels) {
  padding: 0;
  background: transparent;
}

.tab-content {
  padding: 1.25rem 0;
}

/* ── Audit Log ─────────────────────────────── */
.audit-toolbar {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.audit-filter {
  min-width: 14rem;
}

.audit-table :deep(.p-datatable-thead > tr > th) {
  background: var(--card-bg);
  border-color: var(--card-border);
  color: var(--text-muted);
  font-weight: 600;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.audit-meta {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.meta-key {
  font-family: monospace;
  font-size: 0.82rem;
  color: var(--text-primary);
}

.meta-msg {
  font-size: 0.78rem;
  color: var(--text-muted);
}

.skeleton-row {
  margin-bottom: 0.5rem;
}

.text-muted {
  color: var(--text-muted);
  font-size: 0.85rem;
}

/* ── Maintenance ───────────────────────────── */
.maint-toggle-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-radius: 12px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  margin-bottom: 1.5rem;
  transition: border-color 0.2s;
}

.maint-toggle-card.active {
  border-color: #f59e0b;
  background: rgba(245, 158, 11, 0.04);
}

.maint-toggle-info {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.maint-toggle-info i {
  font-size: 1.5rem;
  color: var(--text-muted);
}

.maint-toggle-card.active .maint-toggle-info i {
  color: #f59e0b;
}

.maint-toggle-info h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
}

.maint-toggle-info p {
  margin: 0.15rem 0 0;
  font-size: 0.83rem;
  color: var(--text-muted);
}

.maint-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-group label {
  display: block;
  font-weight: 600;
  font-size: 0.88rem;
  color: var(--text-primary);
  margin-bottom: 0.35rem;
}

.form-help {
  font-size: 0.78rem;
  color: var(--text-muted);
  margin: 0 0 0.5rem;
}

.ip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-bottom: 0.5rem;
}

.ip-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  font-size: 0.83rem;
  font-family: monospace;
}

.ip-remove {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  padding: 0;
  line-height: 1;
}

.ip-remove:hover {
  color: #ef4444;
}

.ip-add-row {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.ip-input {
  max-width: 14rem;
}

/* ── AI Integrations ───────────────────────── */
.integration-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 12px;
  padding: 1.25rem 1.5rem;
  margin-bottom: 1rem;
}

.integration-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.integration-title {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.integration-title i {
  font-size: 1.2rem;
  color: #8b5cf6;
}

.integration-title h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--text-primary);
}

.integration-fields {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.field-row {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.field-row label {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.test-result {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.75rem;
  padding: 0.6rem 0.85rem;
  border-radius: 8px;
  font-size: 0.85rem;
}

.test-result.success {
  background: rgba(16, 185, 129, 0.08);
  color: #10b981;
}

.test-result.error {
  background: rgba(239, 68, 68, 0.08);
  color: #ef4444;
}

/* ── Shared ────────────────────────────────── */
.save-btn {
  margin-top: 0.5rem;
  align-self: flex-start;
}

.empty-msg {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 3rem 1rem;
  color: var(--text-muted);
}

.empty-msg i {
  font-size: 2rem;
  opacity: 0.4;
}

.empty-msg p {
  margin: 0;
  font-size: 0.9rem;
}

.w-full {
  width: 100%;
}

/* ── Flash message ─────────────────────────── */
.flash-msg {
  margin-bottom: 1rem;
}

.flash-enter-active,
.flash-leave-active {
  transition: opacity 0.25s, transform 0.25s;
}

.flash-enter-from,
.flash-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
