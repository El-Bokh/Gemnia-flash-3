<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import ProgressBar from 'primevue/progressbar'
import { getUser, resetPassword } from '@/services/userService'
import type { UserDetail, ResetPasswordPayload } from '@/types/users'

const props = defineProps<{
  visible: boolean
  userId: number | null
}>()
const emit = defineEmits<{ (e: 'update:visible', val: boolean): void }>()
const { t } = useI18n()

const loading = ref(false)
const user = ref<UserDetail | null>(null)
const expandedImage = ref<string | null>(null)

// Reset password fields
const showResetPw = ref(false)
const rpForm = ref({ password: '', confirm: '', revoke: true })
const rpSaving = ref(false)

// Load user detail
watch(() => props.visible, async (open) => {
  if (!open || !props.userId) { user.value = null; return }
  loading.value = true
  try {
    const res = await getUser(props.userId)
    user.value = res.data
  } catch {
    loadMockDetail()
  } finally {
    loading.value = false
  }
})

function loadMockDetail() {
  user.value = {
    id: props.userId!,
    name: 'Sara Ahmed',
    email: 'sara.ahmed@klek.ai',
    phone: '+20 1000000000',
    avatar: null,
    status: 'active',
    locale: 'en',
    timezone: 'UTC',
    email_verified_at: '2026-03-10T10:00:00Z',
    last_login_at: '2026-04-03T14:21:00Z',
    last_login_ip: '192.168.1.15',
    created_at: '2026-01-15T09:00:00Z',
    updated_at: '2026-04-03T14:21:00Z',
    roles: [{ id: 1, name: 'Admin', slug: 'admin' }],
    subscriptions: [{
      id: 101, plan: { id: 3, name: 'Pro', slug: 'pro' }, billing_cycle: 'monthly', status: 'active',
      price: 49, currency: 'USD', credits_remaining: 320, credits_total: 500,
      starts_at: '2026-03-01T00:00:00Z', ends_at: '2026-04-01T00:00:00Z',
      trial_starts_at: null, trial_ends_at: null, cancelled_at: null, auto_renew: true, created_at: '2026-03-01T00:00:00Z',
    }],
    stats: {
      ai_requests_total: 145, ai_requests_completed: 138, ai_requests_failed: 4, ai_requests_pending: 3,
      generated_images: 87, total_payments: 147, payments_count: 3, credit_balance: 320,
    },
    recent_ai_requests: [
      { id: 1, uuid: 'ai-001', type: 'text-to-image', status: 'completed', prompt: 'A sunset over mountains', style: 'Realistic', model: 'DALL-E 3', credits: 5, created_at: '2026-04-03T14:00:00Z' },
      { id: 2, uuid: 'ai-002', type: 'text-to-image', status: 'completed', prompt: 'Cyberpunk city at night', style: 'Anime', model: 'Midjourney', credits: 8, created_at: '2026-04-03T12:30:00Z' },
      { id: 3, uuid: 'ai-003', type: 'text-to-image', status: 'failed', prompt: 'Abstract fractal art', style: null, model: 'DALL-E 3', credits: 0, created_at: '2026-04-02T18:00:00Z' },
    ],
    recent_generated_images: [
      { id: 1, uuid: 'img-001', file_path: '/images/sunset.png', file_name: 'sunset.png', width: 1024, height: 1024, file_size: 245000, is_public: true, is_nsfw: false, created_at: '2026-04-03T14:01:00Z' },
      { id: 2, uuid: 'img-002', file_path: '/images/cyberpunk.png', file_name: 'cyberpunk.png', width: 1024, height: 1024, file_size: 380000, is_public: false, is_nsfw: false, created_at: '2026-04-03T12:31:00Z' },
    ],
    recent_payments: [
      { id: 1, uuid: 'pay-001', amount: 49, net_amount: 46.5, currency: 'USD', status: 'completed', method: 'stripe', paid_at: '2026-03-01T09:00:00Z', created_at: '2026-03-01T09:00:00Z' },
      { id: 2, uuid: 'pay-002', amount: 49, net_amount: 46.5, currency: 'USD', status: 'completed', method: 'stripe', paid_at: '2026-02-01T09:00:00Z', created_at: '2026-02-01T09:00:00Z' },
    ],
    credit_ledger: {
      balance: 320,
      recent: [
        { id: 1, type: 'debit', amount: -5, balance_after: 320, source: 'ai_request', description: 'Text-to-image generation', created_at: '2026-04-03T14:00:00Z' },
        { id: 2, type: 'debit', amount: -8, balance_after: 325, source: 'ai_request', description: 'Text-to-image generation', created_at: '2026-04-03T12:30:00Z' },
        { id: 3, type: 'credit', amount: 500, balance_after: 333, source: 'subscription', description: 'Monthly credit allocation', created_at: '2026-03-01T00:00:00Z' },
      ],
    },
  }
}

async function handleResetPassword() {
  if (!user.value) return
  rpSaving.value = true
  try {
    const payload: ResetPasswordPayload = {
      password: rpForm.value.password,
      password_confirmation: rpForm.value.confirm,
      revoke_tokens: rpForm.value.revoke,
    }
    await resetPassword(user.value.id, payload)
    showResetPw.value = false
    rpForm.value = { password: '', confirm: '', revoke: true }
  } catch { /* handled */ }
  finally { rpSaving.value = false }
}

function close() { emit('update:visible', false) }

// Helpers
function statusSev(s: string): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  return { active: 'success' as const, suspended: 'warn' as const, banned: 'danger' as const, pending: 'info' as const, completed: 'success' as const, failed: 'danger' as const }[s] || 'secondary'
}

// Merge generated_images + output images from media_files
const allImages = computed(() => {
  if (!user.value) return []
  const legacy = (user.value.recent_generated_images || []).map((img: any) => ({
    id: img.id,
    file_name: img.file_name,
    file_path: img.file_path,
    width: img.width,
    height: img.height,
    file_size: img.file_size,
    created_at: img.created_at,
  }))
  const output = (user.value.recent_output_images || []).map((img: any) => ({
    id: `m-${img.id}`,
    file_name: img.file_name,
    file_path: img.file_path,
    width: null,
    height: null,
    file_size: img.file_size,
    created_at: img.created_at,
  }))
  return [...legacy, ...output]
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
    .slice(0, 10)
})

function resolveImageUrl(path: string | null | undefined): string | undefined {
  if (!path) return undefined
  if (path.startsWith('http')) return path
  const apiBase = import.meta.env.VITE_API_BASE_URL || ''
  const origin = apiBase.replace(/\/api\/?$/, '')
  // file_path from media_files is like "ai-generated/1/uuid.png", need /storage/ prefix
  if (!path.startsWith('/')) return `${origin}/storage/${path}`
  return origin + path
}
function fmtDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function fmtBytes(b: number) {
  if (b < 1024) return b + ' B'
  return (b / 1024).toFixed(0) + ' KB'
}
function initials(name: string) {
  return name.split(' ').map(n => n[0]).join('').substring(0, 2)
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="close"
    :header="t('userDetail.title')"
    :modal="true"
    position="right"
    :style="{ width: '580px', maxWidth: '95vw', height: '100vh', margin: 0, borderRadius: 0 }"
    :draggable="false"
    class="detail-drawer"
  >
    <!-- Loading -->
    <div v-if="loading" class="drawer-loading">
      <i class="pi pi-spin pi-spinner" style="font-size: 1.4rem; color: var(--text-muted)" />
    </div>

    <div v-else-if="user" class="drawer-content">
      <!-- Profile Header -->
      <div class="profile-header">
        <div class="p-avatar-lg">
          <img v-if="user.avatar" :src="user.avatar" :alt="user.name" />
          <span v-else>{{ initials(user.name) }}</span>
        </div>
        <div class="p-info">
          <h2>{{ user.name }}</h2>
          <p>{{ user.email }}</p>
          <div class="p-tags">
            <Tag :value="user.status" :severity="statusSev(user.status)" />
            <Tag v-for="r in user.roles" :key="r.id" :value="r.name" severity="info" />
          </div>
        </div>
        <Button icon="pi pi-key" severity="warn" text rounded size="small" @click="showResetPw = !showResetPw" v-tooltip.left="t('userDetail.resetPassword')" />
      </div>

      <!-- Reset Password Mini-form -->
      <div v-if="showResetPw" class="reset-pw-box">
        <div style="display: flex; gap: 6px">
          <input v-model="rpForm.password" type="password" :placeholder="t('userDetail.newPassword')" class="mini-input" />
          <input v-model="rpForm.confirm" type="password" :placeholder="t('userDetail.confirmPassword')" class="mini-input" />
        </div>
        <div style="display: flex; align-items: center; gap: 6px; margin-top: 6px">
          <label style="font-size: 0.66rem; color: var(--text-muted)">
            <input type="checkbox" v-model="rpForm.revoke" /> {{ t('userDetail.revokeTokens') }}
          </label>
          <Button :label="t('userDetail.reset')" size="small" severity="warn" :loading="rpSaving" @click="handleResetPassword" style="margin-left: auto" />
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="stats-bar">
        <div class="stat-item">
          <span class="stat-label">{{ t('userDetail.aiRequests') }}</span>
          <span class="stat-val">{{ user.stats.ai_requests_total }}</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">{{ t('userDetail.images') }}</span>
          <span class="stat-val">{{ user.stats.generated_images }}</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">{{ t('userDetail.payments') }}</span>
          <span class="stat-val">${{ user.stats.total_payments }}</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">{{ t('userDetail.credits') }}</span>
          <span class="stat-val">{{ user.stats.credit_balance }}</span>
        </div>
      </div>

      <!-- Subscription -->
      <div v-if="user.subscriptions.length" class="section">
        <h3 class="section-title">{{ t('userDetail.activeSubscription') }}</h3>
        <div class="sub-card" v-for="sub in user.subscriptions.slice(0, 2)" :key="sub.id">
          <div class="sub-row">
            <span class="sub-plan">{{ sub.plan?.name || 'Unknown' }}</span>
            <Tag :value="sub.status" :severity="statusSev(sub.status)" />
          </div>
          <div class="sub-row sub-meta">
            <span>${{ sub.price }}/{{ sub.billing_cycle }}</span>
            <span>{{ sub.credits_remaining }}/{{ sub.credits_total }} credits</span>
          </div>
          <ProgressBar :value="Math.round((sub.credits_remaining / Math.max(sub.credits_total, 1)) * 100)" :showValue="false" style="height: 4px" />
          <div class="sub-row sub-dates">
            <span>{{ fmtDate(sub.starts_at) }}</span>
            <span>→ {{ fmtDate(sub.ends_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Tabs: AI Requests, Images, Payments, Credits -->
      <Tabs value="ai" class="detail-tabs">
        <TabList>
          <Tab value="ai">{{ t('userDetail.aiRequests') }}</Tab>
          <Tab value="images">{{ t('userDetail.images') }}</Tab>
          <Tab value="payments">{{ t('userDetail.payments') }}</Tab>
          <Tab value="credits">{{ t('userDetail.credits') }}</Tab>
        </TabList>
        <TabPanels>
        <!-- AI Requests -->
        <TabPanel value="ai">
          <DataTable :value="user.recent_ai_requests" size="small" stripedRows class="detail-table">
            <Column field="type" :header="t('common.type')" style="min-width: 80px">
              <template #body="{ data }">
                <span class="dt-text">{{ data.type }}</span>
              </template>
            </Column>
            <Column field="status" :header="t('common.status')" style="min-width: 60px">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="statusSev(data.status)" style="font-size: 0.58rem" />
              </template>
            </Column>
            <Column field="model" :header="t('common.model')" style="min-width: 70px">
              <template #body="{ data }"><span class="dt-text">{{ data.model }}</span></template>
            </Column>
            <Column field="credits" :header="t('userDetail.cr')" style="min-width: 30px">
              <template #body="{ data }"><span class="dt-num">{{ data.credits }}</span></template>
            </Column>
            <Column field="created_at" :header="t('common.date')" style="min-width: 80px">
              <template #body="{ data }"><span class="dt-date">{{ fmtDate(data.created_at) }}</span></template>
            </Column>
          </DataTable>
        </TabPanel>

        <!-- Images -->
        <TabPanel value="images">
          <div v-if="allImages.length" class="user-images-grid">
            <div
              v-for="img in allImages"
              :key="img.id"
              class="user-image-card"
              @click="expandedImage = resolveImageUrl(img.file_path) || null"
            >
              <div class="user-image-thumb">
                <img :src="resolveImageUrl(img.file_path)" :alt="img.file_name" loading="lazy" />
              </div>
              <div class="user-image-info">
                <span class="user-image-size">{{ fmtBytes(img.file_size) }}</span>
                <span class="user-image-date">{{ fmtDate(img.created_at) }}</span>
              </div>
            </div>
          </div>
          <div v-else class="empty-tab">
            <i class="pi pi-image" style="font-size: 1.5rem; color: var(--text-muted)" />
            <span style="font-size: 0.72rem; color: var(--text-muted)">No images generated yet</span>
          </div>
        </TabPanel>

        <!-- Payments -->
        <TabPanel value="payments">
          <DataTable :value="user.recent_payments" size="small" stripedRows class="detail-table">
            <Column field="amount" :header="t('common.amount')" style="min-width: 60px">
              <template #body="{ data }"><span class="dt-num">${{ data.amount }}</span></template>
            </Column>
            <Column field="status" :header="t('common.status')" style="min-width: 60px">
              <template #body="{ data }"><Tag :value="data.status" :severity="statusSev(data.status)" style="font-size: 0.58rem" /></template>
            </Column>
            <Column field="method" :header="t('common.method')" style="min-width: 50px">
              <template #body="{ data }"><span class="dt-text">{{ data.method }}</span></template>
            </Column>
            <Column field="paid_at" :header="t('common.date')" style="min-width: 80px">
              <template #body="{ data }"><span class="dt-date">{{ fmtDate(data.paid_at) }}</span></template>
            </Column>
          </DataTable>
        </TabPanel>

        <!-- Credits -->
        <TabPanel value="credits">
          <div class="credit-balance">{{ t('userDetail.balance') }}: <strong>{{ user.credit_ledger.balance }}</strong></div>
          <DataTable :value="user.credit_ledger.recent" size="small" stripedRows class="detail-table">
            <Column field="type" :header="t('common.type')" style="min-width: 50px">
              <template #body="{ data }">
                <Tag :value="data.type" :severity="data.type === 'credit' ? 'success' : 'danger'" style="font-size: 0.58rem" />
              </template>
            </Column>
            <Column field="amount" :header="t('common.amount')" style="min-width: 50px">
              <template #body="{ data }">
                <span :class="data.amount > 0 ? 'dt-credit' : 'dt-debit'">{{ data.amount > 0 ? '+' : '' }}{{ data.amount }}</span>
              </template>
            </Column>
            <Column field="source" :header="t('userDetail.source')" style="min-width: 70px">
              <template #body="{ data }"><span class="dt-text">{{ data.source }}</span></template>
            </Column>
            <Column field="created_at" :header="t('common.date')" style="min-width: 80px">
              <template #body="{ data }"><span class="dt-date">{{ fmtDate(data.created_at) }}</span></template>
            </Column>
          </DataTable>
        </TabPanel>
      </TabPanels>
      </Tabs>

      <!-- Meta -->
      <div class="meta-section">
        <div class="meta-row"><span>{{ t('userDetail.phone') }}</span><span>{{ user.phone || '—' }}</span></div>
        <div class="meta-row"><span>{{ t('userDetail.locale') }}</span><span>{{ user.locale || '—' }}</span></div>
        <div class="meta-row"><span>{{ t('userDetail.timezone') }}</span><span>{{ user.timezone || '—' }}</span></div>
        <div class="meta-row"><span>{{ t('userDetail.emailVerified') }}</span><span>{{ fmtDate(user.email_verified_at) }}</span></div>
        <div class="meta-row"><span>{{ t('userDetail.lastLogin') }}</span><span>{{ fmtDate(user.last_login_at) }}</span></div>
        <div class="meta-row"><span>{{ t('userDetail.lastIp') }}</span><span>{{ user.last_login_ip || '—' }}</span></div>
        <div class="meta-row"><span>{{ t('userDetail.joined') }}</span><span>{{ fmtDate(user.created_at) }}</span></div>
      </div>
    </div>
  </Dialog>

  <!-- Fullscreen image overlay -->
  <Teleport to="body">
    <div v-if="expandedImage" class="image-overlay" @click="expandedImage = null">
      <img :src="expandedImage" alt="Expanded image" />
      <button class="overlay-close" @click.stop="expandedImage = null">
        <i class="pi pi-times" />
      </button>
    </div>
  </Teleport>
</template>

<style scoped>
.drawer-loading {
  display: flex; align-items: center; justify-content: center;
  height: 200px;
}
.drawer-content {
  display: flex; flex-direction: column; gap: 14px;
}

/* Profile */
.profile-header { display: flex; align-items: flex-start; gap: 12px; }
.p-avatar-lg {
  width: 48px; height: 48px; border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff; font-size: 1rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; overflow: hidden;
}
.p-avatar-lg img { width: 100%; height: 100%; object-fit: cover; }
.p-info { flex: 1; min-width: 0; }
.p-info h2 { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.p-info p { font-size: 0.72rem; color: var(--text-muted); margin: 2px 0 6px; }
.p-tags { display: flex; gap: 4px; flex-wrap: wrap; }

/* Reset Password */
.reset-pw-box {
  padding: 8px 10px; border-radius: 8px;
  background: color-mix(in srgb, var(--card-bg) 50%, var(--hover-bg) 50%);
  border: 1px solid var(--card-border);
}
.mini-input {
  flex: 1; min-width: 0; padding: 5px 8px; font-size: 0.72rem;
  border: 1px solid var(--card-border); border-radius: 6px;
  background: var(--card-bg); color: var(--text-primary);
  outline: none;
}
.mini-input:focus { border-color: var(--active-color); }

/* Stats */
.stats-bar {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;
}
.stat-item {
  display: flex; flex-direction: column; align-items: center;
  padding: 8px 4px; border-radius: 8px;
  background: var(--hover-bg); border: 1px solid var(--card-border);
}
.stat-label { font-size: 0.6rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.stat-val { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-top: 2px; }

/* Subscription */
.section { display: flex; flex-direction: column; gap: 6px; }
.section-title { font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
.sub-card {
  padding: 8px 10px; border-radius: 8px;
  border: 1px solid var(--card-border); background: var(--card-bg);
  display: flex; flex-direction: column; gap: 4px;
}
.sub-row { display: flex; align-items: center; justify-content: space-between; }
.sub-plan { font-size: 0.8rem; font-weight: 700; color: var(--active-color); }
.sub-meta { font-size: 0.66rem; color: var(--text-muted); }
.sub-dates { font-size: 0.62rem; color: var(--text-muted); }

/* Tabs */
:deep(.detail-tabs .p-tablist) {
  background: transparent;
}
:deep(.detail-tabs .p-tab) {
  font-size: 0.68rem !important;
  padding: 6px 10px !important;
  color: var(--text-muted) !important;
  background: transparent !important;
  border: none !important;
}
:deep(.detail-tabs .p-tab-active) {
  color: var(--active-color) !important;
}
:deep(.detail-tabs .p-tabpanels) {
  background: transparent;
  padding: 8px 0 !important;
}

/* Detail tables */
:deep(.detail-table .p-datatable-thead > tr > th) {
  background: var(--hover-bg); color: var(--text-muted);
  border-color: var(--card-border); font-size: 0.6rem;
  font-weight: 700; text-transform: uppercase; padding: 6px 8px;
}
:deep(.detail-table .p-datatable-tbody > tr) {
  background: var(--card-bg); color: var(--text-primary);
}
:deep(.detail-table .p-datatable-tbody > tr > td) {
  background: transparent; color: var(--text-primary);
  border-color: var(--card-border); padding: 5px 8px;
}
:deep(.detail-table .p-datatable-tbody > tr:hover) {
  background: var(--hover-bg);
}

.dt-text { font-size: 0.68rem; color: var(--text-primary); }
.dt-num { font-size: 0.72rem; font-weight: 600; color: var(--text-primary); }
.dt-date { font-size: 0.62rem; color: var(--text-muted); }
.dt-credit { font-size: 0.72rem; font-weight: 600; color: #22c55e; }
.dt-debit { font-size: 0.72rem; font-weight: 600; color: #ef4444; }
.credit-balance { font-size: 0.72rem; color: var(--text-secondary); margin-bottom: 6px; }
.credit-balance strong { color: var(--text-primary); font-size: 0.9rem; }

/* Meta */
.meta-section {
  display: flex; flex-direction: column; gap: 0;
  border: 1px solid var(--card-border); border-radius: 8px; overflow: hidden;
}
.meta-row {
  display: flex; justify-content: space-between; padding: 6px 10px;
  font-size: 0.68rem; border-bottom: 1px solid var(--card-border);
}
.meta-row:last-child { border-bottom: none; }
.meta-row span:first-child { color: var(--text-muted); font-weight: 500; }
.meta-row span:last-child { color: var(--text-primary); font-weight: 500; }

/* Dialog override as drawer */
:deep(.detail-drawer) {
  margin: 0 !important;
  border-radius: 0 !important;
}
:deep(.detail-drawer .p-dialog-header) {
  background: var(--card-bg);
  border-color: var(--card-border);
  color: var(--text-primary);
  padding: 10px 16px;
}
:deep(.detail-drawer .p-dialog-content) {
  background: var(--card-bg);
  color: var(--text-primary);
  padding: 12px 16px;
  overflow-y: auto;
}

/* User Images Grid */
.user-images-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.user-image-card {
  border: 1px solid var(--card-border);
  border-radius: 10px;
  overflow: hidden;
  background: var(--card-bg);
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.user-image-card:hover {
  border-color: var(--active-color);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--active-color) 25%, transparent);
}
.user-image-thumb {
  aspect-ratio: 1 / 1;
  background: linear-gradient(135deg, rgba(14,165,233,0.12), rgba(37,99,235,0.08));
  overflow: hidden;
}
.user-image-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.user-image-info {
  display: flex;
  justify-content: space-between;
  padding: 5px 7px;
  gap: 4px;
}
.user-image-size {
  font-size: 0.6rem;
  font-weight: 600;
  color: var(--text-primary);
}
.user-image-date {
  font-size: 0.58rem;
  color: var(--text-muted);
}
.empty-tab {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 32px 0;
}

/* Fullscreen overlay */
.image-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(0, 0, 0, 0.88);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: zoom-out;
}
.image-overlay img {
  max-width: 92vw;
  max-height: 92vh;
  object-fit: contain;
  border-radius: 8px;
}
.overlay-close {
  position: absolute;
  top: 16px;
  right: 16px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  font-size: 1rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.overlay-close:hover {
  background: rgba(255, 255, 255, 0.3);
}
</style>
