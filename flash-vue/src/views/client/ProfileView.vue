<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Chart from 'primevue/chart'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Message from 'primevue/message'

const { t } = useI18n()
const auth = useAuthStore()

const activeTab = ref('profile')
const saving = ref(false)
const pwSaving = ref(false)
const successMsg = ref('')
const errorMsg = ref('')
const pwSuccess = ref('')
const pwError = ref('')

// ── Profile form ──
const profileForm = ref({
  name: 'محمد ابواخوات',
  email: 'mohammad@klek.ai',
  phone: '+966 55 123 4567',
  timezone: 'Asia/Riyadh',
  locale: 'ar',
})

// ── Password form ──
const pwForm = ref({
  current: '',
  password: '',
  confirm: '',
})
const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)

const pwMatch = computed(() => pwForm.value.password === pwForm.value.confirm)
const pwValid = computed(() =>
  pwForm.value.current.length >= 1 &&
  pwForm.value.password.length >= 8 &&
  pwMatch.value,
)

// ── Subscription mock data ──
const subscription = ref({
  plan: { id: 3, name: 'Professional', slug: 'pro' },
  status: 'active',
  billing_cycle: 'monthly',
  current_period_start: '2026-03-04',
  current_period_end: '2026-04-04',
  price: 29,
  currency: 'USD',
  next_billing_date: '2026-04-04',
  auto_renew: true,
})

// ── Usage mock data ──
const usage = ref({
  images_generated: 187,
  images_limit: 500,
  credits_used: 842,
  credits_limit: 1500,
  api_calls: 56,
  api_limit: 200,
  storage_used_mb: 312,
  storage_limit_mb: 2048,
  upscales_used: 14,
  upscales_limit: 50,
})

const imagesPercent = computed(() => Math.round((usage.value.images_generated / usage.value.images_limit) * 100))
const creditsPercent = computed(() => Math.round((usage.value.credits_used / usage.value.credits_limit) * 100))
const apiPercent = computed(() => Math.round((usage.value.api_calls / usage.value.api_limit) * 100))
const storagePercent = computed(() => Math.round((usage.value.storage_used_mb / usage.value.storage_limit_mb) * 100))
const upscalesPercent = computed(() => Math.round((usage.value.upscales_used / usage.value.upscales_limit) * 100))

// ── Usage history chart ──
const usageChartData = computed(() => ({
  labels: ['Mar 29', 'Mar 30', 'Mar 31', 'Apr 01', 'Apr 02', 'Apr 03', 'Apr 04'],
  datasets: [
    {
      label: t('profile.images'),
      data: [22, 31, 18, 27, 35, 29, 25],
      borderColor: '#8b5cf6',
      backgroundColor: 'rgba(139, 92, 246, 0.08)',
      fill: true,
      tension: 0.35,
      borderWidth: 2,
      pointRadius: 3,
    },
    {
      label: t('profile.credits'),
      data: [95, 142, 88, 118, 156, 131, 112],
      borderColor: '#0ea5e9',
      backgroundColor: 'rgba(14, 165, 233, 0.04)',
      fill: false,
      tension: 0.35,
      borderWidth: 2,
      pointRadius: 3,
    },
  ],
}))

function mutedText() {
  return document.documentElement.classList.contains('dark') ? '#71717a' : '#94a3b8'
}
function gridColor() {
  return document.documentElement.classList.contains('dark') ? 'rgba(113,113,122,0.13)' : 'rgba(148,163,184,0.13)'
}

const usageChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { labels: { color: mutedText(), usePointStyle: true, pointStyle: 'circle' } } },
  scales: {
    x: { grid: { color: gridColor() }, ticks: { color: mutedText(), font: { size: 10 } } },
    y: { grid: { color: gridColor() }, ticks: { color: mutedText(), font: { size: 10 } }, beginAtZero: true },
  },
}))

// ── Recent activity ──
const recentActivity = ref([
  { id: 1, action: 'image_generated', description: 'Professional logo design', date: '2026-04-04T14:22:00Z', credits: 8 },
  { id: 2, action: 'image_generated', description: 'Product photo background removal', date: '2026-04-04T12:05:00Z', credits: 5 },
  { id: 3, action: 'image_upscaled', description: 'Upscale editorial banner', date: '2026-04-03T18:30:00Z', credits: 12 },
  { id: 4, action: 'image_generated', description: 'Cartoon character illustration', date: '2026-04-03T10:15:00Z', credits: 6 },
  { id: 5, action: 'style_applied', description: 'Watercolor effect applied', date: '2026-04-02T16:44:00Z', credits: 4 },
])

// ── Invoices ──
const invoices = ref([
  { id: 1, number: 'INV-2026-0042', date: '2026-04-04', amount: 29, status: 'paid' },
  { id: 2, number: 'INV-2026-0031', date: '2026-03-04', amount: 29, status: 'paid' },
  { id: 3, number: 'INV-2026-0019', date: '2026-02-04', amount: 29, status: 'paid' },
])

// ── Handlers ──
async function saveProfile() {
  saving.value = true
  successMsg.value = ''
  errorMsg.value = ''
  try {
    await new Promise(r => setTimeout(r, 1000))
    successMsg.value = t('profile.profileSaved')
  } catch {
    errorMsg.value = t('profile.saveFailed')
  } finally {
    saving.value = false
  }
}

async function changePassword() {
  if (!pwValid.value) return
  pwSaving.value = true
  pwSuccess.value = ''
  pwError.value = ''
  try {
    await new Promise(r => setTimeout(r, 1000))
    pwSuccess.value = t('profile.passwordChanged')
    pwForm.value = { current: '', password: '', confirm: '' }
  } catch {
    pwError.value = t('profile.passwordFailed')
  } finally {
    pwSaving.value = false
  }
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatDateTime(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function actionIcon(action: string) {
  const map: Record<string, string> = {
    image_generated: 'pi pi-image',
    image_upscaled: 'pi pi-expand',
    style_applied: 'pi pi-palette',
  }
  return map[action] || 'pi pi-bolt'
}

function actionColor(action: string) {
  const map: Record<string, string> = {
    image_generated: '#8b5cf6',
    image_upscaled: '#0ea5e9',
    style_applied: '#f59e0b',
  }
  return map[action] || '#64748b'
}

function usageBarColor(percent: number): string {
  if (percent >= 90) return '#ef4444'
  if (percent >= 70) return '#f59e0b'
  return '#10b981'
}

function invoiceSeverity(status: string): 'success' | 'warn' | 'danger' | 'secondary' {
  return status === 'paid' ? 'success' : status === 'overdue' ? 'danger' : 'warn'
}
</script>

<template>
  <div class="profile-page">
    <div class="page-header">
      <h1 class="page-title">{{ t('profile.pageTitle') }}</h1>
      <p class="page-sub">{{ t('profile.pageSub') }}</p>
    </div>

    <Tabs v-model:value="activeTab" class="profile-tabs">
      <TabList>
        <Tab value="profile"><i class="pi pi-user" /> <span>{{ t('profile.tabProfile') }}</span></Tab>
        <Tab value="security"><i class="pi pi-shield" /> <span>{{ t('profile.tabSecurity') }}</span></Tab>
        <Tab value="subscription"><i class="pi pi-credit-card" /> <span>{{ t('profile.tabSubscription') }}</span></Tab>
        <Tab value="usage"><i class="pi pi-chart-bar" /> <span>{{ t('profile.tabUsage') }}</span></Tab>
      </TabList>

      <TabPanels>
        <!-- ═══ PROFILE TAB ═══ -->
        <TabPanel value="profile">
          <div class="tab-content">
            <section class="section-card">
              <div class="section-head">
                <h2 class="section-title">{{ t('profile.personalInfo') }}</h2>
                <p class="section-desc">{{ t('profile.personalInfoDesc') }}</p>
              </div>

              <Message v-if="successMsg" severity="success" :closable="true" @close="successMsg = ''">{{ successMsg }}</Message>
              <Message v-if="errorMsg" severity="error" :closable="true" @close="errorMsg = ''">{{ errorMsg }}</Message>

              <form class="profile-form" @submit.prevent="saveProfile">
                <!-- Avatar -->
                <div class="avatar-row">
                  <div class="avatar-circle">
                    <span class="avatar-initials">{{ profileForm.name.split(' ').map(w => w[0]).join('').slice(0, 2) }}</span>
                  </div>
                  <div class="avatar-info">
                    <span class="avatar-name">{{ profileForm.name }}</span>
                    <span class="avatar-email">{{ profileForm.email }}</span>
                  </div>
                  <Button :label="t('profile.changeAvatar')" icon="pi pi-camera" severity="secondary" outlined size="small" class="avatar-btn" />
                </div>

                <div class="form-grid">
                  <div class="form-field">
                    <label class="field-label">{{ t('profile.fullName') }}</label>
                    <InputText v-model="profileForm.name" size="small" class="field-input" />
                  </div>
                  <div class="form-field">
                    <label class="field-label">{{ t('profile.emailAddress') }}</label>
                    <InputText v-model="profileForm.email" type="email" size="small" class="field-input" />
                  </div>
                  <div class="form-field">
                    <label class="field-label">{{ t('profile.phone') }}</label>
                    <InputText v-model="profileForm.phone" size="small" class="field-input" />
                  </div>
                  <div class="form-field">
                    <label class="field-label">{{ t('profile.timezone') }}</label>
                    <InputText v-model="profileForm.timezone" size="small" class="field-input" disabled />
                  </div>
                </div>

                <div class="form-actions">
                  <Button type="submit" :label="t('profile.saveChanges')" icon="pi pi-check" :loading="saving" size="small" />
                </div>
              </form>
            </section>
          </div>
        </TabPanel>

        <!-- ═══ SECURITY TAB ═══ -->
        <TabPanel value="security">
          <div class="tab-content">
            <section class="section-card">
              <div class="section-head">
                <h2 class="section-title">{{ t('profile.changePassword') }}</h2>
                <p class="section-desc">{{ t('profile.changePasswordDesc') }}</p>
              </div>

              <Message v-if="pwSuccess" severity="success" :closable="true" @close="pwSuccess = ''">{{ pwSuccess }}</Message>
              <Message v-if="pwError" severity="error" :closable="true" @close="pwError = ''">{{ pwError }}</Message>

              <form class="pw-form" @submit.prevent="changePassword">
                <div class="form-field">
                  <label class="field-label">{{ t('profile.currentPassword') }}</label>
                  <span class="field-input-wrap has-toggle">
                    <i class="pi pi-lock" />
                    <InputText
                      v-model="pwForm.current"
                      :type="showCurrent ? 'text' : 'password'"
                      :placeholder="t('profile.currentPasswordPlaceholder')"
                      autocomplete="current-password"
                      size="small"
                      class="field-input with-icon"
                    />
                    <i :class="showCurrent ? 'pi pi-eye-slash' : 'pi pi-eye'" class="toggle-pw" @click="showCurrent = !showCurrent" />
                  </span>
                </div>

                <div class="form-field">
                  <label class="field-label">{{ t('profile.newPassword') }}</label>
                  <span class="field-input-wrap has-toggle">
                    <i class="pi pi-lock" />
                    <InputText
                      v-model="pwForm.password"
                      :type="showNew ? 'text' : 'password'"
                      :placeholder="t('profile.newPasswordPlaceholder')"
                      autocomplete="new-password"
                      size="small"
                      class="field-input with-icon"
                    />
                    <i :class="showNew ? 'pi pi-eye-slash' : 'pi pi-eye'" class="toggle-pw" @click="showNew = !showNew" />
                  </span>
                  <small v-if="pwForm.password.length > 0 && pwForm.password.length < 8" class="field-hint warn">{{ t('profile.minChars') }}</small>
                </div>

                <div class="form-field">
                  <label class="field-label">{{ t('profile.confirmNewPassword') }}</label>
                  <span class="field-input-wrap has-toggle">
                    <i class="pi pi-lock" />
                    <InputText
                      v-model="pwForm.confirm"
                      :type="showConfirm ? 'text' : 'password'"
                      :placeholder="t('profile.confirmNewPlaceholder')"
                      autocomplete="new-password"
                      size="small"
                      class="field-input with-icon"
                    />
                    <i :class="showConfirm ? 'pi pi-eye-slash' : 'pi pi-eye'" class="toggle-pw" @click="showConfirm = !showConfirm" />
                  </span>
                  <small v-if="pwForm.confirm.length > 0 && !pwMatch" class="field-hint warn">{{ t('profile.passwordMismatch') }}</small>
                </div>

                <div class="form-actions">
                  <Button type="submit" :label="t('profile.updatePassword')" icon="pi pi-shield" :loading="pwSaving" :disabled="!pwValid" size="small" />
                </div>
              </form>
            </section>

            <!-- Sessions -->
            <section class="section-card">
              <div class="section-head">
                <h2 class="section-title">{{ t('profile.activeSessions') }}</h2>
                <p class="section-desc">{{ t('profile.activeSessionsDesc') }}</p>
              </div>
              <div class="sessions-list">
                <div class="session-item current">
                  <div class="session-icon"><i class="pi pi-desktop" /></div>
                  <div class="session-info">
                    <span class="session-device">Windows · Chrome 124</span>
                    <span class="session-meta">Riyadh, SA · {{ t('profile.currentSession') }}</span>
                  </div>
                  <Tag :value="t('profile.thisDevice')" severity="success" class="mini-tag" />
                </div>
                <div class="session-item">
                  <div class="session-icon"><i class="pi pi-mobile" /></div>
                  <div class="session-info">
                    <span class="session-device">iPhone 15 · Safari</span>
                    <span class="session-meta">Riyadh, SA · 2h ago</span>
                  </div>
                  <Button :label="t('profile.revoke')" severity="danger" text size="small" />
                </div>
              </div>
            </section>
          </div>
        </TabPanel>

        <!-- ═══ SUBSCRIPTION TAB ═══ -->
        <TabPanel value="subscription">
          <div class="tab-content">
            <!-- Current plan card -->
            <section class="section-card plan-hero">
              <div class="plan-hero-row">
                <div class="plan-hero-info">
                  <div class="plan-badge">
                    <i class="pi pi-crown" />
                    <span>{{ subscription.plan.name }}</span>
                  </div>
                  <h2 class="plan-price-big">${{ subscription.price }}<span class="plan-cycle">/{{ t('profile.month') }}</span></h2>
                  <div class="plan-meta">
                    <Tag :value="subscription.status" :severity="subscription.status === 'active' ? 'success' : 'warn'" class="mini-tag" />
                    <span class="plan-period">
                      {{ formatDate(subscription.current_period_start) }} — {{ formatDate(subscription.current_period_end) }}
                    </span>
                  </div>
                </div>
                <div class="plan-hero-actions">
                  <Button :label="t('profile.upgradePlan')" icon="pi pi-arrow-up" size="small" />
                  <Button :label="t('profile.manageBilling')" icon="pi pi-credit-card" severity="secondary" outlined size="small" />
                </div>
              </div>
            </section>

            <!-- Quick usage summary -->
            <div class="usage-summary-grid">
              <article class="usage-mini-card">
                <div class="usage-mini-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                  <i class="pi pi-image" />
                </div>
                <div class="usage-mini-body">
                  <span class="usage-mini-value">{{ usage.images_generated }}/{{ usage.images_limit }}</span>
                  <span class="usage-mini-label">{{ t('profile.images') }}</span>
                </div>
                <ProgressBar :value="imagesPercent" :showValue="false" class="usage-mini-bar" :style="{ '--p-progressbar-value-background': usageBarColor(imagesPercent) }" />
              </article>
              <article class="usage-mini-card">
                <div class="usage-mini-icon" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
                  <i class="pi pi-bolt" />
                </div>
                <div class="usage-mini-body">
                  <span class="usage-mini-value">{{ usage.credits_used }}/{{ usage.credits_limit }}</span>
                  <span class="usage-mini-label">{{ t('profile.credits') }}</span>
                </div>
                <ProgressBar :value="creditsPercent" :showValue="false" class="usage-mini-bar" :style="{ '--p-progressbar-value-background': usageBarColor(creditsPercent) }" />
              </article>
              <article class="usage-mini-card">
                <div class="usage-mini-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                  <i class="pi pi-code" />
                </div>
                <div class="usage-mini-body">
                  <span class="usage-mini-value">{{ usage.api_calls }}/{{ usage.api_limit }}</span>
                  <span class="usage-mini-label">{{ t('profile.apiCalls') }}</span>
                </div>
                <ProgressBar :value="apiPercent" :showValue="false" class="usage-mini-bar" :style="{ '--p-progressbar-value-background': usageBarColor(apiPercent) }" />
              </article>
              <article class="usage-mini-card">
                <div class="usage-mini-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                  <i class="pi pi-database" />
                </div>
                <div class="usage-mini-body">
                  <span class="usage-mini-value">{{ usage.storage_used_mb }}MB/{{ (usage.storage_limit_mb / 1024).toFixed(0) }}GB</span>
                  <span class="usage-mini-label">{{ t('profile.storage') }}</span>
                </div>
                <ProgressBar :value="storagePercent" :showValue="false" class="usage-mini-bar" :style="{ '--p-progressbar-value-background': usageBarColor(storagePercent) }" />
              </article>
            </div>

            <!-- Invoices -->
            <section class="section-card">
              <div class="section-head">
                <h2 class="section-title">{{ t('profile.billingHistory') }}</h2>
              </div>
              <div class="invoices-list">
                <div v-for="inv in invoices" :key="inv.id" class="invoice-row">
                  <div class="invoice-info">
                    <span class="invoice-number">{{ inv.number }}</span>
                    <span class="invoice-date">{{ formatDate(inv.date) }}</span>
                  </div>
                  <div class="invoice-end">
                    <span class="invoice-amount">${{ inv.amount }}</span>
                    <Tag :value="inv.status" :severity="invoiceSeverity(inv.status)" class="mini-tag" />
                    <Button icon="pi pi-download" severity="secondary" text rounded size="small" />
                  </div>
                </div>
              </div>
            </section>
          </div>
        </TabPanel>

        <!-- ═══ USAGE TAB ═══ -->
        <TabPanel value="usage">
          <div class="tab-content">
            <!-- Usage Meters -->
            <section class="section-card">
              <div class="section-head">
                <h2 class="section-title">{{ t('profile.currentUsage') }}</h2>
                <p class="section-desc">{{ t('profile.currentUsageDesc') }}</p>
              </div>
              <div class="meters-grid">
                <div class="meter-item" v-for="m in [
                  { label: t('profile.imagesGenerated'), used: usage.images_generated, limit: usage.images_limit, percent: imagesPercent, icon: 'pi pi-image', color: '#8b5cf6' },
                  { label: t('profile.creditsConsumed'), used: usage.credits_used, limit: usage.credits_limit, percent: creditsPercent, icon: 'pi pi-bolt', color: '#0ea5e9' },
                  { label: t('profile.apiRequests'), used: usage.api_calls, limit: usage.api_limit, percent: apiPercent, icon: 'pi pi-code', color: '#f59e0b' },
                  { label: t('profile.storageUsed'), used: `${usage.storage_used_mb}MB`, limit: `${(usage.storage_limit_mb / 1024).toFixed(0)}GB`, percent: storagePercent, icon: 'pi pi-database', color: '#10b981' },
                  { label: t('profile.upscales'), used: usage.upscales_used, limit: usage.upscales_limit, percent: upscalesPercent, icon: 'pi pi-expand', color: '#ec4899' },
                ]" :key="m.label">
                  <div class="meter-head">
                    <div class="meter-icon" :style="{ background: m.color + '18', color: m.color }">
                      <i :class="m.icon" />
                    </div>
                    <div class="meter-label-wrap">
                      <span class="meter-label">{{ m.label }}</span>
                      <span class="meter-count">{{ m.used }} / {{ m.limit }}</span>
                    </div>
                    <span class="meter-percent" :style="{ color: usageBarColor(m.percent) }">{{ m.percent }}%</span>
                  </div>
                  <ProgressBar :value="m.percent" :showValue="false" class="meter-bar" :style="{ '--p-progressbar-value-background': usageBarColor(m.percent) }" />
                </div>
              </div>
            </section>

            <!-- Usage chart -->
            <section class="section-card">
              <div class="section-head">
                <h2 class="section-title">{{ t('profile.usageTrend') }}</h2>
                <p class="section-desc">{{ t('profile.last7days') }}</p>
              </div>
              <div class="chart-shell">
                <Chart type="line" :data="usageChartData" :options="usageChartOptions" />
              </div>
            </section>

            <!-- Recent Activity -->
            <section class="section-card">
              <div class="section-head">
                <h2 class="section-title">{{ t('profile.recentActivity') }}</h2>
              </div>
              <div class="activity-list">
                <div v-for="item in recentActivity" :key="item.id" class="activity-row">
                  <div class="activity-icon" :style="{ background: actionColor(item.action) + '18', color: actionColor(item.action) }">
                    <i :class="actionIcon(item.action)" />
                  </div>
                  <div class="activity-info">
                    <span class="activity-desc">{{ item.description }}</span>
                    <span class="activity-date">{{ formatDateTime(item.date) }}</span>
                  </div>
                  <div class="activity-credits">
                    <i class="pi pi-bolt" />
                    <span>{{ item.credits }}</span>
                  </div>
                </div>
              </div>
            </section>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.profile-page {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 20px;
  max-width: 900px;
  margin: 0 auto;
  min-width: 0;
  overflow: hidden;
}

.page-header { margin-bottom: 4px; }
.page-title { font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.page-sub { font-size: 0.76rem; color: var(--text-muted); margin: 4px 0 0; }

/* ── Tabs ── */
:deep(.profile-tabs .p-tablist) { background: transparent; }
:deep(.profile-tabs .p-tab) {
  font-size: 0.74rem !important;
  padding: 8px 14px !important;
  color: var(--text-muted) !important;
  background: transparent !important;
  border: none !important;
  display: flex;
  align-items: center;
  gap: 6px;
}
:deep(.profile-tabs .p-tab-active) { color: var(--active-color) !important; }
:deep(.profile-tabs .p-tabpanels) { background: transparent; padding: 10px 0 0 !important; }

.tab-content {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* ── Section Card ── */
.section-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 12px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section-head { display: flex; flex-direction: column; gap: 2px; }
.section-title { margin: 0; font-size: 0.88rem; font-weight: 700; color: var(--text-primary); }
.section-desc { margin: 0; font-size: 0.7rem; color: var(--text-muted); }

/* ── Avatar row ── */
.avatar-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
  border-bottom: 1px solid var(--card-border);
}

.avatar-circle {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  font-weight: 700;
  flex-shrink: 0;
}

.avatar-info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.avatar-name { font-size: 0.88rem; font-weight: 600; color: var(--text-primary); }
.avatar-email { font-size: 0.7rem; color: var(--text-muted); }
.avatar-btn { flex-shrink: 0; }

/* ── Form grid ── */
.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}
@media (min-width: 640px) {
  .form-grid { grid-template-columns: 1fr 1fr; }
}

.form-field { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); }
.field-input { width: 100%; font-size: 0.8rem !important; }
.field-input-wrap { position: relative; width: 100%; }
.field-input-wrap > i:first-child {
  position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
  font-size: 0.78rem; color: var(--text-muted); z-index: 1; pointer-events: none;
}
.with-icon { padding-left: 34px !important; }
.has-toggle .field-input { padding-right: 36px !important; }
.toggle-pw {
  position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
  font-size: 0.82rem; color: var(--text-muted); cursor: pointer; z-index: 1;
}
.toggle-pw:hover { color: var(--text-secondary); }
.field-hint { font-size: 0.64rem; color: var(--text-muted); }
.field-hint.warn { color: #f59e0b; }

.form-actions { display: flex; gap: 8px; padding-top: 4px; }

.profile-form, .pw-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* ── Sessions ── */
.sessions-list { display: flex; flex-direction: column; gap: 8px; }

.session-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--hover-bg);
}

.session-icon {
  width: 34px; height: 34px; border-radius: 8px;
  background: var(--card-bg); display: flex; align-items: center; justify-content: center;
  color: var(--text-muted); font-size: 0.9rem; flex-shrink: 0;
}

.session-info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.session-device { font-size: 0.78rem; font-weight: 600; color: var(--text-primary); }
.session-meta { font-size: 0.64rem; color: var(--text-muted); }

/* ── Subscription hero ── */
.plan-hero { background: linear-gradient(135deg, var(--card-bg) 0%, color-mix(in srgb, var(--active-color) 5%, var(--card-bg)) 100%); }

.plan-hero-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.plan-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 8px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 0.72rem;
  font-weight: 700;
}

.plan-price-big {
  font-size: 2rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 8px 0 0;
  line-height: 1;
}
.plan-cycle { font-size: 0.82rem; font-weight: 500; color: var(--text-muted); }

.plan-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 8px;
}
.plan-period { font-size: 0.68rem; color: var(--text-muted); }

.plan-hero-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
  flex-wrap: wrap;
}

/* ── Usage summary grid ── */
.usage-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
@media (min-width: 768px) {
  .usage-summary-grid { grid-template-columns: repeat(4, 1fr); }
}

.usage-mini-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 10px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.usage-mini-icon {
  width: 30px; height: 30px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem;
}

.usage-mini-body { display: flex; flex-direction: column; }
.usage-mini-value { font-size: 0.88rem; font-weight: 700; color: var(--text-primary); }
.usage-mini-label { font-size: 0.62rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }

.usage-mini-bar { height: 4px !important; border-radius: 2px; }

/* ── Invoices ── */
.invoices-list { display: flex; flex-direction: column; gap: 6px; }

.invoice-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--hover-bg);
}

.invoice-info { display: flex; flex-direction: column; }
.invoice-number { font-size: 0.78rem; font-weight: 600; color: var(--text-primary); }
.invoice-date { font-size: 0.64rem; color: var(--text-muted); }
.invoice-end { display: flex; align-items: center; gap: 8px; }
.invoice-amount { font-size: 0.82rem; font-weight: 700; color: var(--text-primary); }

/* ── Meters ── */
.meters-grid { display: flex; flex-direction: column; gap: 14px; }

.meter-item { display: flex; flex-direction: column; gap: 6px; }
.meter-head { display: flex; align-items: center; gap: 10px; }
.meter-icon {
  width: 30px; height: 30px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.78rem; flex-shrink: 0;
}
.meter-label-wrap { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.meter-label { font-size: 0.74rem; font-weight: 600; color: var(--text-primary); }
.meter-count { font-size: 0.62rem; color: var(--text-muted); }
.meter-percent { font-size: 0.78rem; font-weight: 700; flex-shrink: 0; }
.meter-bar { height: 6px !important; border-radius: 3px; }

/* ── Chart ── */
.chart-shell { height: 240px; min-width: 0; overflow: hidden; position: relative; }

/* ── Activity ── */
.activity-list { display: flex; flex-direction: column; gap: 8px; }

.activity-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--hover-bg);
}

.activity-icon {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.78rem; flex-shrink: 0;
}

.activity-info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.activity-desc { font-size: 0.78rem; font-weight: 600; color: var(--text-primary); }
.activity-date { font-size: 0.62rem; color: var(--text-muted); }

.activity-credits {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-secondary);
  flex-shrink: 0;
}

.mini-tag { font-size: 0.58rem !important; padding: 2px 8px !important; }
</style>
