<script setup lang="ts">
import { ref, watch } from 'vue'
import { getCoupon, getCouponUsage, toggleCoupon, deleteCoupon, restoreCoupon } from '@/services/couponService'
import type { CouponDetail, CouponUsageStats } from '@/types/payments'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Chart from 'primevue/chart'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

const props = defineProps<{
  visible: boolean
  couponId: number | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void
  (e: 'updated'): void
  (e: 'edit', id: number): void
}>()

const loading = ref(false)
const actionLoading = ref(false)
const destructiveLoading = ref(false)

const detail = ref<CouponDetail | null>(null)
const usage = ref<CouponUsageStats | null>(null)

watch(
  () => props.visible,
  visible => {
    if (!visible || !props.couponId) {
      detail.value = null
      usage.value = null
      return
    }
    loadCoupon()
  },
)

async function loadCoupon() {
  if (!props.couponId) return
  loading.value = true
  try {
    const [couponRes, usageRes] = await Promise.all([
      getCoupon(props.couponId),
      getCouponUsage(props.couponId),
    ])
    detail.value = couponRes.data
    usage.value = usageRes.data
  } catch {
    detail.value = buildMockDetail(props.couponId)
    usage.value = buildMockUsage()
  } finally {
    loading.value = false
  }
}

async function handleToggle() {
  if (!detail.value) return
  actionLoading.value = true
  try {
    await toggleCoupon(detail.value.id)
    await loadCoupon()
    emit('updated')
  } catch { /* noop */ } finally {
    actionLoading.value = false
  }
}

async function handleDelete() {
  if (!detail.value) return
  destructiveLoading.value = true
  try {
    await deleteCoupon(detail.value.id)
    await loadCoupon()
    emit('updated')
  } catch { /* noop */ } finally {
    destructiveLoading.value = false
  }
}

async function handleRestore() {
  if (!detail.value) return
  destructiveLoading.value = true
  try {
    await restoreCoupon(detail.value.id)
    await loadCoupon()
    emit('updated')
  } catch { /* noop */ } finally {
    destructiveLoading.value = false
  }
}

function close() {
  emit('update:visible', false)
}

/* ─── Chart data ─── */
const usageChartData = ref<unknown>(null)
const usageChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#888' } },
    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { font: { size: 10 }, color: '#888' } },
  },
}

watch(usage, val => {
  if (!val) return
  usageChartData.value = {
    labels: val.daily_trend.map(d => d.date.slice(5)),
    datasets: [
      { label: 'Uses', data: val.daily_trend.map(d => d.uses), backgroundColor: 'rgba(14,165,233,0.7)', borderRadius: 4, barPercentage: 0.5 },
      { label: 'Discount', data: val.daily_trend.map(d => d.discount_total), backgroundColor: 'rgba(239,68,68,0.45)', borderRadius: 4, barPercentage: 0.5 },
    ],
  }
})

/* ─── Helpers ─── */
function discountLabel(coupon: CouponDetail) {
  if (coupon.discount_type === 'percentage') return `${coupon.discount_value}%`
  if (coupon.discount_type === 'credits') return `${coupon.discount_value} credits`
  return `$${coupon.discount_value}`
}

function statusSeverity(active: boolean, expired: boolean): 'success' | 'danger' | 'secondary' {
  if (!active) return 'secondary'
  if (expired) return 'danger'
  return 'success'
}

function statusLabel(active: boolean, expired: boolean) {
  if (!active) return 'Inactive'
  if (expired) return 'Expired'
  return 'Active'
}

function formatDate(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatDateTime(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatCurrency(value: number | undefined | null) {
  if (value == null) return '$0'
  return `$${value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function formatJson(value: Record<string, unknown> | null) {
  if (!value) return '—'
  return JSON.stringify(value, null, 2)
}

function usagePercent(coupon: CouponDetail) {
  if (!coupon.max_uses) return '∞'
  return `${coupon.usage_percentage?.toFixed(1) ?? 0}%`
}

/* ─── Mocks ─── */
function buildMockDetail(id: number): CouponDetail {
  return {
    id,
    code: 'SPRING10',
    name: 'Spring Promotion',
    discount_type: 'percentage',
    discount_value: 10,
    currency: 'USD',
    is_active: true,
    max_uses: 500,
    max_uses_per_user: 2,
    times_used: 148,
    usage_percentage: 29.6,
    starts_at: '2026-04-01T00:00:00Z',
    expires_at: '2026-04-30T23:59:00Z',
    is_expired: false,
    applicable_plan: { id: 3, name: 'Pro', slug: 'pro' },
    created_at: '2026-04-01T00:00:00Z',
    deleted_at: null,
    description: 'Seasonal incentive for Pro plan renewals and recovery flows.',
    min_order_amount: 19,
    metadata: { segment: 'reactivation', campaign: 'spring_2026' },
    updated_at: '2026-04-04T08:30:00Z',
    payments_count: 148,
    payments_total: 7252,
    discount_given_total: 712,
  }
}

function buildMockUsage(): CouponUsageStats {
  return {
    summary: { total_uses: 148, total_discount_given: 712, total_revenue: 7252, unique_users: 91, avg_discount: 4.81 },
    user_breakdown: [
      { id: 1, name: 'Sara Ahmed', email: 'sara@flash.io', uses: 2, total_discount: 9.8, total_amount: 98 },
      { id: 2, name: 'Omar Ali', email: 'omar@flash.io', uses: 1, total_discount: 4.9, total_amount: 49 },
      { id: 4, name: 'Karim Mostafa', email: 'karim@flash.io', uses: 2, total_discount: 9.8, total_amount: 98 },
      { id: 5, name: 'Layla Hassan', email: 'layla@flash.io', uses: 1, total_discount: 4.9, total_amount: 49 },
      { id: 6, name: 'Nour Sayed', email: 'nour@flash.io', uses: 1, total_discount: 4.9, total_amount: 49 },
    ],
    daily_trend: [
      { date: '2026-04-01', uses: 22, discount_total: 104 },
      { date: '2026-04-02', uses: 41, discount_total: 198 },
      { date: '2026-04-03', uses: 53, discount_total: 255 },
      { date: '2026-04-04', uses: 32, discount_total: 155 },
    ],
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="close"
    header="Coupon Detail"
    :modal="true"
    position="right"
    :style="{ width: '720px', maxWidth: '96vw', height: '100vh', margin: 0, borderRadius: 0 }"
    :draggable="false"
    class="coupon-detail-drawer"
  >
    <div v-if="loading" class="drawer-loading"><i class="pi pi-spin pi-spinner" /></div>

    <div v-else-if="detail" class="drawer-content">
      <!-- Hero -->
      <div class="hero-card">
        <div class="hero-row">
          <div class="hero-main">
            <div class="hero-title-row">
              <h2>{{ detail.code }}</h2>
              <Tag :value="statusLabel(detail.is_active, detail.is_expired)" :severity="statusSeverity(detail.is_active, detail.is_expired)" class="mini-tag" />
              <Tag :value="detail.discount_type" severity="info" class="mini-tag" />
            </div>
            <p class="hero-sub">{{ detail.name }} · {{ discountLabel(detail) }} off · {{ detail.applicable_plan?.name || 'All plans' }}</p>
            <p v-if="detail.description" class="hero-desc">{{ detail.description }}</p>
          </div>
          <div class="hero-actions">
            <Button :label="detail.is_active ? 'Deactivate' : 'Activate'" size="small" :severity="detail.is_active ? 'secondary' : 'success'" outlined :loading="actionLoading" @click="handleToggle" />
            <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" @click="emit('edit', detail.id)" />
            <Button v-if="detail.deleted_at" icon="pi pi-replay" severity="secondary" text rounded size="small" :loading="destructiveLoading" @click="handleRestore" />
            <Button v-else icon="pi pi-trash" severity="danger" text rounded size="small" :loading="destructiveLoading" @click="handleDelete" />
          </div>
        </div>

        <div class="stats-grid">
          <article class="stat-card">
            <span class="stat-k">Used</span>
            <strong>{{ detail.times_used }}</strong>
            <small>{{ usagePercent(detail) }} of limit</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Revenue</span>
            <strong>{{ formatCurrency(detail.payments_total) }}</strong>
            <small>{{ detail.payments_count }} payments</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Discount Given</span>
            <strong>{{ formatCurrency(detail.discount_given_total) }}</strong>
            <small>{{ discountLabel(detail) }} each</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Validity</span>
            <strong>{{ formatDate(detail.starts_at) }}</strong>
            <small>→ {{ formatDate(detail.expires_at) }}</small>
          </article>
        </div>
      </div>

      <!-- Tabs -->
      <Tabs value="overview" class="drawer-tabs">
        <TabList>
          <Tab value="overview">Overview</Tab>
          <Tab value="usage">Usage</Tab>
          <Tab value="payloads">Payloads</Tab>
        </TabList>
        <TabPanels>
          <!-- Overview -->
          <TabPanel value="overview">
            <section class="info-card">
              <h3 class="section-title">Configuration</h3>
              <div class="meta-list">
                <div class="meta-row"><span>Discount Type</span><span>{{ detail.discount_type }}</span></div>
                <div class="meta-row"><span>Discount Value</span><span>{{ discountLabel(detail) }}</span></div>
                <div class="meta-row"><span>Currency</span><span>{{ detail.currency }}</span></div>
                <div class="meta-row"><span>Min Order</span><span>{{ detail.min_order_amount ? formatCurrency(detail.min_order_amount) : '—' }}</span></div>
                <div class="meta-row"><span>Max Uses</span><span>{{ detail.max_uses ?? '∞' }}</span></div>
                <div class="meta-row"><span>Max Uses / User</span><span>{{ detail.max_uses_per_user ?? '∞' }}</span></div>
                <div class="meta-row"><span>Plan</span><span>{{ detail.applicable_plan?.name || 'All' }}</span></div>
                <div class="meta-row"><span>Created</span><span>{{ formatDateTime(detail.created_at) }}</span></div>
                <div class="meta-row"><span>Updated</span><span>{{ formatDateTime(detail.updated_at) }}</span></div>
              </div>
            </section>
          </TabPanel>

          <!-- Usage -->
          <TabPanel value="usage">
            <div v-if="usage" class="usage-content">
              <div class="summary-grid">
                <article class="stat-card">
                  <span class="stat-k">Total Uses</span>
                  <strong>{{ usage.summary.total_uses }}</strong>
                </article>
                <article class="stat-card">
                  <span class="stat-k">Unique Users</span>
                  <strong>{{ usage.summary.unique_users }}</strong>
                </article>
                <article class="stat-card">
                  <span class="stat-k">Revenue</span>
                  <strong>{{ formatCurrency(usage.summary.total_revenue) }}</strong>
                </article>
                <article class="stat-card">
                  <span class="stat-k">Avg Discount</span>
                  <strong>{{ formatCurrency(usage.summary.avg_discount) }}</strong>
                </article>
              </div>

              <section v-if="usageChartData" class="chart-section">
                <h3 class="section-title">Daily Trend</h3>
                <div class="chart-wrap"><Chart type="bar" :data="usageChartData" :options="usageChartOptions" /></div>
              </section>

              <section class="info-card">
                <h3 class="section-title">User Breakdown</h3>
                <!-- Desktop -->
                <div class="hidden md:block">
                  <DataTable :value="usage.user_breakdown" size="small" stripedRows class="compact-table">
                    <Column field="name" header="User" style="min-width: 120px">
                      <template #body="{ data }">
                        <div class="cell-stack">
                          <span class="cell-primary">{{ data.name }}</span>
                          <span class="cell-sub">{{ data.email }}</span>
                        </div>
                      </template>
                    </Column>
                    <Column field="uses" header="Uses" style="width: 60px" />
                    <Column header="Discount" style="width: 90px">
                      <template #body="{ data }">{{ formatCurrency(data.total_discount) }}</template>
                    </Column>
                    <Column header="Amount" style="width: 90px">
                      <template #body="{ data }">{{ formatCurrency(data.total_amount) }}</template>
                    </Column>
                  </DataTable>
                </div>
                <!-- Mobile -->
                <div class="md:hidden mobile-cards">
                  <article v-for="u in usage.user_breakdown" :key="u.id" class="mobile-card">
                    <div class="mc-header">
                      <span class="mc-title">{{ u.name }}</span>
                      <span class="mc-badge">{{ u.uses }} uses</span>
                    </div>
                    <p class="mc-sub">{{ u.email }}</p>
                    <div class="mc-row"><span>Discount</span><span>{{ formatCurrency(u.total_discount) }}</span></div>
                    <div class="mc-row"><span>Amount</span><span>{{ formatCurrency(u.total_amount) }}</span></div>
                  </article>
                </div>
              </section>
            </div>
            <div v-else class="empty-state">No usage data available</div>
          </TabPanel>

          <!-- Payloads -->
          <TabPanel value="payloads">
            <section class="payload-card">
              <h3 class="section-title">Metadata</h3>
              <pre>{{ formatJson(detail.metadata) }}</pre>
            </section>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </div>
  </Dialog>
</template>

<style scoped>
.drawer-loading { display: flex; align-items: center; justify-content: center; height: 220px; color: var(--text-muted); font-size: 1.2rem; }
.drawer-content { display: flex; flex-direction: column; gap: 14px; }
.hero-card { display: flex; flex-direction: column; gap: 12px; padding: 12px; border: 1px solid var(--card-border); border-radius: 12px; background: var(--card-bg); }
.hero-row { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.hero-main { min-width: 0; flex: 1; }
.hero-title-row { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.hero-title-row h2 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary); font-family: monospace; }
.hero-sub { margin: 4px 0 0; font-size: 0.64rem; color: var(--text-muted); }
.hero-desc { margin: 8px 0 0; font-size: 0.74rem; line-height: 1.45; color: var(--text-primary); }
.hero-actions { display: flex; gap: 6px; align-items: flex-start; flex-wrap: wrap; justify-content: flex-end; }
.stats-grid,.summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
@media (min-width: 640px) { .stats-grid,.summary-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.stat-card { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border-radius: 10px; background: var(--hover-bg); }
.stat-k { font-size: 0.58rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
.stat-card strong { font-size: 0.82rem; color: var(--text-primary); }
.stat-card small { font-size: 0.62rem; color: var(--text-muted); }
:deep(.drawer-tabs .p-tablist) { background: transparent; }
:deep(.drawer-tabs .p-tab) { font-size: 0.7rem !important; padding: 6px 10px !important; color: var(--text-muted) !important; background: transparent !important; border: none !important; }
:deep(.drawer-tabs .p-tab-active) { color: var(--active-color) !important; }
:deep(.drawer-tabs .p-tabpanels) { background: transparent; padding: 10px 0 0 !important; }
.info-card,.payload-card { border: 1px solid var(--card-border); border-radius: 10px; background: var(--card-bg); padding: 10px; }
.section-title { margin: 0 0 8px; font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
.meta-list { display: flex; flex-direction: column; gap: 0; border: 1px solid var(--card-border); border-radius: 8px; overflow: hidden; }
.meta-row { display: flex; justify-content: space-between; gap: 10px; padding: 7px 9px; border-bottom: 1px solid var(--card-border); font-size: 0.66rem; }
.meta-row:last-child { border-bottom: none; }
.meta-row span:first-child { color: var(--text-muted); }
.meta-row span:last-child { color: var(--text-primary); text-align: right; }
.payload-card pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-size: 0.65rem; color: var(--text-primary); }
.chart-section { border: 1px solid var(--card-border); border-radius: 10px; background: var(--card-bg); padding: 10px; margin-bottom: 10px; }
.chart-wrap { height: 180px; }
.usage-content { display: flex; flex-direction: column; gap: 10px; }
.mini-tag { font-size: 0.56rem !important; padding: 2px 7px !important; }
.empty-state { text-align: center; padding: 40px 0; color: var(--text-muted); font-size: 0.78rem; }
.compact-table { font-size: 0.72rem; }
.cell-stack { display: flex; flex-direction: column; gap: 1px; }
.cell-primary { font-weight: 600; font-size: 0.72rem; color: var(--text-primary); }
.cell-sub { font-size: 0.62rem; color: var(--text-muted); }
.mobile-cards { display: flex; flex-direction: column; gap: 8px; }
.mobile-card { padding: 10px; border: 1px solid var(--card-border); border-radius: 10px; background: var(--card-bg); }
.mc-header { display: flex; justify-content: space-between; align-items: center; }
.mc-title { font-weight: 600; font-size: 0.74rem; color: var(--text-primary); }
.mc-badge { font-size: 0.6rem; padding: 2px 6px; border-radius: 999px; background: var(--hover-bg); color: var(--text-secondary); }
.mc-sub { margin: 2px 0 6px; font-size: 0.62rem; color: var(--text-muted); }
.mc-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 0.66rem; border-top: 1px solid var(--card-border); }
.mc-row span:first-child { color: var(--text-muted); }
.mc-row span:last-child { color: var(--text-primary); }
:deep(.coupon-detail-drawer) { margin: 0 !important; border-radius: 0 !important; }
:deep(.coupon-detail-drawer .p-dialog-header) { background: var(--card-bg); border-color: var(--card-border); color: var(--text-primary); padding: 10px 16px; }
:deep(.coupon-detail-drawer .p-dialog-content) { background: var(--card-bg); color: var(--text-primary); padding: 12px 16px; overflow-y: auto; }
</style>
