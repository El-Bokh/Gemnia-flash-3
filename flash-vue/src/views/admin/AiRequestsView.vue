<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  bulkDeleteAiRequests,
  bulkRetryAiRequests,
  cancelAiRequest,
  deleteAiRequest,
  getAiRequestAggregations,
  getAiRequests,
  restoreAiRequest,
  retryAiRequest,
} from '@/services/aiRequestService'
import type {
  AiRequest,
  AiRequestAggregations,
  AiRequestStatus,
  AiRequestType,
  ListAiRequestsParams,
} from '@/types/aiRequests'
import Chart from 'primevue/chart'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import AiRequestDetailDrawer from '@/components/ai/AiRequestDetailDrawer.vue'

const { t } = useI18n()

const loading = ref(true)
const aggregationsLoading = ref(true)

const requests = ref<AiRequest[]>([])
const aggregations = ref<AiRequestAggregations | null>(null)
const totalRecords = ref(0)
const currentPage = ref(1)
const perPage = ref(15)

const search = ref('')
const statusFilter = ref<'all' | AiRequestStatus>('all')
const typeFilter = ref<'all' | AiRequestType>('all')
const providerFilter = ref('')
const withTrashed = ref(false)
const sortField = ref<ListAiRequestsParams['sort_by']>('created_at')
const sortOrder = ref<'asc' | 'desc'>('desc')

const selectedIds = ref<number[]>([])
const actionLoading = ref(false)

const showDetail = ref(false)
const detailRequestId = ref<number | null>(null)

const showDeleteConfirm = ref(false)
const deleteMode = ref<'single' | 'bulk'>('single')
const deleteTarget = ref<AiRequest | null>(null)

const statusOptions = computed(() => [
  { label: t('aiRequests.allStatus'), value: 'all' },
  { label: t('aiRequests.pending'), value: 'pending' },
  { label: t('aiRequests.processing'), value: 'processing' },
  { label: t('aiRequests.completed'), value: 'completed' },
  { label: t('aiRequests.failed'), value: 'failed' },
  { label: t('aiRequests.cancelled'), value: 'cancelled' },
  { label: t('aiRequests.timeout'), value: 'timeout' },
] as Array<{ label: string; value: 'all' | AiRequestStatus }>)

const typeOptions = computed(() => [
  { label: t('aiRequests.allTypes'), value: 'all' },
  { label: t('aiRequests.textToImage'), value: 'text_to_image' },
  { label: t('aiRequests.imageToImage'), value: 'image_to_image' },
  { label: t('aiRequests.inpainting'), value: 'inpainting' },
  { label: t('aiRequests.upscale'), value: 'upscale' },
  { label: t('aiRequests.other'), value: 'other' },
] as Array<{ label: string; value: 'all' | AiRequestType }>)

const providerOptions = computed(() => {
  const fromAgg = Object.keys(aggregations.value?.by_engine || {})
  const fromList = requests.value.map(item => item.engine_provider).filter(Boolean) as string[]
  const unique = Array.from(new Set([...fromAgg, ...fromList]))
  return [
    { label: t('aiRequests.allProviders'), value: '' },
    ...unique.map(item => ({ label: item, value: item })),
  ]
})

async function fetchRequests() {
  loading.value = true
  try {
    const params: ListAiRequestsParams = {
      page: currentPage.value,
      per_page: perPage.value,
      sort_by: sortField.value,
      sort_dir: sortOrder.value,
      with_trashed: withTrashed.value || undefined,
    }

    if (search.value) params.search = search.value
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (typeFilter.value !== 'all') params.type = typeFilter.value
    if (providerFilter.value) params.engine_provider = providerFilter.value

    const res = await getAiRequests(params)
    requests.value = res.data
    totalRecords.value = res.meta.total
    selectedIds.value = selectedIds.value.filter(id => requests.value.some(item => item.id === id))
  } catch {
    loadMockRequests()
  } finally {
    loading.value = false
  }
}

async function fetchAggregations() {
  aggregationsLoading.value = true
  try {
    const res = await getAiRequestAggregations()
    aggregations.value = res.data
  } catch {
    loadMockAggregations()
  } finally {
    aggregationsLoading.value = false
  }
}

function loadMockRequests() {
  requests.value = [
    { id: 101, uuid: 'req_001', type: 'text_to_image', status: 'completed', user_prompt: 'Luxury perfume bottle on stone podium with soft shadows', model_used: 'flux-pro', engine_provider: 'fal', width: 1024, height: 1024, num_images: 4, credits_consumed: 12, retry_count: 0, processing_time_ms: 4200, error_message: null, error_code: null, started_at: '2026-04-04T08:30:00Z', completed_at: '2026-04-04T08:30:04Z', created_at: '2026-04-04T08:30:00Z', updated_at: '2026-04-04T08:30:04Z', deleted_at: null, user: { id: 1, name: 'Sara Ahmed', email: 'sara@klek.ai', avatar: null }, visual_style: { id: 3, name: 'Editorial Luxe', slug: 'editorial-luxe', thumbnail: null }, generated_images_count: 4 },
    { id: 102, uuid: 'req_002', type: 'image_to_image', status: 'processing', user_prompt: 'Convert this room photo into Nordic interior style', model_used: 'sdxl-img2img', engine_provider: 'replicate', width: 1344, height: 768, num_images: 2, credits_consumed: 8, retry_count: 1, processing_time_ms: null, error_message: null, error_code: null, started_at: '2026-04-04T08:42:00Z', completed_at: null, created_at: '2026-04-04T08:42:00Z', updated_at: '2026-04-04T08:42:15Z', deleted_at: null, user: { id: 2, name: 'Omar Ali', email: 'omar@klek.ai', avatar: null }, visual_style: { id: 4, name: 'Nordic Clean', slug: 'nordic-clean', thumbnail: null }, generated_images_count: 0 },
    { id: 103, uuid: 'req_003', type: 'inpainting', status: 'failed', user_prompt: 'Remove the background crowd and extend jacket texture naturally', model_used: 'sdxl-inpaint', engine_provider: 'fal', width: 1024, height: 1536, num_images: 1, credits_consumed: 5, retry_count: 2, processing_time_ms: 1900, error_message: 'Worker timeout during inpaint stage', error_code: 'WORKER_TIMEOUT', started_at: '2026-04-04T07:58:00Z', completed_at: '2026-04-04T07:58:02Z', created_at: '2026-04-04T07:58:00Z', updated_at: '2026-04-04T07:58:02Z', deleted_at: null, user: { id: 3, name: 'Mona Khaled', email: 'mona@klek.ai', avatar: null }, visual_style: null, generated_images_count: 0 },
    { id: 104, uuid: 'req_004', type: 'upscale', status: 'pending', user_prompt: 'Upscale product thumbnail for homepage hero banner', model_used: 'real-esrgan', engine_provider: 'internal', width: 800, height: 800, num_images: 1, credits_consumed: 2, retry_count: 0, processing_time_ms: null, error_message: null, error_code: null, started_at: null, completed_at: null, created_at: '2026-04-04T08:48:00Z', updated_at: '2026-04-04T08:48:00Z', deleted_at: null, user: { id: 4, name: 'Karim Mostafa', email: 'karim@klek.ai', avatar: null }, visual_style: null, generated_images_count: 0 },
    { id: 105, uuid: 'req_005', type: 'text_to_image', status: 'cancelled', user_prompt: 'Fashion campaign shot with reflective floor and blue backlight', model_used: 'flux-pro', engine_provider: 'fal', width: 1024, height: 1280, num_images: 3, credits_consumed: 0, retry_count: 1, processing_time_ms: 800, error_message: 'Cancelled by admin', error_code: 'ADMIN_CANCELLED', started_at: '2026-04-04T06:40:00Z', completed_at: '2026-04-04T06:40:01Z', created_at: '2026-04-04T06:40:00Z', updated_at: '2026-04-04T06:40:01Z', deleted_at: null, user: { id: 5, name: 'Layla Hassan', email: 'layla@klek.ai', avatar: null }, visual_style: { id: 5, name: 'Studio Blue', slug: 'studio-blue', thumbnail: null }, generated_images_count: 0 },
    { id: 106, uuid: 'req_006', type: 'text_to_image', status: 'completed', user_prompt: 'Minimal UI dashboard illustration in black and gray', model_used: 'dall-e-3', engine_provider: 'openai', width: 1792, height: 1024, num_images: 1, credits_consumed: 10, retry_count: 0, processing_time_ms: 6100, error_message: null, error_code: null, started_at: '2026-04-03T19:20:00Z', completed_at: '2026-04-03T19:20:06Z', created_at: '2026-04-03T19:20:00Z', updated_at: '2026-04-03T19:20:06Z', deleted_at: '2026-04-04T00:30:00Z', user: { id: 6, name: 'Nour Sayed', email: 'nour@klek.ai', avatar: null }, visual_style: { id: 2, name: 'Minimal Noir', slug: 'minimal-noir', thumbnail: null }, generated_images_count: 1 },
  ]
  totalRecords.value = 126
}

function loadMockAggregations() {
  aggregations.value = {
    overview: {
      total_requests: 12842,
      total_credits_consumed: 68420,
      total_images_requested: 23811,
      avg_processing_time_ms: 3240,
      max_processing_time_ms: 15220,
      min_processing_time_ms: 640,
      avg_credits_per_request: 5.3,
      avg_retry_count: 0.4,
      requests_with_retries: 842,
      success_rate: 91.4,
      failure_rate: 4.8,
    },
    by_status: { pending: 42, processing: 18, completed: 11733, failed: 621, cancelled: 286, timeout: 142 },
    by_type: { text_to_image: 8021, image_to_image: 2420, inpainting: 1103, upscale: 912, other: 386 },
    by_engine: { fal: 5220, replicate: 2900, openai: 3410, internal: 1312 },
    by_model: { 'flux-pro': 3310, 'dall-e-3': 2504, 'sdxl-img2img': 2114, 'sdxl-inpaint': 984, 'real-esrgan': 912 },
    daily_trend: [
      { date: '2026-03-29', total: 1710, completed: 1570, failed: 58, credits: 9031 },
      { date: '2026-03-30', total: 1652, completed: 1511, failed: 66, credits: 8762 },
      { date: '2026-03-31', total: 1780, completed: 1631, failed: 73, credits: 9540 },
      { date: '2026-04-01', total: 1864, completed: 1708, failed: 81, credits: 9905 },
      { date: '2026-04-02', total: 1930, completed: 1764, failed: 92, credits: 10362 },
      { date: '2026-04-03', total: 2014, completed: 1838, failed: 111, credits: 10710 },
      { date: '2026-04-04', total: 1892, completed: 1711, failed: 140, credits: 10110 },
    ],
    top_users: [
      { user: { id: 1, name: 'Sara Ahmed', email: 'sara@klek.ai', avatar: null }, request_count: 382, total_credits: 2190, completed_count: 358 },
      { user: { id: 2, name: 'Omar Ali', email: 'omar@klek.ai', avatar: null }, request_count: 341, total_credits: 1844, completed_count: 327 },
      { user: { id: 3, name: 'Mona Khaled', email: 'mona@klek.ai', avatar: null }, request_count: 298, total_credits: 1652, completed_count: 276 },
    ],
    top_visual_styles: [
      { style: { id: 1, name: 'Minimal Noir', slug: 'minimal-noir', thumbnail: null }, usage_count: 1220 },
      { style: { id: 2, name: 'Editorial Luxe', slug: 'editorial-luxe', thumbnail: null }, usage_count: 981 },
      { style: { id: 3, name: 'Nordic Clean', slug: 'nordic-clean', thumbnail: null }, usage_count: 744 },
    ],
    error_codes: [
      { error_code: 'WORKER_TIMEOUT', count: 184 },
      { error_code: 'MODEL_UNAVAILABLE', count: 96 },
      { error_code: 'RATE_LIMITED', count: 53 },
    ],
  }
}

onMounted(async () => {
  await Promise.all([fetchRequests(), fetchAggregations()])
})

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    fetchRequests()
  }, 350)
})

watch([statusFilter, typeFilter, providerFilter, withTrashed], () => {
  currentPage.value = 1
  fetchRequests()
})

function onPage(event: any) {
  currentPage.value = Math.floor(event.first / event.rows) + 1
  perPage.value = event.rows
  fetchRequests()
}

function onSort(event: any) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder === 1 ? 'asc' : 'desc'
  fetchRequests()
}

function openDetail(item: AiRequest) {
  detailRequestId.value = item.id
  showDetail.value = true
}

function setSelected(id: number, checked: boolean) {
  if (checked) {
    if (!selectedIds.value.includes(id)) selectedIds.value = [...selectedIds.value, id]
    return
  }
  selectedIds.value = selectedIds.value.filter(item => item !== id)
}

const pageAllSelected = computed(() => requests.value.length > 0 && requests.value.every(item => selectedIds.value.includes(item.id)))

function togglePageSelection(checked: boolean) {
  if (checked) {
    const merged = new Set([...selectedIds.value, ...requests.value.map(item => item.id)])
    selectedIds.value = Array.from(merged)
    return
  }
  const currentIds = new Set(requests.value.map(item => item.id))
  selectedIds.value = selectedIds.value.filter(id => !currentIds.has(id))
}

async function handleRetry(item: AiRequest) {
  actionLoading.value = true
  try {
    await retryAiRequest(item.id)
    await Promise.all([fetchRequests(), fetchAggregations()])
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleCancel(item: AiRequest) {
  actionLoading.value = true
  try {
    await cancelAiRequest(item.id)
    await Promise.all([fetchRequests(), fetchAggregations()])
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleRestore(item: AiRequest) {
  actionLoading.value = true
  try {
    await restoreAiRequest(item.id)
    await Promise.all([fetchRequests(), fetchAggregations()])
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

function confirmDeleteSingle(item: AiRequest) {
  deleteMode.value = 'single'
  deleteTarget.value = item
  showDeleteConfirm.value = true
}

function confirmDeleteBulk() {
  deleteMode.value = 'bulk'
  deleteTarget.value = null
  showDeleteConfirm.value = true
}

async function handleDelete() {
  actionLoading.value = true
  try {
    if (deleteMode.value === 'single' && deleteTarget.value) {
      await deleteAiRequest(deleteTarget.value.id)
    }
    if (deleteMode.value === 'bulk' && selectedIds.value.length) {
      await bulkDeleteAiRequests({ request_ids: selectedIds.value })
      selectedIds.value = []
    }
    showDeleteConfirm.value = false
    await Promise.all([fetchRequests(), fetchAggregations()])
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleBulkRetry() {
  if (!selectedIds.value.length) return
  actionLoading.value = true
  try {
    await bulkRetryAiRequests({ request_ids: selectedIds.value })
    selectedIds.value = []
    await Promise.all([fetchRequests(), fetchAggregations()])
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

function onDrawerUpdated() {
  Promise.all([fetchRequests(), fetchAggregations()])
}

const statCards = computed(() => {
  const overview = aggregations.value?.overview
  if (!overview) return []
  return [
    { label: t('aiRequests.requests'), value: overview.total_requests.toLocaleString(), sub: t('aiRequests.successRate', { rate: overview.success_rate.toFixed(1) }), tone: '#3b82f6', icon: 'pi pi-sparkles' },
    { label: t('aiRequests.credits'), value: overview.total_credits_consumed.toLocaleString(), sub: t('aiRequests.avgPerRequest', { avg: overview.avg_credits_per_request.toFixed(1) }), tone: '#8b5cf6', icon: 'pi pi-bolt' },
    { label: t('aiRequests.images'), value: overview.total_images_requested.toLocaleString(), sub: t('aiRequests.retried', { count: overview.requests_with_retries }), tone: '#10b981', icon: 'pi pi-image' },
    { label: t('aiRequests.avgTime'), value: formatDuration(overview.avg_processing_time_ms), sub: t('aiRequests.max', { time: formatDuration(overview.max_processing_time_ms) }), tone: '#f59e0b', icon: 'pi pi-stopwatch' },
  ]
})

function gridColor() {
  return getComputedStyle(document.documentElement).getPropertyValue('--card-border').trim() || '#e2e8f0'
}

function mutedText() {
  return getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#94a3b8'
}

const trendChartData = computed(() => {
  const items = aggregations.value?.daily_trend || []
  return {
    labels: items.map(item => formatShortDate(item.date)),
    datasets: [
      {
        label: t('aiRequests.requests'),
        data: items.map(item => item.total),
        borderColor: '#0ea5e9',
        backgroundColor: 'rgba(14, 165, 233, 0.08)',
        fill: true,
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 2,
      },
      {
        label: t('aiRequests.failed'),
        data: items.map(item => item.failed),
        borderColor: '#ef4444',
        backgroundColor: 'rgba(239, 68, 68, 0.04)',
        fill: false,
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 2,
      },
    ],
  }
})

const trendChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { labels: { color: mutedText(), usePointStyle: true, pointStyle: 'circle' } } },
  scales: {
    x: { grid: { color: gridColor() }, ticks: { color: mutedText(), font: { size: 10 } } },
    y: { grid: { color: gridColor() }, ticks: { color: mutedText(), font: { size: 10 } }, beginAtZero: true },
  },
}))

const statusChartData = computed(() => {
  const byStatus = aggregations.value?.by_status || {}
  const entries = Object.entries(byStatus)
  return {
    labels: entries.map(([key]) => capitalize(key)),
    datasets: [{ data: entries.map(([, value]) => value), backgroundColor: ['#f59e0b', '#06b6d4', '#10b981', '#ef4444', '#64748b', '#8b5cf6'], borderWidth: 0, hoverOffset: 6 }],
  }
})

const statusChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '68%',
  plugins: {
    legend: { position: 'bottom' as const, labels: { usePointStyle: true, pointStyle: 'circle', padding: 12, font: { size: 11 } } },
  },
}

const typeChartData = computed(() => {
  const byType = aggregations.value?.by_type || {}
  const entries = Object.entries(byType)
  return {
    labels: entries.map(([key]) => typeLabel(key)),
    datasets: [{ data: entries.map(([, value]) => value), backgroundColor: ['#8b5cf6', '#0ea5e9', '#10b981', '#f59e0b', '#6b7280'], borderRadius: 5, borderWidth: 0, barThickness: 18 }],
  }
})

const typeChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y' as const,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { color: gridColor() }, ticks: { color: mutedText(), font: { size: 10 } }, beginAtZero: true },
    y: { grid: { display: false }, ticks: { color: mutedText(), font: { size: 10 } } },
  },
}))

function statusSeverity(status: string): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  const map: Record<string, 'success' | 'warn' | 'danger' | 'info' | 'secondary'> = {
    pending: 'warn',
    processing: 'info',
    completed: 'success',
    failed: 'danger',
    cancelled: 'secondary',
    timeout: 'danger',
  }
  return map[status] || 'secondary'
}

function typeLabel(type: string) {
  return {
    text_to_image: 'Text→Image',
    image_to_image: 'Image→Image',
    inpainting: 'Inpainting',
    upscale: 'Upscale',
    other: 'Other',
  }[type] || type
}

function formatDateTime(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatShortDate(value: string) {
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function formatDuration(value: number | null) {
  if (!value) return '—'
  if (value < 1000) return `${value} ms`
  return `${(value / 1000).toFixed(1)} s`
}

function initials(name: string) {
  return name.split(' ').map(item => item[0]).join('').slice(0, 2)
}

function shortPrompt(prompt: string) {
  return prompt.length > 88 ? `${prompt.slice(0, 88)}...` : prompt
}

function capitalize(value: string) {
  return value.charAt(0).toUpperCase() + value.slice(1)
}

function canRetry(item: AiRequest) {
  return ['failed', 'cancelled', 'timeout'].includes(item.status)
}

function canCancel(item: AiRequest) {
  return ['pending', 'processing'].includes(item.status)
}
</script>

<template>
  <div class="ai-page">
    <div class="page-toolbar">
      <h1 class="page-title">{{ t('aiRequests.title') }}</h1>
      <div class="toolbar-actions" v-if="selectedIds.length">
        <Button icon="pi pi-refresh" :label="t('aiRequests.retrySelected')" size="small" severity="secondary" :loading="actionLoading" @click="handleBulkRetry" />
        <Button icon="pi pi-trash" :label="t('aiRequests.deleteSelected')" size="small" severity="danger" :loading="actionLoading" @click="confirmDeleteBulk" />
      </div>
    </div>

    <div class="summary-grid">
      <article v-for="card in statCards" :key="card.label" class="summary-card">
        <span class="summary-icon" :style="{ color: card.tone, background: `${card.tone}12` }">
          <i :class="card.icon" />
        </span>
        <div class="summary-copy">
          <span class="summary-label">{{ card.label }}</span>
          <strong class="summary-value">{{ card.value }}</strong>
          <small class="summary-sub">{{ card.sub }}</small>
        </div>
      </article>
    </div>

    <div class="analytics-grid" v-if="!aggregationsLoading && aggregations">
      <section class="chart-card chart-wide">
        <div class="card-head">
          <div>
            <h3>{{ t('aiRequests.trendTitle') }}</h3>
            <p>{{ t('aiRequests.trendTitle') }}</p>
          </div>
        </div>
        <div class="chart-shell tall">
          <Chart type="line" :data="trendChartData" :options="trendChartOptions" />
        </div>
      </section>

      <section class="chart-card">
        <div class="card-head">
          <div>
            <h3>{{ t('aiRequests.requestsByStatus') }}</h3>
            <p>Current lifecycle distribution.</p>
          </div>
        </div>
        <div class="chart-shell">
          <Chart type="doughnut" :data="statusChartData" :options="statusChartOptions" />
        </div>
      </section>

      <section class="chart-card">
        <div class="card-head">
          <div>
            <h3>{{ t('aiRequests.requestsByType') }}</h3>
            <p>Load distribution by generation workflow.</p>
          </div>
        </div>
        <div class="chart-shell">
          <Chart type="bar" :data="typeChartData" :options="typeChartOptions" />
        </div>
      </section>

      <section class="insight-card">
        <div class="insight-block">
          <div class="card-head compact-head">
            <h3>Top Users</h3>
          </div>
          <div class="mini-list">
            <div v-for="entry in aggregations.top_users.slice(0, 4)" :key="entry.user?.id || entry.request_count" class="mini-row">
              <div class="mini-avatar">{{ initials(entry.user?.name || 'Unknown User') }}</div>
              <div class="mini-copy">
                <span class="mini-title">{{ entry.user?.name || 'Unknown User' }}</span>
                <span class="mini-sub">{{ entry.request_count }} requests · {{ entry.total_credits }} credits</span>
              </div>
            </div>
          </div>
        </div>

        <div class="insight-block">
          <div class="card-head compact-head">
            <h3>Error Codes</h3>
          </div>
          <div class="error-list">
            <div v-for="item in aggregations.error_codes.slice(0, 4)" :key="item.error_code" class="error-chip">
              <span>{{ item.error_code }}</span>
              <strong>{{ item.count }}</strong>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="filters-bar">
      <span class="filter-search">
        <i class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('aiRequests.searchPlaceholder')" size="small" class="filter-input" />
      </span>
      <Select v-model="statusFilter" :options="statusOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
      <Select v-model="typeFilter" :options="typeOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
      <Select v-model="providerFilter" :options="providerOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
      <label class="with-trashed-toggle">
        <Checkbox v-model="withTrashed" :binary="true" />
        <span>{{ t('aiRequests.showTrashed') }}</span>
      </label>
      <span class="filter-count">{{ totalRecords }} {{ t('common.requests') }}</span>
    </div>

    <div class="cards-list d-mobile">
      <article v-for="item in requests" :key="item.id" class="request-card" @click="openDetail(item)">
        <div class="request-head">
          <label class="select-toggle" @click.stop>
            <Checkbox :modelValue="selectedIds.includes(item.id)" :binary="true" @update:modelValue="setSelected(item.id, $event)" />
          </label>
          <div class="request-head-copy">
            <div class="request-title-row">
              <span class="request-uuid">#{{ item.id }} · {{ item.uuid }}</span>
              <Tag :value="item.status" :severity="statusSeverity(item.status)" class="mini-tag" />
            </div>
            <span class="request-user">{{ item.user?.name || 'Unknown User' }} · {{ typeLabel(item.type) }}</span>
          </div>
        </div>

        <p class="request-prompt">{{ shortPrompt(item.user_prompt) }}</p>

        <div class="request-meta-grid">
          <span><i class="pi pi-microchip" /> {{ item.model_used || '—' }}</span>
          <span><i class="pi pi-server" /> {{ item.engine_provider || '—' }}</span>
          <span><i class="pi pi-bolt" /> {{ item.credits_consumed }} cr</span>
          <span><i class="pi pi-stopwatch" /> {{ formatDuration(item.processing_time_ms) }}</span>
        </div>

        <div class="request-foot">
          <span class="request-date">{{ formatDateTime(item.created_at) }}</span>
          <div class="row-actions" @click.stop>
            <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openDetail(item)" />
            <Button v-if="canRetry(item)" icon="pi pi-refresh" severity="secondary" text rounded size="small" @click="handleRetry(item)" />
            <Button v-if="canCancel(item)" icon="pi pi-times" severity="secondary" text rounded size="small" @click="handleCancel(item)" />
            <Button v-if="item.deleted_at" icon="pi pi-replay" severity="secondary" text rounded size="small" @click="handleRestore(item)" />
            <Button v-else icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDeleteSingle(item)" />
          </div>
        </div>
      </article>
    </div>

    <div class="table-card d-desktop">
      <DataTable
        :value="requests"
        :loading="loading"
        :rows="perPage"
        :totalRecords="totalRecords"
        :lazy="true"
        :paginator="true"
        :rowsPerPageOptions="[10, 15, 25, 50]"
        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
        sortMode="single"
        :sortField="sortField"
        :sortOrder="sortOrder === 'asc' ? 1 : -1"
        @sort="onSort"
        @page="onPage"
        stripedRows
        size="small"
        scrollable
        class="requests-table"
        dataKey="id"
      >
        <Column header="" style="width: 48px">
          <template #header>
            <Checkbox :modelValue="pageAllSelected" :binary="true" @update:modelValue="togglePageSelection($event)" />
          </template>
          <template #body="{ data }">
            <Checkbox :modelValue="selectedIds.includes(data.id)" :binary="true" @update:modelValue="setSelected(data.id, $event)" />
          </template>
        </Column>

        <Column field="id" header="#" sortable style="min-width: 80px">
          <template #body="{ data }">
            <span class="mono-text">#{{ data.id }}</span>
          </template>
        </Column>

        <Column field="user_prompt" header="Request" sortable style="min-width: 290px">
          <template #body="{ data }">
            <div class="request-cell" @click="openDetail(data)">
              <div class="request-copy">
                <span class="request-prompt-line">{{ shortPrompt(data.user_prompt) }}</span>
                <span class="request-sub-line">{{ data.user?.name || 'Unknown User' }} · {{ data.uuid }}</span>
              </div>
            </div>
          </template>
        </Column>

        <Column field="status" header="Status" sortable style="min-width: 110px">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="statusSeverity(data.status)" class="mini-tag" />
          </template>
        </Column>

        <Column field="type" header="Type" sortable style="min-width: 120px">
          <template #body="{ data }">
            <span class="metric-chip">{{ typeLabel(data.type) }}</span>
          </template>
        </Column>

        <Column field="engine_provider" header="Provider" sortable style="min-width: 100px">
          <template #body="{ data }">
            <span class="muted-text">{{ data.engine_provider || '—' }}</span>
          </template>
        </Column>

        <Column field="credits_consumed" header="Credits" sortable style="min-width: 90px">
          <template #body="{ data }">
            <span class="metric-strong">{{ data.credits_consumed }}</span>
          </template>
        </Column>

        <Column field="retry_count" header="Retry" sortable style="min-width: 80px">
          <template #body="{ data }">
            <span class="metric-chip">{{ data.retry_count }}</span>
          </template>
        </Column>

        <Column field="processing_time_ms" header="Time" sortable style="min-width: 90px">
          <template #body="{ data }">
            <span class="muted-text">{{ formatDuration(data.processing_time_ms) }}</span>
          </template>
        </Column>

        <Column field="created_at" header="Created" sortable style="min-width: 120px">
          <template #body="{ data }">
            <span class="muted-text">{{ formatDateTime(data.created_at) }}</span>
          </template>
        </Column>

        <Column header="" style="min-width: 160px; text-align: right" frozen alignFrozen="right">
          <template #body="{ data }">
            <div class="row-actions">
              <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openDetail(data)" v-tooltip.left="'View'" />
              <Button v-if="canRetry(data)" icon="pi pi-refresh" severity="secondary" text rounded size="small" @click="handleRetry(data)" v-tooltip.left="'Retry'" />
              <Button v-if="canCancel(data)" icon="pi pi-times" severity="secondary" text rounded size="small" @click="handleCancel(data)" v-tooltip.left="'Cancel'" />
              <Button v-if="data.deleted_at" icon="pi pi-replay" severity="secondary" text rounded size="small" @click="handleRestore(data)" v-tooltip.left="'Restore'" />
              <Button v-else icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDeleteSingle(data)" v-tooltip.left="'Delete'" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <AiRequestDetailDrawer v-model:visible="showDetail" :requestId="detailRequestId" @updated="onDrawerUpdated" />

    <Dialog v-model:visible="showDeleteConfirm" :header="t('aiRequests.title')" :modal="true" :style="{ width: '360px' }">
      <div class="confirm-body">
        <i class="pi pi-exclamation-triangle confirm-icon" />
        <p v-if="deleteMode === 'single'">{{ t('aiRequests.deleteConfirm') }} <strong>{{ deleteTarget?.uuid }}</strong></p>
        <p v-else>{{ t('aiRequests.deleteConfirmBulk', { count: selectedIds.length }) }}</p>
        <p class="confirm-sub">This performs a soft delete so records can still be restored later.</p>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text size="small" @click="showDeleteConfirm = false" />
        <Button :label="t('common.delete')" severity="danger" size="small" :loading="actionLoading" @click="handleDelete" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.ai-page { display: flex; flex-direction: column; gap: 10px; min-width: 0; overflow: hidden; }
.page-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.page-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.toolbar-actions { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
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
.summary-sub { font-size: 0.62rem; color: var(--text-muted); }

.analytics-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}
@media (min-width: 1024px) {
  .analytics-grid {
    grid-template-columns: 1.4fr 1fr 1fr 1fr;
    align-items: stretch;
  }
  .chart-wide { grid-column: span 1; }
}
.chart-card,
.insight-card {
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
  padding: 10px 12px;
  min-width: 0;
  overflow: hidden;
}
.card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}
.card-head h3 { margin: 0; font-size: 0.8rem; color: var(--text-primary); }
.card-head p { margin: 2px 0 0; font-size: 0.64rem; color: var(--text-muted); }
.compact-head { margin-bottom: 6px; }
.chart-shell { height: 220px; overflow: hidden; min-width: 0; position: relative; }
.chart-shell.tall { height: 240px; }
.insight-card { display: flex; flex-direction: column; gap: 10px; }
.mini-list { display: flex; flex-direction: column; gap: 8px; }
.mini-row { display: flex; align-items: center; gap: 8px; }
.mini-avatar {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: linear-gradient(135deg, #0ea5e9, #2563eb);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.62rem;
  font-weight: 700;
  flex-shrink: 0;
}
.mini-copy { display: flex; flex-direction: column; min-width: 0; }
.mini-title { font-size: 0.72rem; font-weight: 600; color: var(--text-primary); }
.mini-sub { font-size: 0.62rem; color: var(--text-muted); }
.error-list { display: flex; flex-direction: column; gap: 6px; }
.error-chip {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 7px 8px;
  border-radius: 8px;
  background: var(--hover-bg);
  font-size: 0.68rem;
  color: var(--text-primary);
}

.filters-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.filter-search { position: relative; flex: 1; min-width: 190px; max-width: 340px; }
.filter-search i {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.8rem;
  color: var(--text-muted);
  z-index: 1;
}
.filter-input { width: 100%; padding-left: 32px !important; font-size: 0.78rem !important; }
.filter-select { min-width: 135px; font-size: 0.78rem !important; }
.with-trashed-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  color: var(--text-primary);
}
.filter-count { font-size: 0.7rem; color: var(--text-muted); margin-left: auto; }

.d-mobile { display: grid; }
.d-desktop { display: none; }
@media (min-width: 768px) {
  .d-mobile { display: none; }
  .d-desktop { display: block; }
}

.cards-list { display: grid; grid-template-columns: 1fr; gap: 8px; }
.request-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
  cursor: pointer;
  transition: border-color 0.14s, box-shadow 0.14s;
}
.request-card:hover {
  border-color: var(--active-color);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--active-color) 20%, transparent);
}
.request-head { display: flex; gap: 8px; align-items: flex-start; }
.request-head-copy { min-width: 0; flex: 1; }
.request-title-row { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.request-uuid,
.request-user,
.request-date { font-size: 0.64rem; color: var(--text-muted); }
.request-prompt { margin: 0; font-size: 0.74rem; line-height: 1.4; color: var(--text-primary); }
.request-meta-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 10px;
  font-size: 0.66rem;
  color: var(--text-muted);
}
.request-meta-grid span,
.request-foot { display: flex; align-items: center; gap: 5px; }
.request-foot { justify-content: space-between; }

.table-card {
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  overflow: hidden;
  min-width: 0;
}
.requests-table { font-size: 0.75rem; }
:deep(.requests-table .p-datatable-table-container) {
  border-radius: 10px;
  overflow: hidden;
  background: var(--card-bg);
}
:deep(.requests-table .p-datatable-thead > tr > th) {
  background: var(--hover-bg);
  color: var(--text-secondary);
  border-color: var(--card-border);
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 10px 12px;
}
:deep(.requests-table .p-datatable-tbody > tr) {
  background: var(--card-bg);
  color: var(--text-primary);
}
:deep(.requests-table.p-datatable-striped .p-datatable-tbody > tr:nth-child(even)) {
  background: color-mix(in srgb, var(--card-bg) 76%, var(--hover-bg) 24%);
}
:deep(.requests-table .p-datatable-tbody > tr:hover) { background: var(--hover-bg); }
:deep(.requests-table .p-datatable-tbody > tr > td) {
  background: transparent;
  color: var(--text-primary);
  border-color: var(--card-border);
  padding: 8px 12px;
}
:deep(.requests-table .p-paginator) {
  background: var(--card-bg);
  border-color: var(--card-border);
  padding: 6px 12px;
}

.request-cell { cursor: pointer; }
.request-copy { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.request-prompt-line { font-size: 0.74rem; font-weight: 600; color: var(--text-primary); }
.request-sub-line,
.muted-text,
.mono-text { font-size: 0.64rem; color: var(--text-muted); }
.mono-text { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.metric-strong { font-size: 0.76rem; font-weight: 600; color: var(--text-primary); }
.metric-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.68rem;
  color: var(--text-secondary);
}
.row-actions { display: flex; align-items: center; gap: 0; }
.mini-tag { font-size: 0.58rem !important; padding: 2px 7px !important; }

.confirm-body { text-align: center; padding: 6px 0; }
.confirm-icon { font-size: 2rem; color: #ef4444; margin-bottom: 8px; }
.confirm-body p { font-size: 0.82rem; color: var(--text-primary); margin: 4px 0; }
.confirm-sub { font-size: 0.7rem; color: var(--text-muted); }
</style>
