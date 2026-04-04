<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getCouponAggregations, getCoupons, toggleCoupon } from '@/services/couponService'
import { getSupportTickets, getTicketAggregations } from '@/services/supportTicketService'
import type { Coupon, CouponAggregations, ListCouponsParams } from '@/types/payments'
import type { ListSupportTicketsParams, SupportTicket, TicketAggregations } from '@/types/supportTickets'
import type { DataTableSortEvent } from 'primevue/datatable'
import Chart from 'primevue/chart'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import SupportTicketDetailDrawer from '@/components/support/SupportTicketDetailDrawer.vue'
import CouponFormDialog from '@/components/support/CouponFormDialog.vue'
import CouponDetailDrawer from '@/components/support/CouponDetailDrawer.vue'

const { t } = useI18n()

type TicketStatusFilter = 'all' | NonNullable<ListSupportTicketsParams['status']>
type TicketPriorityFilter = 'all' | NonNullable<ListSupportTicketsParams['priority']>
type CouponTypeFilter = 'all' | NonNullable<ListCouponsParams['discount_type']>
type CouponStatusFilter = 'all' | 'active' | 'inactive'
type CouponExpiryFilter = 'all' | 'expired' | 'valid'
type TrashFilter = 'without' | 'with' | 'only'

const activeTab = ref('overview')

const ticketsLoading = ref(true)
const couponsLoading = ref(true)
const aggregationsLoading = ref(true)
const couponActionLoadingId = ref<number | null>(null)

const tickets = ref<SupportTicket[]>([])
const coupons = ref<Coupon[]>([])
const ticketAggregations = ref<TicketAggregations | null>(null)
const couponAggregations = ref<CouponAggregations | null>(null)

const ticketTotalRecords = ref(0)
const couponTotalRecords = ref(0)
const ticketPage = ref(1)
const couponPage = ref(1)
const ticketPerPage = ref(15)
const couponPerPage = ref(15)

const ticketSearch = ref('')
const couponSearch = ref('')
const ticketStatusFilter = ref<TicketStatusFilter>('all')
const ticketPriorityFilter = ref<TicketPriorityFilter>('all')
const ticketTrashFilter = ref<TrashFilter>('without')
const couponTypeFilter = ref<CouponTypeFilter>('all')
const couponStatusFilter = ref<CouponStatusFilter>('all')
const couponExpiryFilter = ref<CouponExpiryFilter>('all')
const couponTrashFilter = ref<TrashFilter>('without')

const ticketSortField = ref<NonNullable<ListSupportTicketsParams['sort_by']>>('created_at')
const ticketSortOrder = ref<'asc' | 'desc'>('desc')
const couponSortField = ref<NonNullable<ListCouponsParams['sort_by']>>('created_at')
const couponSortOrder = ref<'asc' | 'desc'>('desc')

const showTicketDetail = ref(false)
const ticketDetailId = ref<number | null>(null)
const showCouponForm = ref(false)
const couponFormId = ref<number | null>(null)
const showCouponDetail = ref(false)
const couponDetailId = ref<number | null>(null)

const ticketStatusOptions = computed(() => [
  { label: t('support.allStatus'), value: 'all' },
  { label: t('support.open'), value: 'open' },
  { label: t('support.inProgress'), value: 'in_progress' },
  { label: t('support.waitingReply'), value: 'waiting_reply' },
  { label: t('support.resolved'), value: 'resolved' },
  { label: t('support.closed'), value: 'closed' },
] as Array<{ label: string; value: TicketStatusFilter }>)

const ticketPriorityOptions = computed(() => [
  { label: t('support.allPriority'), value: 'all' },
  { label: t('support.low'), value: 'low' },
  { label: t('support.medium'), value: 'medium' },
  { label: t('support.high'), value: 'high' },
  { label: t('support.urgent'), value: 'urgent' },
] as Array<{ label: string; value: TicketPriorityFilter }>)

const couponTypeOptions = computed(() => [
  { label: t('support.allTypes'), value: 'all' },
  { label: t('support.percentage'), value: 'percentage' },
  { label: t('support.fixedAmount'), value: 'fixed_amount' },
  { label: t('support.creditsCoupon'), value: 'credits' },
] as Array<{ label: string; value: CouponTypeFilter }>)

const couponStatusOptions = computed(() => [
  { label: t('support.allStates'), value: 'all' },
  { label: t('support.activeCoupons'), value: 'active' },
  { label: t('support.inactiveCoupons'), value: 'inactive' },
] as Array<{ label: string; value: CouponStatusFilter }>)

const couponExpiryOptions = computed(() => [
  { label: t('support.allValidity'), value: 'all' },
  { label: t('support.valid'), value: 'valid' },
  { label: t('support.expired'), value: 'expired' },
] as Array<{ label: string; value: CouponExpiryFilter }>)

const trashOptions = computed(() => [
  { label: t('payments.activeOnly'), value: 'without' },
  { label: t('payments.withTrashed'), value: 'with' },
  { label: t('payments.onlyTrashed'), value: 'only' },
] as Array<{ label: string; value: TrashFilter }>)

async function fetchTickets() {
  ticketsLoading.value = true
  try {
    const params: ListSupportTicketsParams = {
      search: ticketSearch.value || undefined,
      status: ticketStatusFilter.value === 'all' ? undefined : ticketStatusFilter.value,
      priority: ticketPriorityFilter.value === 'all' ? undefined : ticketPriorityFilter.value,
      trashed: ticketTrashFilter.value === 'without' ? undefined : ticketTrashFilter.value,
      sort_by: ticketSortField.value,
      sort_dir: ticketSortOrder.value,
      page: ticketPage.value,
      per_page: ticketPerPage.value,
    }
    const res = await getSupportTickets(params)
    tickets.value = res.data
    ticketTotalRecords.value = res.meta.total
  } catch {
    loadMockTickets()
  } finally {
    ticketsLoading.value = false
  }
}

async function fetchCoupons() {
  couponsLoading.value = true
  try {
    const params: ListCouponsParams = {
      search: couponSearch.value || undefined,
      discount_type: couponTypeFilter.value === 'all' ? undefined : couponTypeFilter.value,
      is_active: couponStatusFilter.value === 'all' ? undefined : couponStatusFilter.value === 'active',
      expired: couponExpiryFilter.value === 'all' ? undefined : couponExpiryFilter.value === 'expired',
      trashed: couponTrashFilter.value === 'without' ? undefined : couponTrashFilter.value,
      sort_by: couponSortField.value,
      sort_dir: couponSortOrder.value,
      page: couponPage.value,
      per_page: couponPerPage.value,
    }
    const res = await getCoupons(params)
    coupons.value = res.data
    couponTotalRecords.value = res.meta.total
  } catch {
    loadMockCoupons()
  } finally {
    couponsLoading.value = false
  }
}

async function fetchAggregations() {
  aggregationsLoading.value = true
  try {
    const [ticketRes, couponRes] = await Promise.all([getTicketAggregations(), getCouponAggregations()])
    ticketAggregations.value = ticketRes.data
    couponAggregations.value = couponRes.data
  } catch {
    loadMockAggregations()
  } finally {
    aggregationsLoading.value = false
  }
}

function loadMockTickets() {
  tickets.value = [
    {
      id: 18,
      uuid: 'tkt_001',
      ticket_number: 'SUP-2026-0018',
      subject: 'Refund processed but credits were not restored',
      message_preview: 'Customer was refunded successfully, but credit balance remains reduced inside workspace.',
      status: 'in_progress',
      priority: 'high',
      category: 'billing',
      replies_count: 3,
      last_reply_at: '2026-04-04T09:38:00Z',
      resolved_at: null,
      closed_at: null,
      created_at: '2026-04-04T08:55:00Z',
      updated_at: '2026-04-04T09:38:00Z',
      deleted_at: null,
      user: { id: 1, name: 'Sara Ahmed', email: 'sara@klek.ai', avatar: null },
      assigned_agent: { id: 6, name: 'Nour Sayed', email: 'nour@klek.ai', avatar: null },
      user_subscription: { id: 21, status: 'active', billing_cycle: 'monthly', plan: { id: 3, name: 'Pro', slug: 'pro' } },
    },
    {
      id: 22,
      uuid: 'tkt_002',
      ticket_number: 'SUP-2026-0022',
      subject: 'Generated image queue is stuck on processing',
      message_preview: 'The request has been processing for over 40 minutes with no completion event.',
      status: 'waiting_reply',
      priority: 'urgent',
      category: 'ai_generation',
      replies_count: 5,
      last_reply_at: '2026-04-04T10:12:00Z',
      resolved_at: null,
      closed_at: null,
      created_at: '2026-04-04T07:20:00Z',
      updated_at: '2026-04-04T10:12:00Z',
      deleted_at: null,
      user: { id: 2, name: 'Omar Ali', email: 'omar@klek.ai', avatar: null },
      assigned_agent: null,
      user_subscription: { id: 22, status: 'active', billing_cycle: 'monthly', plan: { id: 2, name: 'Starter', slug: 'starter' } },
    },
    {
      id: 24,
      uuid: 'tkt_003',
      ticket_number: 'SUP-2026-0024',
      subject: 'Need invoice copy for accounting archive',
      message_preview: 'Customer requested signed invoice PDF for last Enterprise renewal.',
      status: 'resolved',
      priority: 'medium',
      category: 'documents',
      replies_count: 2,
      last_reply_at: '2026-04-03T16:44:00Z',
      resolved_at: '2026-04-03T17:02:00Z',
      closed_at: null,
      created_at: '2026-04-03T15:32:00Z',
      updated_at: '2026-04-03T17:02:00Z',
      deleted_at: null,
      user: { id: 3, name: 'Mona Khaled', email: 'mona@klek.ai', avatar: null },
      assigned_agent: { id: 3, name: 'Mona Khaled', email: 'mona@klek.ai', avatar: null },
      user_subscription: { id: 23, status: 'cancelled', billing_cycle: 'yearly', plan: { id: 4, name: 'Enterprise', slug: 'enterprise' } },
    },
    {
      id: 29,
      uuid: 'tkt_004',
      ticket_number: 'SUP-2026-0029',
      subject: 'Workspace owner can\'t update card details',
      message_preview: 'Billing page returns invalid state after SCA step completes.',
      status: 'open',
      priority: 'high',
      category: 'billing',
      replies_count: 1,
      last_reply_at: null,
      resolved_at: null,
      closed_at: null,
      created_at: '2026-04-04T10:05:00Z',
      updated_at: '2026-04-04T10:05:00Z',
      deleted_at: '2026-04-04T10:10:00Z',
      user: { id: 4, name: 'Karim Mostafa', email: 'karim@klek.ai', avatar: null },
      assigned_agent: null,
      user_subscription: null,
    },
  ]
  ticketTotalRecords.value = 148
}

function loadMockCoupons() {
  coupons.value = [
    {
      id: 3,
      code: 'SPRING10',
      name: 'Spring Promo',
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
    },
    {
      id: 8,
      code: 'VIP20',
      name: 'VIP Recovery',
      discount_type: 'fixed_amount',
      discount_value: 20,
      currency: 'USD',
      is_active: true,
      max_uses: 120,
      max_uses_per_user: 1,
      times_used: 61,
      usage_percentage: 50.8,
      starts_at: '2026-03-20T00:00:00Z',
      expires_at: '2026-04-15T23:59:00Z',
      is_expired: false,
      applicable_plan: { id: 4, name: 'Enterprise', slug: 'enterprise' },
      created_at: '2026-03-20T00:00:00Z',
      deleted_at: null,
    },
    {
      id: 11,
      code: 'CREDIT25',
      name: 'Support Recovery Credits',
      discount_type: 'credits',
      discount_value: 25,
      currency: 'USD',
      is_active: false,
      max_uses: 40,
      max_uses_per_user: 1,
      times_used: 17,
      usage_percentage: 42.5,
      starts_at: '2026-02-01T00:00:00Z',
      expires_at: '2026-03-01T00:00:00Z',
      is_expired: true,
      applicable_plan: null,
      created_at: '2026-02-01T00:00:00Z',
      deleted_at: null,
    },
    {
      id: 14,
      code: 'RESTART15',
      name: 'Restart Flow',
      discount_type: 'percentage',
      discount_value: 15,
      currency: 'USD',
      is_active: true,
      max_uses: 80,
      max_uses_per_user: 1,
      times_used: 23,
      usage_percentage: 28.7,
      starts_at: '2026-04-02T00:00:00Z',
      expires_at: '2026-04-25T23:59:00Z',
      is_expired: false,
      applicable_plan: { id: 2, name: 'Starter', slug: 'starter' },
      created_at: '2026-04-02T00:00:00Z',
      deleted_at: '2026-04-03T00:00:00Z',
    },
  ]
  couponTotalRecords.value = 64
}

function loadMockAggregations() {
  ticketAggregations.value = {
    summary: {
      total_tickets: 148,
      open_count: 34,
      in_progress_count: 29,
      waiting_reply_count: 18,
      resolved_count: 51,
      closed_count: 16,
      unassigned_active_count: 11,
    },
    by_priority: [
      { priority: 'low', count: 18, active_count: 3 },
      { priority: 'medium', count: 44, active_count: 10 },
      { priority: 'high', count: 59, active_count: 24 },
      { priority: 'urgent', count: 27, active_count: 15 },
    ],
    by_category: [
      { category: 'billing', count: 56 },
      { category: 'ai_generation', count: 33 },
      { category: 'account', count: 21 },
      { category: 'documents', count: 14 },
    ],
    agent_performance: [
      { id: 6, name: 'Nour Sayed', email: 'nour@klek.ai', total_assigned: 31, resolved_count: 19, active_count: 12, avg_resolution_hours: 5.4 },
      { id: 3, name: 'Mona Khaled', email: 'mona@klek.ai', total_assigned: 24, resolved_count: 17, active_count: 7, avg_resolution_hours: 4.2 },
      { id: 2, name: 'Omar Ali', email: 'omar@klek.ai', total_assigned: 17, resolved_count: 9, active_count: 8, avg_resolution_hours: 6.8 },
    ],
    daily_trend: [
      { date: '2026-03-29', created: 14, resolved: 9 },
      { date: '2026-03-30', created: 18, resolved: 10 },
      { date: '2026-03-31', created: 16, resolved: 12 },
      { date: '2026-04-01', created: 21, resolved: 13 },
      { date: '2026-04-02', created: 24, resolved: 15 },
      { date: '2026-04-03', created: 19, resolved: 18 },
      { date: '2026-04-04', created: 22, resolved: 11 },
    ],
    avg_first_response: { avg_first_response_minutes: 42 },
  }

  couponAggregations.value = {
    summary: {
      total_coupons: 64,
      active_count: 41,
      inactive_count: 23,
      total_uses: 612,
    },
    expired_count: 13,
    by_type: [
      { discount_type: 'percentage', count: 31, total_uses: 318 },
      { discount_type: 'fixed_amount', count: 21, total_uses: 201 },
      { discount_type: 'credits', count: 12, total_uses: 93 },
    ],
    top_coupons: [
      { id: 3, code: 'SPRING10', name: 'Spring Promo', discount_type: 'percentage', discount_value: 10, times_used: 148 },
      { id: 8, code: 'VIP20', name: 'VIP Recovery', discount_type: 'fixed_amount', discount_value: 20, times_used: 61 },
      { id: 14, code: 'RESTART15', name: 'Restart Flow', discount_type: 'percentage', discount_value: 15, times_used: 23 },
    ],
  }
}

onMounted(async () => {
  await Promise.all([fetchTickets(), fetchCoupons(), fetchAggregations()])
})

let ticketSearchTimeout: ReturnType<typeof setTimeout>
watch(ticketSearch, () => {
  clearTimeout(ticketSearchTimeout)
  ticketSearchTimeout = setTimeout(() => {
    ticketPage.value = 1
    fetchTickets()
  }, 300)
})

let couponSearchTimeout: ReturnType<typeof setTimeout>
watch(couponSearch, () => {
  clearTimeout(couponSearchTimeout)
  couponSearchTimeout = setTimeout(() => {
    couponPage.value = 1
    fetchCoupons()
  }, 300)
})

watch([ticketStatusFilter, ticketPriorityFilter, ticketTrashFilter], () => {
  ticketPage.value = 1
  fetchTickets()
})

watch([couponTypeFilter, couponStatusFilter, couponExpiryFilter, couponTrashFilter], () => {
  couponPage.value = 1
  fetchCoupons()
})

function onTicketPage(event: { first: number; rows: number }) {
  ticketPage.value = Math.floor(event.first / event.rows) + 1
  ticketPerPage.value = event.rows
  fetchTickets()
}

function onCouponPage(event: { first: number; rows: number }) {
  couponPage.value = Math.floor(event.first / event.rows) + 1
  couponPerPage.value = event.rows
  fetchCoupons()
}

function onTicketSort(event: DataTableSortEvent) {
  if (typeof event.sortField !== 'string') return
  ticketSortField.value = event.sortField as NonNullable<ListSupportTicketsParams['sort_by']>
  ticketSortOrder.value = event.sortOrder === 1 ? 'asc' : 'desc'
  fetchTickets()
}

function onCouponSort(event: DataTableSortEvent) {
  if (typeof event.sortField !== 'string') return
  couponSortField.value = event.sortField as NonNullable<ListCouponsParams['sort_by']>
  couponSortOrder.value = event.sortOrder === 1 ? 'asc' : 'desc'
  fetchCoupons()
}

function openTicketDetail(ticket: SupportTicket) {
  ticketDetailId.value = ticket.id
  showTicketDetail.value = true
}

function openCouponDetail(coupon: Coupon) {
  couponDetailId.value = coupon.id
  showCouponDetail.value = true
}

function openCouponCreate() {
  couponFormId.value = null
  showCouponForm.value = true
}

function openCouponEdit(id: number) {
  couponFormId.value = id
  showCouponForm.value = true
}

function handleCouponEditFromDrawer(id: number) {
  showCouponDetail.value = false
  openCouponEdit(id)
}

async function handleCouponToggle(coupon: Coupon) {
  couponActionLoadingId.value = coupon.id
  try {
    await toggleCoupon(coupon.id)
    await Promise.all([fetchCoupons(), fetchAggregations()])
  } catch {
    // noop
  } finally {
    couponActionLoadingId.value = null
  }
}

function handleCouponSaved() {
  showCouponForm.value = false
  Promise.all([fetchCoupons(), fetchAggregations()])
}

function onEntityUpdated() {
  Promise.all([fetchTickets(), fetchCoupons(), fetchAggregations()])
}

const overviewCards = computed(() => {
  const ticketSummary = ticketAggregations.value?.summary
  const couponSummary = couponAggregations.value?.summary
  const avgFirstResponse = ticketAggregations.value?.avg_first_response.avg_first_response_minutes
  if (!ticketSummary || !couponSummary) return []

  return [
    {
      label: t('support.openWorkload'),
      value: (ticketSummary.open_count + ticketSummary.in_progress_count + ticketSummary.waiting_reply_count).toLocaleString(),
      sub: t('support.unassignedCount', { count: ticketSummary.unassigned_active_count }),
      tone: '#0ea5e9',
      icon: 'pi pi-inbox',
    },
    {
      label: t('support.resolvedToday'),
      value: ticketSummary.resolved_count.toLocaleString(),
      sub: avgFirstResponse ? t('support.minFirstResponse', { min: avgFirstResponse }) : t('support.responseTimePending'),
      tone: '#10b981',
      icon: 'pi pi-check-circle',
    },
    {
      label: t('support.activeCouponsLabel'),
      value: couponSummary.active_count.toLocaleString(),
      sub: t('support.expiredCouponsCount', { count: couponAggregations.value?.expired_count || 0 }),
      tone: '#f59e0b',
      icon: 'pi pi-ticket',
    },
    {
      label: t('support.couponUsage'),
      value: couponSummary.total_uses.toLocaleString(),
      sub: t('support.totalCodesCount', { count: couponSummary.total_coupons }),
      tone: '#ef4444',
      icon: 'pi pi-bolt',
    },
  ]
})

function gridColor() {
  return getComputedStyle(document.documentElement).getPropertyValue('--card-border').trim() || '#e2e8f0'
}

function mutedText() {
  return getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#94a3b8'
}

const ticketTrendData = computed(() => {
  const items = ticketAggregations.value?.daily_trend || []
  return {
    labels: items.map(item => formatShortDate(item.date)),
    datasets: [
      {
        label: t('support.createdTrend'),
        data: items.map(item => item.created),
        borderColor: '#0ea5e9',
        backgroundColor: 'rgba(14, 165, 233, 0.08)',
        fill: true,
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 2,
      },
      {
        label: t('support.resolvedTrend'),
        data: items.map(item => item.resolved),
        borderColor: '#10b981',
        backgroundColor: 'rgba(16, 185, 129, 0.04)',
        fill: false,
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 2,
      },
    ],
  }
})

const ticketTrendOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { labels: { color: mutedText(), usePointStyle: true, pointStyle: 'circle' } } },
  scales: {
    x: { grid: { color: gridColor() }, ticks: { color: mutedText(), font: { size: 10 } } },
    y: { grid: { color: gridColor() }, ticks: { color: mutedText(), font: { size: 10 } }, beginAtZero: true },
  },
}))

const ticketPriorityData = computed(() => {
  const items = ticketAggregations.value?.by_priority || []
  return {
    labels: items.map(item => capitalize(item.priority)),
    datasets: [{ data: items.map(item => item.count), backgroundColor: ['#94a3b8', '#0ea5e9', '#f59e0b', '#ef4444'], borderWidth: 0, hoverOffset: 6 }],
  }
})

const ticketPriorityChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '70%',
  plugins: {
    legend: { position: 'bottom' as const, labels: { usePointStyle: true, pointStyle: 'circle', padding: 12, font: { size: 11 } } },
  },
}

const couponTypeData = computed(() => {
  const items = couponAggregations.value?.by_type || []
  return {
    labels: items.map(item => capitalize(item.discount_type.replace('_', ' '))),
    datasets: [{ data: items.map(item => item.total_uses), backgroundColor: ['#f59e0b', '#10b981', '#8b5cf6'], borderRadius: 6, barThickness: 18, borderWidth: 0 }],
  }
})

const couponTypeChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y' as const,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { color: gridColor() }, ticks: { color: mutedText(), font: { size: 10 } }, beginAtZero: true },
    y: { grid: { display: false }, ticks: { color: mutedText(), font: { size: 10 } } },
  },
}))

function ticketStatusSeverity(status: string): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  const map: Record<string, 'success' | 'warn' | 'danger' | 'info' | 'secondary'> = {
    open: 'warn',
    in_progress: 'info',
    waiting_reply: 'secondary',
    resolved: 'success',
    closed: 'secondary',
  }
  return map[status] || 'secondary'
}

function ticketPrioritySeverity(priority: string): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  const map: Record<string, 'success' | 'warn' | 'danger' | 'info' | 'secondary'> = {
    low: 'secondary',
    medium: 'info',
    high: 'warn',
    urgent: 'danger',
  }
  return map[priority] || 'secondary'
}

function couponStateSeverity(coupon: Coupon): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  if (coupon.deleted_at) return 'secondary'
  if (coupon.is_expired) return 'danger'
  if (coupon.is_active) return 'success'
  return 'warn'
}

function couponStateLabel(coupon: Coupon) {
  if (coupon.deleted_at) return 'Trashed'
  if (coupon.is_expired) return 'Expired'
  if (coupon.is_active) return 'Active'
  return 'Inactive'
}

function discountLabel(coupon: Coupon) {
  if (coupon.discount_type === 'percentage') return `${coupon.discount_value}%`
  if (coupon.discount_type === 'credits') return `${coupon.discount_value} credits`
  return formatMoney(coupon.discount_value, coupon.currency)
}

function formatMoney(value: number, currency: string = 'USD') {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    maximumFractionDigits: value % 1 === 0 ? 0 : 2,
  }).format(value)
}

function formatDateTime(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatDate(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatShortDate(value: string) {
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function initials(name: string) {
  return name.split(' ').map(item => item[0]).join('').slice(0, 2)
}

function shortText(value: string | null, max = 90) {
  if (!value) return '—'
  return value.length > max ? `${value.slice(0, max)}...` : value
}

function capitalize(value: string) {
  return value.charAt(0).toUpperCase() + value.slice(1)
}
</script>

<template>
  <div class="support-page">
    <div class="page-toolbar">
      <div>
        <h1 class="page-title">{{ t('support.supportOperations') }}</h1>
        <p class="page-subtitle">{{ t('support.supportSubtitle') }}</p>
      </div>
      <Button v-if="activeTab === 'coupons'" :label="t('support.newCoupon')" icon="pi pi-plus" size="small" @click="openCouponCreate" />
    </div>

    <Tabs v-model:value="activeTab" class="support-tabs">
      <TabList>
        <Tab value="overview"><i class="pi pi-chart-line" /> <span>{{ t('support.overviewTab') }}</span></Tab>
        <Tab value="tickets"><i class="pi pi-inbox" /> <span>{{ t('support.ticketsTab') }}</span></Tab>
        <Tab value="coupons"><i class="pi pi-ticket" /> <span>{{ t('support.couponsTab') }}</span></Tab>
      </TabList>

      <TabPanels>
        <TabPanel value="overview">
          <div class="summary-grid">
            <article v-for="card in overviewCards" :key="card.label" class="summary-card">
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

          <div v-if="!aggregationsLoading && ticketAggregations && couponAggregations" class="analytics-grid">
            <section class="chart-card chart-wide">
              <div class="card-head">
                <div>
                  <h3>{{ t('support.ticketTrend') }}</h3>
                  <p>Daily creation volume versus resolved throughput.</p>
                </div>
              </div>
              <div class="chart-shell tall">
                <Chart type="line" :data="ticketTrendData" :options="ticketTrendOptions" />
              </div>
            </section>

            <section class="chart-card">
              <div class="card-head">
                <div>
                  <h3>{{ t('support.priorityMix') }}</h3>
                  <p>Distribution of ticket urgency.</p>
                </div>
              </div>
              <div class="chart-shell">
                <Chart type="doughnut" :data="ticketPriorityData" :options="ticketPriorityChartOptions" />
              </div>
            </section>

            <section class="chart-card">
              <div class="card-head">
                <div>
                  <h3>{{ t('support.couponUsageByType') }}</h3>
                  <p>Operational lift generated by each discount model.</p>
                </div>
              </div>
              <div class="chart-shell">
                <Chart type="bar" :data="couponTypeData" :options="couponTypeChartOptions" />
              </div>
            </section>

            <section class="insight-card">
              <div class="insight-block">
                <div class="card-head compact-head">
                  <h3>{{ t('support.agentPerformance') }}</h3>
                </div>
                <div class="mini-list">
                  <div v-for="agent in ticketAggregations.agent_performance.slice(0, 4)" :key="agent.id" class="mini-row">
                    <div class="mini-avatar">{{ initials(agent.name) }}</div>
                    <div class="mini-copy">
                      <span class="mini-title">{{ agent.name }}</span>
                      <span class="mini-sub">{{ agent.resolved_count }} resolved · {{ agent.active_count }} active</span>
                    </div>
                    <strong class="mini-metric">{{ agent.avg_resolution_hours ? `${agent.avg_resolution_hours}h` : '—' }}</strong>
                  </div>
                </div>
              </div>

              <div class="insight-block">
                <div class="card-head compact-head">
                  <h3>{{ t('support.topCoupons') }}</h3>
                </div>
                <div class="health-list">
                  <div v-for="coupon in couponAggregations.top_coupons.slice(0, 4)" :key="coupon.id" class="health-row">
                    <span>{{ coupon.code }}</span>
                    <strong>{{ coupon.times_used }} uses</strong>
                  </div>
                </div>
              </div>
            </section>
          </div>
        </TabPanel>

        <TabPanel value="tickets">
          <div class="filters-bar">
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText v-model="ticketSearch" :placeholder="t('support.searchTickets')" size="small" class="filter-input" />
            </span>
            <Select v-model="ticketStatusFilter" :options="ticketStatusOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <Select v-model="ticketPriorityFilter" :options="ticketPriorityOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <Select v-model="ticketTrashFilter" :options="trashOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <span class="filter-count">{{ ticketTotalRecords }} {{ t('support.ticketsTab').toLowerCase() }}</span>
          </div>

          <div class="cards-list d-mobile">
            <article v-for="ticket in tickets" :key="ticket.id" class="entity-card" @click="openTicketDetail(ticket)">
              <div class="entity-head">
                <div>
                  <div class="entity-title-row">
                    <span class="entity-id">{{ ticket.ticket_number }}</span>
                    <Tag :value="ticket.status" :severity="ticketStatusSeverity(ticket.status)" class="mini-tag" />
                  </div>
                  <span class="entity-user">{{ ticket.user.name }} · {{ ticket.category || 'general' }}</span>
                </div>
                <Tag :value="ticket.priority" :severity="ticketPrioritySeverity(ticket.priority)" class="mini-tag" />
              </div>

              <p class="entity-desc">{{ shortText(ticket.message_preview) }}</p>

              <div class="meta-grid">
                <span><i class="pi pi-user" /> {{ ticket.assigned_agent?.name || 'Unassigned' }}</span>
                <span><i class="pi pi-comments" /> {{ ticket.replies_count }} replies</span>
                <span><i class="pi pi-box" /> {{ ticket.user_subscription?.plan?.name || 'No plan' }}</span>
                <span><i class="pi pi-calendar" /> {{ formatDateTime(ticket.last_reply_at || ticket.created_at) }}</span>
              </div>

              <div class="card-actions" @click.stop>
                <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openTicketDetail(ticket)" />
              </div>
            </article>
          </div>

          <div class="table-card d-desktop">
            <DataTable
              :value="tickets"
              :loading="ticketsLoading"
              :rows="ticketPerPage"
              :totalRecords="ticketTotalRecords"
              :lazy="true"
              :paginator="true"
              :rowsPerPageOptions="[10, 15, 25, 50]"
              paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
              sortMode="single"
              :sortField="ticketSortField"
              :sortOrder="ticketSortOrder === 'asc' ? 1 : -1"
              @sort="onTicketSort"
              @page="onTicketPage"
              stripedRows
              size="small"
              scrollable
              class="entity-table"
              dataKey="id"
            >
              <Column field="ticket_number" header="Ticket" sortable style="min-width: 260px">
                <template #body="{ data }">
                  <div class="name-cell" @click="openTicketDetail(data)">
                    <div class="name-icon support-icon"><i class="pi pi-inbox" /></div>
                    <div class="name-copy">
                      <span class="name-title">{{ data.subject }}</span>
                      <span class="name-sub">{{ data.ticket_number }} · {{ data.user.name }}</span>
                    </div>
                  </div>
                </template>
              </Column>
              <Column field="status" header="Status" sortable style="min-width: 110px">
                <template #body="{ data }">
                  <Tag :value="data.status" :severity="ticketStatusSeverity(data.status)" class="mini-tag" />
                </template>
              </Column>
              <Column field="priority" header="Priority" sortable style="min-width: 100px">
                <template #body="{ data }">
                  <Tag :value="data.priority" :severity="ticketPrioritySeverity(data.priority)" class="mini-tag" />
                </template>
              </Column>
              <Column field="category" header="Category" style="min-width: 110px">
                <template #body="{ data }">{{ data.category || '—' }}</template>
              </Column>
              <Column field="assigned_agent" header="Assigned" style="min-width: 130px">
                <template #body="{ data }">{{ data.assigned_agent?.name || 'Unassigned' }}</template>
              </Column>
              <Column field="replies_count" header="Replies" sortable style="min-width: 80px" />
              <Column field="last_reply_at" header="Last Reply" sortable style="min-width: 130px">
                <template #body="{ data }">{{ formatDateTime(data.last_reply_at || data.created_at) }}</template>
              </Column>
              <Column header="Actions" style="width: 80px">
                <template #body="{ data }">
                  <div class="row-actions">
                    <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openTicketDetail(data)" />
                  </div>
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>

        <TabPanel value="coupons">
          <div class="filters-bar">
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText v-model="couponSearch" :placeholder="t('support.searchCoupons')" size="small" class="filter-input" />
            </span>
            <Select v-model="couponTypeFilter" :options="couponTypeOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <Select v-model="couponStatusFilter" :options="couponStatusOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <Select v-model="couponExpiryFilter" :options="couponExpiryOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <Select v-model="couponTrashFilter" :options="trashOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <span class="filter-count">{{ couponTotalRecords }} {{ t('support.couponsTab').toLowerCase() }}</span>
          </div>

          <div class="cards-list d-mobile">
            <article v-for="coupon in coupons" :key="coupon.id" class="entity-card" @click="openCouponDetail(coupon)">
              <div class="entity-head">
                <div>
                  <div class="entity-title-row">
                    <span class="entity-id mono">{{ coupon.code }}</span>
                    <Tag :value="couponStateLabel(coupon)" :severity="couponStateSeverity(coupon)" class="mini-tag" />
                  </div>
                  <span class="entity-user">{{ coupon.name }} · {{ coupon.applicable_plan?.name || 'All plans' }}</span>
                </div>
                <strong class="amount-strong">{{ discountLabel(coupon) }}</strong>
              </div>

              <p class="entity-desc">{{ coupon.discount_type }} · {{ coupon.times_used }} redemptions · valid until {{ formatDate(coupon.expires_at) }}</p>

              <div class="meta-grid">
                <span><i class="pi pi-chart-bar" /> {{ coupon.usage_percentage?.toFixed(1) || 0 }}% usage</span>
                <span><i class="pi pi-users" /> {{ coupon.max_uses_per_user ?? '∞' }} / user</span>
                <span><i class="pi pi-calendar" /> {{ formatDate(coupon.starts_at) }}</span>
                <span><i class="pi pi-trash" /> {{ coupon.deleted_at ? 'Trashed' : 'Active record' }}</span>
              </div>

              <div class="card-actions" @click.stop>
                <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openCouponDetail(coupon)" />
                <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" @click="openCouponEdit(coupon.id)" />
                <Button
                  :icon="coupon.is_active ? 'pi pi-pause' : 'pi pi-play'"
                  severity="secondary"
                  text
                  rounded
                  size="small"
                  :loading="couponActionLoadingId === coupon.id"
                  @click="handleCouponToggle(coupon)"
                />
              </div>
            </article>
          </div>

          <div class="table-card d-desktop">
            <DataTable
              :value="coupons"
              :loading="couponsLoading"
              :rows="couponPerPage"
              :totalRecords="couponTotalRecords"
              :lazy="true"
              :paginator="true"
              :rowsPerPageOptions="[10, 15, 25, 50]"
              paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
              sortMode="single"
              :sortField="couponSortField"
              :sortOrder="couponSortOrder === 'asc' ? 1 : -1"
              @sort="onCouponSort"
              @page="onCouponPage"
              stripedRows
              size="small"
              scrollable
              class="entity-table"
              dataKey="id"
            >
              <Column field="code" header="Coupon" sortable style="min-width: 230px">
                <template #body="{ data }">
                  <div class="name-cell" @click="openCouponDetail(data)">
                    <div class="name-icon coupon-icon"><i class="pi pi-ticket" /></div>
                    <div class="name-copy">
                      <span class="name-title mono">{{ data.code }}</span>
                      <span class="name-sub">{{ data.name }} · {{ data.applicable_plan?.name || 'All plans' }}</span>
                    </div>
                  </div>
                </template>
              </Column>
              <Column field="discount_type" header="Type" sortable style="min-width: 100px">
                <template #body="{ data }">{{ data.discount_type }}</template>
              </Column>
              <Column field="discount_value" header="Discount" sortable style="min-width: 100px">
                <template #body="{ data }">
                  <span class="metric-strong">{{ discountLabel(data) }}</span>
                </template>
              </Column>
              <Column field="times_used" header="Used" sortable style="min-width: 80px" />
              <Column field="expires_at" header="Expires" sortable style="min-width: 120px">
                <template #body="{ data }">{{ formatDate(data.expires_at) }}</template>
              </Column>
              <Column field="is_active" header="State" style="min-width: 90px">
                <template #body="{ data }">
                  <Tag :value="couponStateLabel(data)" :severity="couponStateSeverity(data)" class="mini-tag" />
                </template>
              </Column>
              <Column header="Actions" style="width: 118px">
                <template #body="{ data }">
                  <div class="row-actions">
                    <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openCouponDetail(data)" />
                    <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" @click="openCouponEdit(data.id)" />
                    <Button
                      :icon="data.is_active ? 'pi pi-pause' : 'pi pi-play'"
                      severity="secondary"
                      text
                      rounded
                      size="small"
                      :loading="couponActionLoadingId === data.id"
                      @click="handleCouponToggle(data)"
                    />
                  </div>
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <SupportTicketDetailDrawer v-model:visible="showTicketDetail" :ticket-id="ticketDetailId" @updated="onEntityUpdated" />
    <CouponFormDialog v-model:visible="showCouponForm" :coupon-id="couponFormId" @saved="handleCouponSaved" />
    <CouponDetailDrawer v-model:visible="showCouponDetail" :coupon-id="couponDetailId" @updated="onEntityUpdated" @edit="handleCouponEditFromDrawer" />
  </div>
</template>

<style scoped>
.support-page { display: flex; flex-direction: column; gap: 16px; }
.page-toolbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.page-title { margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--text-primary); }
.page-subtitle { margin: 4px 0 0; font-size: 0.74rem; color: var(--text-muted); max-width: 720px; }
:deep(.support-tabs .p-tablist) { background: transparent; border: none; gap: 4px; }
:deep(.support-tabs .p-tab) { padding: 9px 12px !important; border-radius: 12px !important; border: 1px solid transparent !important; background: transparent !important; color: var(--text-muted) !important; font-size: 0.74rem !important; }
:deep(.support-tabs .p-tab-active) { color: var(--text-primary) !important; border-color: var(--card-border) !important; background: var(--card-bg) !important; }
:deep(.support-tabs .p-tabpanels) { background: transparent; padding: 14px 0 0 !important; }
.summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
@media (min-width: 1024px) { .summary-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.summary-card { display: flex; gap: 10px; padding: 12px; border-radius: 14px; border: 1px solid var(--card-border); background: var(--card-bg); }
.summary-icon { width: 38px; height: 38px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.summary-copy { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.summary-label { font-size: 0.66rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
.summary-value { font-size: 1rem; color: var(--text-primary); }
.summary-sub { font-size: 0.66rem; color: var(--text-muted); }
.analytics-grid { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 12px; }
@media (min-width: 1024px) { .analytics-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .chart-wide { grid-column: span 2; } }
.chart-card,.insight-card,.table-card { border: 1px solid var(--card-border); border-radius: 14px; background: var(--card-bg); }
.chart-card,.insight-card { padding: 12px; }
.card-head { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; margin-bottom: 12px; }
.card-head h3,.compact-head h3 { margin: 0; font-size: 0.86rem; color: var(--text-primary); }
.card-head p { margin: 3px 0 0; font-size: 0.68rem; color: var(--text-muted); }
.chart-shell { height: 250px; }
.chart-shell.tall { height: 300px; }
.insight-card { display: grid; grid-template-columns: 1fr; gap: 12px; }
.mini-list { display: flex; flex-direction: column; gap: 10px; }
.mini-row { display: grid; grid-template-columns: auto 1fr auto; gap: 8px; align-items: center; }
.mini-avatar { width: 32px; height: 32px; border-radius: 10px; background: linear-gradient(135deg, #0ea5e9, #0369a1); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.68rem; font-weight: 700; }
.mini-copy { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.mini-title { font-size: 0.72rem; font-weight: 600; color: var(--text-primary); }
.mini-sub { font-size: 0.64rem; color: var(--text-muted); }
.mini-metric { font-size: 0.72rem; color: var(--text-primary); }
.health-list { display: flex; flex-direction: column; gap: 8px; }
.health-row { display: flex; justify-content: space-between; gap: 8px; padding: 9px 10px; border-radius: 10px; background: var(--hover-bg); font-size: 0.7rem; color: var(--text-secondary); }
.health-row strong { color: var(--text-primary); }
.filters-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
.filter-search { display: inline-flex; align-items: center; gap: 8px; min-width: min(100%, 300px); flex: 1 1 220px; padding: 0 10px; height: 36px; border: 1px solid var(--card-border); border-radius: 12px; background: var(--card-bg); color: var(--text-muted); }
.filter-input { width: 100%; }
.filter-select { min-width: 148px; }
.filter-count { margin-left: auto; font-size: 0.68rem; color: var(--text-muted); }
.cards-list { display: flex; flex-direction: column; gap: 10px; }
.entity-card { padding: 12px; border-radius: 14px; border: 1px solid var(--card-border); background: var(--card-bg); display: flex; flex-direction: column; gap: 10px; }
.entity-head { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; }
.entity-title-row { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.entity-id { font-size: 0.72rem; font-weight: 700; color: var(--text-primary); }
.mono { font-family: monospace; }
.entity-user { font-size: 0.66rem; color: var(--text-muted); }
.entity-desc { margin: 0; font-size: 0.72rem; line-height: 1.5; color: var(--text-primary); }
.amount-strong { font-size: 0.8rem; color: var(--text-primary); }
.meta-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 10px; }
.meta-grid span { display: inline-flex; gap: 6px; align-items: center; min-width: 0; font-size: 0.64rem; color: var(--text-muted); }
.card-actions,.row-actions { display: flex; align-items: center; gap: 4px; justify-content: flex-end; }
.table-card { overflow: hidden; }
.entity-table { font-size: 0.74rem; }
.name-cell { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.name-icon { width: 34px; height: 34px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; }
.support-icon { background: linear-gradient(135deg, #0ea5e9, #0369a1); }
.coupon-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
.name-copy { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.name-title { font-size: 0.72rem; font-weight: 600; color: var(--text-primary); line-height: 1.35; }
.name-sub { font-size: 0.62rem; color: var(--text-muted); }
.metric-strong { font-weight: 600; color: var(--text-primary); }
.mini-tag { font-size: 0.56rem !important; padding: 2px 7px !important; }
.d-desktop { display: none; }
@media (min-width: 960px) { .d-desktop { display: block; } .d-mobile { display: none; } }
@media (max-width: 959px) { .filter-count { width: 100%; margin-left: 0; } }
</style>