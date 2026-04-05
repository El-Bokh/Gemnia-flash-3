<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { getFullOverview } from '@/services/dashboardService'
import type {
  DashboardOverview,
  DashboardKpis,
  DashboardCharts,
  RecentAiRequest,
  RecentPayment,
  DashboardAlerts,
} from '@/types/dashboard'
import Chart from 'primevue/chart'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Skeleton from 'primevue/skeleton'
import ProgressBar from 'primevue/progressbar'

const { t } = useI18n()

const loading = ref(true)
const kpis = ref<DashboardKpis | null>(null)
const charts = ref<DashboardCharts | null>(null)
const recentAi = ref<RecentAiRequest[]>([])
const recentPayments = ref<RecentPayment[]>([])
const alerts = ref<DashboardAlerts | null>(null)

onMounted(async () => {
  try {
    const res = await getFullOverview()
    if (res.success) {
      const d = res.data
      kpis.value = d.kpis
      charts.value = d.charts
      recentAi.value = d.recent_ai_requests
      recentPayments.value = d.recent_payments
      alerts.value = d.alerts
    }
  } catch {
    // API unavailable
  } finally {
    loading.value = false
  }
})

// ── KPI Cards ───────────────────────────────────────────────
const kpiCards = computed(() => {
  const k = kpis.value
  if (!k) return []
  return [
    { label: t('dashboard.totalUsers'), value: k.users.total, sub: `+${k.users.new_today} ${t('common.today')}`, icon: 'pi pi-users', color: '#6366f1' },
    { label: t('dashboard.activeUsers'), value: k.users.active, sub: `${k.users.suspended} ${t('common.suspended')}`, icon: 'pi pi-user-plus', color: '#10b981' },
    { label: t('dashboard.aiCompleted'), value: k.ai_requests_completed, sub: `${k.ai_requests_pending} ${t('common.pending')}`, icon: 'pi pi-microchip-ai', color: '#8b5cf6' },
    { label: t('dashboard.aiFailed'), value: k.ai_requests_failed, sub: t('common.requests'), icon: 'pi pi-exclamation-triangle', color: '#ef4444' },
    { label: t('dashboard.imagesToday'), value: k.images_generated_today, sub: `${k.images_generated_week} ${t('common.thisWeek')}`, icon: 'pi pi-image', color: '#f59e0b' },
    { label: t('dashboard.revenueToday'), value: `$${k.revenue_today.total.toLocaleString()}`, sub: `${k.revenue_today.count} ${t('common.txns')}`, icon: 'pi pi-dollar', color: '#10b981' },
    { label: t('dashboard.revenueWeek'), value: `$${k.revenue_week.total.toLocaleString()}`, sub: `${k.revenue_week.count} ${t('common.txns')}`, icon: 'pi pi-chart-line', color: '#06b6d4' },
    { label: t('dashboard.pendingUsers'), value: k.users.pending, sub: t('common.awaitingApproval'), icon: 'pi pi-clock', color: '#f97316' },
  ]
})

// ── Chart Data ──────────────────────────────────────────────
const cssText = getComputedStyle(document.documentElement)
const gridColor = () => cssText.getPropertyValue('--card-border').trim() || '#e2e8f0'
const textMuted = () => cssText.getPropertyValue('--text-muted').trim() || '#94a3b8'

const lineChartData = computed(() => {
  if (!charts.value) return null
  const items = charts.value.images_last_7_days
  return {
    labels: items.map(i => i.date),
    datasets: [{
      label: t('dashboard.imagesGenerated'),
      data: items.map(i => i.count),
      fill: true,
      borderColor: '#8b5cf6',
      backgroundColor: 'rgba(139, 92, 246, 0.08)',
      tension: 0.35,
      pointRadius: 3,
      pointBackgroundColor: '#8b5cf6',
      borderWidth: 2,
    }],
  }
})

const lineChartOpts = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { color: gridColor() }, ticks: { color: textMuted(), font: { size: 10 } } },
    y: { grid: { color: gridColor() }, ticks: { color: textMuted(), font: { size: 10 } }, beginAtZero: true },
  },
}))

const doughnutData = computed(() => {
  if (!charts.value) return null
  const items = charts.value.subscriptions_by_plan
  const colors = ['#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe', '#06b6d4', '#10b981']
  return {
    labels: items.map(i => i.label),
    datasets: [{
      data: items.map(i => i.value),
      backgroundColor: colors.slice(0, items.length),
      borderWidth: 0,
      hoverOffset: 6,
    }],
  }
})

const doughnutOpts = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '68%',
  plugins: {
    legend: { position: 'bottom' as const, labels: { usePointStyle: true, pointStyle: 'circle', padding: 12, font: { size: 11 } } },
  },
}

const aiStatusData = computed(() => {
  if (!charts.value) return null
  const s = charts.value.ai_requests_by_status
  return {
    labels: [t('common.pending'), t('aiRequests.processing'), t('aiRequests.completed'), t('aiRequests.failed')],
    datasets: [{
      data: [s.pending || 0, s.processing || 0, s.completed || 0, s.failed || 0],
      backgroundColor: ['#f59e0b', '#06b6d4', '#10b981', '#ef4444'],
      borderRadius: 4,
      borderWidth: 0,
      barThickness: 18,
    }],
  }
})

const barChartOpts = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y' as const,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { color: gridColor() }, ticks: { color: textMuted(), font: { size: 10 } }, beginAtZero: true },
    y: { grid: { display: false }, ticks: { color: textMuted(), font: { size: 11 } } },
  },
}))

// ── Helpers ─────────────────────────────────────────────────
function statusSeverity(s: string): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  const map: Record<string, 'success' | 'warn' | 'danger' | 'info' | 'secondary'> = {
    completed: 'success', paid: 'success', active: 'success',
    pending: 'warn', processing: 'info',
    failed: 'danger', cancelled: 'danger', refunded: 'secondary',
  }
  return map[s] || 'secondary'
}

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// ── Alert totals ────────────────────────────────────────────
const alertBadges = computed(() => {
  if (!alerts.value) return []
  const a = alerts.value
  return [
    { label: t('dashboard.failedRequestsToday'), value: a.failed_requests.count_today, icon: 'pi pi-times-circle', color: '#ef4444' },
    { label: t('dashboard.pendingPayments'), value: a.pending_payments.count, icon: 'pi pi-clock', color: '#f59e0b' },
    { label: t('dashboard.lowCreditUsers'), value: a.system.low_credit_users, icon: 'pi pi-wallet', color: '#f97316' },
    { label: t('dashboard.expiringSubscriptions'), value: a.system.subscriptions_expiring, icon: 'pi pi-calendar-clock', color: '#8b5cf6' },
  ]
})

// ── Subscriptions table ─────────────────────────────────────
const subPlans = computed(() => kpis.value?.subscriptions_per_plan || [])
const maxSubCount = computed(() => Math.max(...subPlans.value.map(p => p.total_count), 1))
</script>

<template>
  <div class="dash">
    <!-- Header -->
    <div class="dash-header">
      <h1 class="dash-title">{{ t('dashboard.title') }}</h1>
    </div>

    <!-- Skeleton loader -->
    <template v-if="loading">
      <div class="kpi-grid">
        <div v-for="n in 8" :key="n" class="kpi-card">
          <Skeleton width="2rem" height="2rem" shape="circle" />
          <div class="flex-1">
            <Skeleton width="60%" height="1rem" class="mb-2" />
            <Skeleton width="40%" height="0.7rem" />
          </div>
        </div>
      </div>
    </template>

    <!-- Loaded content -->
    <template v-else-if="kpis">
      <!-- KPI Cards -->
      <div class="kpi-grid">
        <div v-for="(c, i) in kpiCards" :key="i" class="kpi-card">
          <div class="kpi-icon" :style="{ background: c.color + '14', color: c.color }">
            <i :class="c.icon" />
          </div>
          <div class="kpi-body">
            <span class="kpi-value">{{ typeof c.value === 'number' ? c.value.toLocaleString() : c.value }}</span>
            <span class="kpi-label">{{ c.label }}</span>
            <span class="kpi-sub">{{ c.sub }}</span>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="charts-row">
        <!-- Line Chart — Images last 7 days -->
        <div class="chart-card chart-wide">
          <div class="card-head">
            <i class="pi pi-chart-line card-head-icon" />
            <span>{{ t('dashboard.imagesLast7Days') }}</span>
          </div>
          <div class="chart-wrap chart-h-sm">
            <Chart v-if="lineChartData" type="line" :data="lineChartData" :options="lineChartOpts" />
          </div>
        </div>

        <!-- Doughnut — Subscriptions by Plan -->
        <div class="chart-card">
          <div class="card-head">
            <i class="pi pi-chart-pie card-head-icon" />
            <span>{{ t('dashboard.subscriptionsByPlan') }}</span>
          </div>
          <div class="chart-wrap chart-h-sm">
            <Chart v-if="doughnutData" type="doughnut" :data="doughnutData" :options="doughnutOpts" />
          </div>
        </div>
      </div>

      <!-- Second Row: AI Status + Subscriptions Table -->
      <div class="charts-row">
        <!-- Horizontal Bar — AI Requests by Status -->
        <div class="chart-card">
          <div class="card-head">
            <i class="pi pi-microchip-ai card-head-icon" />
            <span>{{ t('dashboard.aiRequestsByStatus') }}</span>
          </div>
          <div class="chart-wrap chart-h-xs">
            <Chart v-if="aiStatusData" type="bar" :data="aiStatusData" :options="barChartOpts" />
          </div>
        </div>

        <!-- Subscriptions per Plan Table -->
        <div class="chart-card chart-wide">
          <div class="card-head">
            <i class="pi pi-box card-head-icon" />
            <span>{{ t('dashboard.subscriptionsPerPlan') }}</span>
          </div>
          <div class="plan-table">
            <div v-for="plan in subPlans" :key="plan.id" class="plan-row">
              <div class="plan-name">{{ plan.name }}</div>
              <div class="plan-bar-wrap">
                <ProgressBar
                  :value="Math.round((plan.total_count / maxSubCount) * 100)"
                  :showValue="false"
                  class="plan-bar"
                />
              </div>
              <div class="plan-counts">
                <Tag :value="`${plan.active_count} ${t('common.active')}`" severity="success" class="plan-tag" />
                <Tag v-if="plan.trial_count" :value="`${plan.trial_count} ${t('common.trial')}`" severity="warn" class="plan-tag" />
                <span class="plan-total">{{ plan.total_count }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Alerts Strip -->
      <div v-if="alerts" class="alert-strip">
        <div v-for="(a, i) in alertBadges" :key="i" class="alert-badge" :class="{ 'alert-zero': a.value === 0 }">
          <i :class="a.icon" :style="{ color: a.color }" />
          <span class="alert-val">{{ a.value }}</span>
          <span class="alert-lbl">{{ a.label }}</span>
        </div>
      </div>

      <!-- Tables Row -->
      <div class="tables-row">
        <!-- Recent AI Requests -->
        <div class="table-card">
          <div class="card-head">
            <i class="pi pi-microchip-ai card-head-icon" />
            <span>{{ t('dashboard.recentAiRequests') }}</span>
          </div>
          <DataTable :value="recentAi" :rows="6" stripedRows size="small" scrollable scrollHeight="320px" class="dash-table">
            <Column field="user" :header="t('common.user')" style="min-width: 120px">
              <template #body="{ data }">
                <div class="user-cell">
                  <div class="user-avatar-sm" v-if="data.user">
                    {{ data.user.name.charAt(0) }}
                  </div>
                  <span>{{ data.user?.name || '—' }}</span>
                </div>
              </template>
            </Column>
            <Column field="type" :header="t('common.type')" style="min-width: 70px">
              <template #body="{ data }">
                <span class="cell-mono">{{ data.type }}</span>
              </template>
            </Column>
            <Column field="model" :header="t('common.model')" style="min-width: 80px">
              <template #body="{ data }">
                <span class="cell-mono">{{ data.model }}</span>
              </template>
            </Column>
            <Column field="credits" :header="t('common.credits')" style="min-width: 60px">
              <template #body="{ data }">
                <span class="cell-num">{{ data.credits }}</span>
              </template>
            </Column>
            <Column field="status" :header="t('common.status')" style="min-width: 90px">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="statusSeverity(data.status)" class="cell-tag" />
              </template>
            </Column>
            <Column field="date" :header="t('common.date')" style="min-width: 130px">
              <template #body="{ data }">
                <span class="cell-date">{{ formatDate(data.date) }}</span>
              </template>
            </Column>
          </DataTable>
        </div>

        <!-- Recent Payments -->
        <div class="table-card">
          <div class="card-head">
            <i class="pi pi-credit-card card-head-icon" />
            <span>{{ t('dashboard.recentPayments') }}</span>
          </div>
          <DataTable :value="recentPayments" :rows="6" stripedRows size="small" scrollable scrollHeight="320px" class="dash-table">
            <Column field="user" :header="t('common.user')" style="min-width: 120px">
              <template #body="{ data }">
                <div class="user-cell">
                  <div class="user-avatar-sm" v-if="data.user">
                    {{ data.user.name.charAt(0) }}
                  </div>
                  <span>{{ data.user?.name || '—' }}</span>
                </div>
              </template>
            </Column>
            <Column field="plan" :header="t('common.plan')" style="min-width: 80px">
              <template #body="{ data }">
                <span class="cell-mono">{{ data.plan || '—' }}</span>
              </template>
            </Column>
            <Column field="amount" :header="t('common.amount')" style="min-width: 80px">
              <template #body="{ data }">
                <span class="cell-num">${{ data.amount.toLocaleString() }}</span>
              </template>
            </Column>
            <Column field="payment_method" :header="t('common.method')" style="min-width: 80px">
              <template #body="{ data }">
                <span class="cell-mono">{{ data.payment_method }}</span>
              </template>
            </Column>
            <Column field="status" :header="t('common.status')" style="min-width: 90px">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="statusSeverity(data.status)" class="cell-tag" />
              </template>
            </Column>
            <Column field="date" :header="t('common.date')" style="min-width: 130px">
              <template #body="{ data }">
                <span class="cell-date">{{ formatDate(data.date) }}</span>
              </template>
            </Column>
          </DataTable>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.dash {
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
  overflow: hidden;
}

/* Header */
.dash-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.dash-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

/* KPI Grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
@media (min-width: 640px) {
  .kpi-grid { grid-template-columns: repeat(4, 1fr); }
}

.kpi-card {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
}

.kpi-icon {
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  flex-shrink: 0;
  font-size: 0.95rem;
}

.kpi-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.kpi-value {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.2;
}
.kpi-label {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-top: 1px;
}
.kpi-sub {
  font-size: 0.62rem;
  color: var(--text-muted);
  opacity: 0.7;
}

/* Charts */
.charts-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}
@media (min-width: 768px) {
  .charts-row { grid-template-columns: 1fr 1fr; }
  .chart-wide { grid-column: span 1; }
}
@media (min-width: 1024px) {
  .charts-row { grid-template-columns: 1.4fr 0.6fr; }
}

.chart-card {
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  padding: 14px;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

.card-head {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 10px;
}
.card-head-icon {
  font-size: 0.85rem;
  color: var(--text-muted);
}

.chart-wrap {
  flex: 1;
  position: relative;
  min-width: 0;
  overflow: hidden;
}
.chart-h-sm { height: 220px; }
.chart-h-xs { height: 170px; }

/* Plan mini-table */
.plan-table {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.plan-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.plan-name {
  width: 90px;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex-shrink: 0;
}
.plan-bar-wrap {
  flex: 1;
  min-width: 60px;
}
.plan-bar {
  height: 6px !important;
  border-radius: 3px;
}
.plan-counts {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}
.plan-tag {
  font-size: 0.6rem !important;
  padding: 2px 6px !important;
}
.plan-total {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--text-primary);
  min-width: 24px;
  text-align: right;
}

/* Alert strip */
.alert-strip {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
@media (min-width: 640px) {
  .alert-strip { grid-template-columns: repeat(4, 1fr); }
}

.alert-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
}
.alert-zero {
  opacity: 0.5;
}
.alert-badge i {
  font-size: 1rem;
}
.alert-val {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-primary);
}
.alert-lbl {
  font-size: 0.68rem;
  color: var(--text-muted);
  line-height: 1.2;
}

/* Tables */
.tables-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}
@media (min-width: 1024px) {
  .tables-row { grid-template-columns: 1fr 1fr; }
}

.table-card {
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  padding: 12px;
  overflow: hidden;
  min-width: 0;
}

/* DataTable overrides */
.dash-table {
  font-size: 0.75rem;
}

:deep(.dash-table) {
  background: transparent;
  color: var(--text-primary);
}

:deep(.dash-table .p-datatable-table-container) {
  border: 1px solid var(--card-border);
  border-radius: 10px;
  overflow: hidden;
  background: var(--card-bg);
}

:deep(.dash-table .p-datatable-table) {
  background: var(--card-bg);
}

:deep(.dash-table .p-datatable-thead > tr > th) {
  background: var(--hover-bg);
  color: var(--text-secondary);
  border-color: var(--card-border);
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 10px 12px;
}

:deep(.dash-table .p-datatable-tbody > tr) {
  background: var(--card-bg);
  color: var(--text-primary);
}

:deep(.dash-table.p-datatable-striped .p-datatable-tbody > tr:nth-child(even)) {
  background: color-mix(in srgb, var(--card-bg) 76%, var(--hover-bg) 24%);
}

:deep(.dash-table .p-datatable-tbody > tr:hover) {
  background: var(--hover-bg);
}

:deep(.dash-table .p-datatable-tbody > tr > td) {
  background: transparent;
  color: var(--text-primary);
  border-color: var(--card-border);
  padding: 10px 12px;
}

:deep(.dash-table .p-datatable-column-title) {
  color: inherit;
}

:deep(.dash-table .p-datatable-scrollable-header),
:deep(.dash-table .p-datatable-scrollable-body),
:deep(.dash-table .p-datatable-scrollable-body-table),
:deep(.dash-table .p-datatable-scrollable-header-box) {
  background: transparent;
}

/* User cell */
.user-cell {
  display: flex;
  align-items: center;
  gap: 6px;
}
.user-avatar-sm {
  width: 22px;
  height: 22px;
  border-radius: 6px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 0.6rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cell-mono { font-size: 0.72rem; color: var(--text-secondary); }
.cell-num { font-weight: 600; font-size: 0.75rem; color: var(--text-primary); }
.cell-date { font-size: 0.68rem; color: var(--text-muted); }
.cell-tag { font-size: 0.6rem !important; padding: 2px 6px !important; }
</style>
