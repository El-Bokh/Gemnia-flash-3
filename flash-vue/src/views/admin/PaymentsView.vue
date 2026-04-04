<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { deleteInvoice, getInvoiceAggregations, getInvoices, restoreInvoice } from '@/services/invoiceService'
import { deletePayment, getPaymentAggregations, getPayments, restorePayment } from '@/services/paymentService'
import type {
  Invoice,
  InvoiceAggregations,
  ListInvoicesParams,
  ListPaymentsParams,
  Payment,
  PaymentAggregations,
} from '@/types/payments'
import Chart from 'primevue/chart'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import PaymentDetailDrawer from '@/components/payments/PaymentDetailDrawer.vue'
import InvoiceDetailDrawer from '@/components/payments/InvoiceDetailDrawer.vue'

const { t } = useI18n()

const activeTab = ref('overview')

const paymentsLoading = ref(true)
const invoicesLoading = ref(true)
const aggregationsLoading = ref(true)
const actionLoading = ref(false)

const payments = ref<Payment[]>([])
const invoices = ref<Invoice[]>([])
const paymentAggregations = ref<PaymentAggregations | null>(null)
const invoiceAggregations = ref<InvoiceAggregations | null>(null)

const paymentTotalRecords = ref(0)
const invoiceTotalRecords = ref(0)
const paymentPage = ref(1)
const invoicePage = ref(1)
const paymentPerPage = ref(15)
const invoicePerPage = ref(15)

const paymentSearch = ref('')
const invoiceSearch = ref('')
const paymentStatusFilter = ref<'all' | ListPaymentsParams['status']>('all')
const paymentGatewayFilter = ref('')
const paymentTrashFilter = ref<'without' | 'with' | 'only'>('without')
const invoiceStatusFilter = ref<'all' | ListInvoicesParams['status']>('all')
const invoiceTrashFilter = ref<'without' | 'with' | 'only'>('without')
const invoiceOverdueFilter = ref(false)

const paymentSortField = ref<ListPaymentsParams['sort_by']>('created_at')
const paymentSortOrder = ref<'asc' | 'desc'>('desc')
const invoiceSortField = ref<ListInvoicesParams['sort_by']>('created_at')
const invoiceSortOrder = ref<'asc' | 'desc'>('desc')

const showPaymentDetail = ref(false)
const paymentDetailId = ref<number | null>(null)
const showInvoiceDetail = ref(false)
const invoiceDetailId = ref<number | null>(null)

const showDeleteConfirm = ref(false)
const deleteTarget = ref<{ kind: 'payment' | 'invoice'; id: number; label: string } | null>(null)

const paymentStatusOptions = computed(() => [
  { label: t('payments.allStatus'), value: 'all' },
  { label: t('payments.pending'), value: 'pending' },
  { label: t('payments.completed'), value: 'completed' },
  { label: t('payments.failed'), value: 'failed' },
  { label: t('payments.refundedStatus'), value: 'refunded' },
  { label: t('payments.partiallyRefunded'), value: 'partially_refunded' },
  { label: t('payments.cancelled'), value: 'cancelled' },
  { label: t('payments.disputed'), value: 'disputed' },
] as Array<{ label: string; value: 'all' | NonNullable<ListPaymentsParams['status']> }>)

const invoiceStatusOptions = computed(() => [
  { label: t('payments.allStatus'), value: 'all' },
  { label: t('payments.draft'), value: 'draft' },
  { label: t('payments.issued'), value: 'issued' },
  { label: t('payments.paid'), value: 'paid' },
  { label: t('payments.overdueStatus'), value: 'overdue' },
  { label: t('payments.cancelled'), value: 'cancelled' },
  { label: t('payments.refundedStatus'), value: 'refunded' },
] as Array<{ label: string; value: 'all' | NonNullable<ListInvoicesParams['status']> }>)

const trashOptions = computed(() => [
  { label: t('payments.activeOnly'), value: 'without' },
  { label: t('payments.withTrashed'), value: 'with' },
  { label: t('payments.onlyTrashed'), value: 'only' },
] as Array<{ label: string; value: 'without' | 'with' | 'only' }>)

const gatewayOptions = computed(() => {
  const values = [
    ...payments.value.map(item => item.payment_gateway),
    ...(paymentAggregations.value?.by_gateway || []).map(item => item.payment_gateway),
  ]
  const unique = Array.from(new Set(values.filter(Boolean)))
  return [{ label: t('payments.allGateways'), value: '' }, ...unique.map(value => ({ label: value, value }))]
})

async function fetchPayments() {
  paymentsLoading.value = true
  try {
    const params: ListPaymentsParams = {
      search: paymentSearch.value || undefined,
      status: paymentStatusFilter.value === 'all' ? undefined : paymentStatusFilter.value,
      payment_gateway: paymentGatewayFilter.value || undefined,
      trashed: paymentTrashFilter.value === 'without' ? undefined : paymentTrashFilter.value,
      sort_by: paymentSortField.value,
      sort_dir: paymentSortOrder.value,
      page: paymentPage.value,
      per_page: paymentPerPage.value,
    }
    const res = await getPayments(params)
    payments.value = res.data
    paymentTotalRecords.value = res.meta.total
  } catch {
    loadMockPayments()
  } finally {
    paymentsLoading.value = false
  }
}

async function fetchInvoices() {
  invoicesLoading.value = true
  try {
    const params: ListInvoicesParams = {
      search: invoiceSearch.value || undefined,
      status: invoiceStatusFilter.value === 'all' ? undefined : invoiceStatusFilter.value,
      overdue: invoiceOverdueFilter.value || undefined,
      trashed: invoiceTrashFilter.value === 'without' ? undefined : invoiceTrashFilter.value,
      sort_by: invoiceSortField.value,
      sort_dir: invoiceSortOrder.value,
      page: invoicePage.value,
      per_page: invoicePerPage.value,
    }
    const res = await getInvoices(params)
    invoices.value = res.data
    invoiceTotalRecords.value = res.meta.total
  } catch {
    loadMockInvoices()
  } finally {
    invoicesLoading.value = false
  }
}

async function fetchAggregations() {
  aggregationsLoading.value = true
  try {
    const [paymentsRes, invoicesRes] = await Promise.all([getPaymentAggregations(), getInvoiceAggregations()])
    paymentAggregations.value = paymentsRes.data
    invoiceAggregations.value = invoicesRes.data
  } catch {
    loadMockAggregations()
  } finally {
    aggregationsLoading.value = false
  }
}

function loadMockPayments() {
  payments.value = [
    { id: 501, uuid: 'pay_001', payment_gateway: 'stripe', gateway_payment_id: 'pi_001', status: 'completed', amount: 49, discount_amount: 5, tax_amount: 2.2, net_amount: 46.2, currency: 'USD', payment_method: 'card', description: 'Pro monthly renewal', refunded_amount: 0, refunded_at: null, paid_at: '2026-04-04T08:30:00Z', created_at: '2026-04-04T08:28:00Z', updated_at: '2026-04-04T08:30:00Z', deleted_at: null, user: { id: 1, name: 'Sara Ahmed', email: 'sara@klek.ai', avatar: null }, subscription: { id: 21, status: 'active', plan: { id: 3, name: 'Pro', slug: 'pro' } }, coupon: { id: 3, code: 'SPRING10', discount_type: 'percent', discount_value: 10 }, invoices_count: 1 },
    { id: 502, uuid: 'pay_002', payment_gateway: 'paypal', gateway_payment_id: 'txn_002', status: 'pending', amount: 19, discount_amount: 0, tax_amount: 0.95, net_amount: 19.95, currency: 'USD', payment_method: 'paypal_balance', description: 'Starter monthly', refunded_amount: 0, refunded_at: null, paid_at: null, created_at: '2026-04-04T09:05:00Z', updated_at: '2026-04-04T09:05:00Z', deleted_at: null, user: { id: 2, name: 'Omar Ali', email: 'omar@klek.ai', avatar: null }, subscription: { id: 22, status: 'pending', plan: { id: 2, name: 'Starter', slug: 'starter' } }, coupon: null, invoices_count: 0 },
    { id: 503, uuid: 'pay_003', payment_gateway: 'stripe', gateway_payment_id: 'pi_003', status: 'refunded', amount: 199, discount_amount: 20, tax_amount: 8.95, net_amount: 187.95, currency: 'USD', payment_method: 'card', description: 'Enterprise annual', refunded_amount: 187.95, refunded_at: '2026-04-03T18:00:00Z', paid_at: '2026-04-02T16:40:00Z', created_at: '2026-04-02T16:38:00Z', updated_at: '2026-04-03T18:00:00Z', deleted_at: null, user: { id: 3, name: 'Mona Khaled', email: 'mona@klek.ai', avatar: null }, subscription: { id: 23, status: 'cancelled', plan: { id: 4, name: 'Enterprise', slug: 'enterprise' } }, coupon: { id: 8, code: 'VIP20', discount_type: 'amount', discount_value: 20 }, invoices_count: 1 },
    { id: 504, uuid: 'pay_004', payment_gateway: 'stripe', gateway_payment_id: 'pi_004', status: 'failed', amount: 19, discount_amount: 0, tax_amount: 0.95, net_amount: 19.95, currency: 'USD', payment_method: 'card', description: 'Starter upgrade attempt', refunded_amount: 0, refunded_at: null, paid_at: null, created_at: '2026-04-03T11:10:00Z', updated_at: '2026-04-03T11:12:00Z', deleted_at: null, user: { id: 4, name: 'Karim Mostafa', email: 'karim@klek.ai', avatar: null }, subscription: null, coupon: null, invoices_count: 0 },
    { id: 505, uuid: 'pay_005', payment_gateway: 'stripe', gateway_payment_id: 'pi_005', status: 'completed', amount: 49, discount_amount: 0, tax_amount: 2.45, net_amount: 51.45, currency: 'USD', payment_method: 'card', description: 'Pro monthly', refunded_amount: 0, refunded_at: null, paid_at: '2026-04-01T13:12:00Z', created_at: '2026-04-01T13:10:00Z', updated_at: '2026-04-01T13:12:00Z', deleted_at: '2026-04-03T00:00:00Z', user: { id: 5, name: 'Layla Hassan', email: 'layla@klek.ai', avatar: null }, subscription: { id: 24, status: 'active', plan: { id: 3, name: 'Pro', slug: 'pro' } }, coupon: null, invoices_count: 1 },
  ]
  paymentTotalRecords.value = 184
}

function loadMockInvoices() {
  invoices.value = [
    { id: 701, uuid: 'inv_001', invoice_number: 'INV-2026-0001', status: 'paid', subtotal: 49, discount_amount: 5, tax_amount: 2.2, total: 46.2, currency: 'USD', issued_at: '2026-04-04T08:29:00Z', due_at: '2026-04-11T08:29:00Z', paid_at: '2026-04-04T08:30:00Z', created_at: '2026-04-04T08:29:00Z', deleted_at: null, user: { id: 1, name: 'Sara Ahmed', email: 'sara@klek.ai' }, payment: { id: 501, uuid: 'pay_001', payment_gateway: 'stripe', status: 'completed', amount: 49 }, subscription: { id: 21, status: 'active', plan: { id: 3, name: 'Pro' } } },
    { id: 702, uuid: 'inv_002', invoice_number: 'INV-2026-0002', status: 'issued', subtotal: 19, discount_amount: 0, tax_amount: 0.95, total: 19.95, currency: 'USD', issued_at: '2026-04-04T09:05:00Z', due_at: '2026-04-11T09:05:00Z', paid_at: null, created_at: '2026-04-04T09:05:00Z', deleted_at: null, user: { id: 2, name: 'Omar Ali', email: 'omar@klek.ai' }, payment: null, subscription: { id: 22, status: 'pending', plan: { id: 2, name: 'Starter' } } },
    { id: 703, uuid: 'inv_003', invoice_number: 'INV-2026-0003', status: 'refunded', subtotal: 199, discount_amount: 20, tax_amount: 8.95, total: 187.95, currency: 'USD', issued_at: '2026-04-02T16:39:00Z', due_at: '2026-04-09T16:39:00Z', paid_at: '2026-04-02T16:40:00Z', created_at: '2026-04-02T16:39:00Z', deleted_at: null, user: { id: 3, name: 'Mona Khaled', email: 'mona@klek.ai' }, payment: { id: 503, uuid: 'pay_003', payment_gateway: 'stripe', status: 'refunded', amount: 199 }, subscription: { id: 23, status: 'cancelled', plan: { id: 4, name: 'Enterprise' } } },
    { id: 704, uuid: 'inv_004', invoice_number: 'INV-2026-0004', status: 'overdue', subtotal: 99, discount_amount: 0, tax_amount: 4.95, total: 103.95, currency: 'USD', issued_at: '2026-03-26T10:00:00Z', due_at: '2026-04-02T10:00:00Z', paid_at: null, created_at: '2026-03-26T10:00:00Z', deleted_at: null, user: { id: 6, name: 'Nour Sayed', email: 'nour@klek.ai' }, payment: null, subscription: null },
    { id: 705, uuid: 'inv_005', invoice_number: 'INV-2026-0005', status: 'paid', subtotal: 49, discount_amount: 0, tax_amount: 2.45, total: 51.45, currency: 'USD', issued_at: '2026-04-01T13:11:00Z', due_at: '2026-04-08T13:11:00Z', paid_at: '2026-04-01T13:12:00Z', created_at: '2026-04-01T13:11:00Z', deleted_at: '2026-04-03T00:00:00Z', user: { id: 5, name: 'Layla Hassan', email: 'layla@klek.ai' }, payment: { id: 505, uuid: 'pay_005', payment_gateway: 'stripe', status: 'completed', amount: 49 }, subscription: { id: 24, status: 'active', plan: { id: 3, name: 'Pro' } } },
  ]
  invoiceTotalRecords.value = 129
}

function loadMockAggregations() {
  paymentAggregations.value = {
    summary: {
      total_payments: 4218,
      total_revenue: 183420,
      total_net_revenue: 171804,
      total_discounts: 8160,
      total_taxes: 6544,
      total_refunded: 12355,
      avg_payment_amount: 43.5,
      completed_count: 3782,
      failed_count: 146,
      pending_count: 104,
      refunded_count: 132,
      disputed_count: 54,
    },
    by_gateway: [
      { payment_gateway: 'stripe', count: 2910, total: 132450, net_total: 124880 },
      { payment_gateway: 'paypal', count: 980, total: 33240, net_total: 31400 },
      { payment_gateway: 'manual', count: 328, total: 17730, net_total: 15524 },
    ],
    by_currency: [{ currency: 'USD', count: 4020, total: 176900 }, { currency: 'EUR', count: 198, total: 6520 }],
    daily_trend: [
      { date: '2026-03-29', count: 54, total: 2240, net_total: 2108 },
      { date: '2026-03-30', count: 61, total: 2630, net_total: 2472 },
      { date: '2026-03-31', count: 58, total: 2510, net_total: 2360 },
      { date: '2026-04-01', count: 66, total: 2810, net_total: 2646 },
      { date: '2026-04-02', count: 73, total: 3240, net_total: 3054 },
      { date: '2026-04-03', count: 69, total: 3010, net_total: 2829 },
      { date: '2026-04-04', count: 77, total: 3480, net_total: 3286 },
    ],
    top_users: [
      { id: 1, name: 'Sara Ahmed', email: 'sara@klek.ai', payments_count: 18, total_spent: 882 },
      { id: 2, name: 'Omar Ali', email: 'omar@klek.ai', payments_count: 15, total_spent: 631 },
      { id: 3, name: 'Mona Khaled', email: 'mona@klek.ai', payments_count: 13, total_spent: 594 },
    ],
    status_distribution: [
      { status: 'completed', count: 3782, total: 171804 },
      { status: 'pending', count: 104, total: 4920 },
      { status: 'failed', count: 146, total: 6110 },
      { status: 'refunded', count: 132, total: 12355 },
      { status: 'disputed', count: 54, total: 2860 },
    ],
  }

  invoiceAggregations.value = {
    summary: {
      total_invoices: 4021,
      total_paid: 167910,
      paid_count: 3580,
      draft_count: 72,
      issued_count: 188,
      overdue_count: 121,
      cancelled_count: 35,
      refunded_count: 25,
      avg_invoice_total: 41.7,
    },
    overdue: {
      count: 121,
      total: 8844,
    },
  }
}

onMounted(async () => {
  await Promise.all([fetchPayments(), fetchInvoices(), fetchAggregations()])
})

let paymentSearchTimeout: ReturnType<typeof setTimeout>
watch(paymentSearch, () => {
  clearTimeout(paymentSearchTimeout)
  paymentSearchTimeout = setTimeout(() => {
    paymentPage.value = 1
    fetchPayments()
  }, 350)
})

let invoiceSearchTimeout: ReturnType<typeof setTimeout>
watch(invoiceSearch, () => {
  clearTimeout(invoiceSearchTimeout)
  invoiceSearchTimeout = setTimeout(() => {
    invoicePage.value = 1
    fetchInvoices()
  }, 350)
})

watch([paymentStatusFilter, paymentGatewayFilter, paymentTrashFilter], () => {
  paymentPage.value = 1
  fetchPayments()
})

watch([invoiceStatusFilter, invoiceTrashFilter, invoiceOverdueFilter], () => {
  invoicePage.value = 1
  fetchInvoices()
})

function onPaymentPage(event: any) {
  paymentPage.value = Math.floor(event.first / event.rows) + 1
  paymentPerPage.value = event.rows
  fetchPayments()
}

function onInvoicePage(event: any) {
  invoicePage.value = Math.floor(event.first / event.rows) + 1
  invoicePerPage.value = event.rows
  fetchInvoices()
}

function onPaymentSort(event: any) {
  paymentSortField.value = event.sortField
  paymentSortOrder.value = event.sortOrder === 1 ? 'asc' : 'desc'
  fetchPayments()
}

function onInvoiceSort(event: any) {
  invoiceSortField.value = event.sortField
  invoiceSortOrder.value = event.sortOrder === 1 ? 'asc' : 'desc'
  fetchInvoices()
}

function openPaymentDetail(item: Payment) {
  paymentDetailId.value = item.id
  showPaymentDetail.value = true
}

function openInvoiceDetail(item: Invoice) {
  invoiceDetailId.value = item.id
  showInvoiceDetail.value = true
}

function confirmDelete(kind: 'payment' | 'invoice', id: number, label: string) {
  deleteTarget.value = { kind, id, label }
  showDeleteConfirm.value = true
}

async function handleDelete() {
  if (!deleteTarget.value) return
  actionLoading.value = true
  try {
    if (deleteTarget.value.kind === 'payment') {
      await deletePayment(deleteTarget.value.id)
      await fetchPayments()
    } else {
      await deleteInvoice(deleteTarget.value.id)
      await fetchInvoices()
    }
    await fetchAggregations()
    showDeleteConfirm.value = false
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleRestorePayment(item: Payment) {
  actionLoading.value = true
  try {
    await restorePayment(item.id)
    await Promise.all([fetchPayments(), fetchAggregations()])
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleRestoreInvoice(item: Invoice) {
  actionLoading.value = true
  try {
    await restoreInvoice(item.id)
    await Promise.all([fetchInvoices(), fetchAggregations()])
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

function onEntityUpdated() {
  Promise.all([fetchPayments(), fetchInvoices(), fetchAggregations()])
}

const overviewCards = computed(() => {
  const paymentSummary = paymentAggregations.value?.summary
  const invoiceSummary = invoiceAggregations.value?.summary
  const overdue = invoiceAggregations.value?.overdue
  if (!paymentSummary || !invoiceSummary || !overdue) return []
  return [
    { label: t('payments.grossRevenue'), value: formatMoney(paymentSummary.total_revenue), sub: t('payments.paymentsCount', { count: paymentSummary.total_payments }), tone: '#10b981', icon: 'pi pi-dollar' },
    { label: t('payments.netRevenue'), value: formatMoney(paymentSummary.total_net_revenue), sub: t('payments.refunded', { amount: formatMoney(paymentSummary.total_refunded) }), tone: '#06b6d4', icon: 'pi pi-chart-line' },
    { label: t('payments.paidInvoices'), value: invoiceSummary.paid_count.toLocaleString(), sub: t('payments.collected', { amount: formatMoney(invoiceSummary.total_paid) }), tone: '#8b5cf6', icon: 'pi pi-file-check' },
    { label: t('payments.overdue'), value: overdue.count.toLocaleString(), sub: t('payments.pendingAmount', { amount: formatMoney(overdue.total) }), tone: '#f59e0b', icon: 'pi pi-clock' },
  ]
})

function gridColor() {
  return getComputedStyle(document.documentElement).getPropertyValue('--card-border').trim() || '#e2e8f0'
}

function mutedText() {
  return getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#94a3b8'
}

const revenueTrendData = computed(() => {
  const items = paymentAggregations.value?.daily_trend || []
  return {
    labels: items.map(item => formatShortDate(item.date)),
    datasets: [
      {
        label: t('payments.revenue'),
        data: items.map(item => item.total),
        borderColor: '#10b981',
        backgroundColor: 'rgba(16, 185, 129, 0.08)',
        fill: true,
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 2,
      },
      {
        label: 'Net',
        data: items.map(item => item.net_total),
        borderColor: '#06b6d4',
        backgroundColor: 'rgba(6, 182, 212, 0.04)',
        fill: false,
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 2,
      },
    ],
  }
})

const revenueTrendOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { labels: { color: mutedText(), usePointStyle: true, pointStyle: 'circle' } } },
  scales: {
    x: { grid: { color: gridColor() }, ticks: { color: mutedText(), font: { size: 10 } } },
    y: { grid: { color: gridColor() }, ticks: { color: mutedText(), font: { size: 10 } }, beginAtZero: true },
  },
}))

const gatewayChartData = computed(() => {
  const items = paymentAggregations.value?.by_gateway || []
  return {
    labels: items.map(item => item.payment_gateway),
    datasets: [{ data: items.map(item => item.total), backgroundColor: ['#8b5cf6', '#0ea5e9', '#f59e0b', '#10b981'], borderRadius: 5, borderWidth: 0, barThickness: 18 }],
  }
})

const gatewayChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y' as const,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { color: gridColor() }, ticks: { color: mutedText(), font: { size: 10 } }, beginAtZero: true },
    y: { grid: { display: false }, ticks: { color: mutedText(), font: { size: 10 } } },
  },
}))

const statusChartData = computed(() => {
  const items = paymentAggregations.value?.status_distribution || []
  return {
    labels: items.map(item => capitalize(item.status)),
    datasets: [{ data: items.map(item => item.count), backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b'], borderWidth: 0, hoverOffset: 6 }],
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

function paymentStatusSeverity(status: string): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  const map: Record<string, 'success' | 'warn' | 'danger' | 'info' | 'secondary'> = {
    completed: 'success',
    pending: 'warn',
    failed: 'danger',
    refunded: 'secondary',
    partially_refunded: 'info',
    cancelled: 'secondary',
    disputed: 'danger',
  }
  return map[status] || 'secondary'
}

function invoiceStatusSeverity(status: string): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  const map: Record<string, 'success' | 'warn' | 'danger' | 'info' | 'secondary'> = {
    paid: 'success',
    draft: 'secondary',
    issued: 'info',
    overdue: 'danger',
    cancelled: 'secondary',
    refunded: 'warn',
  }
  return map[status] || 'secondary'
}

function formatMoney(value: number, currency: string = 'USD') {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: value % 1 === 0 ? 0 : 2 }).format(value)
}

function formatDateTime(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatShortDate(value: string) {
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function initials(name: string) {
  return name.split(' ').map(item => item[0]).join('').slice(0, 2)
}

function shortText(value: string | null, max = 78) {
  if (!value) return '—'
  return value.length > max ? `${value.slice(0, max)}...` : value
}

function capitalize(value: string) {
  return value.charAt(0).toUpperCase() + value.slice(1)
}
</script>

<template>
  <div class="billing-page">
    <div class="page-toolbar">
      <h1 class="page-title">{{ t('payments.title') }}</h1>
    </div>

    <Tabs v-model:value="activeTab" class="billing-tabs">
      <TabList>
        <Tab value="overview"><i class="pi pi-chart-line" /> <span>{{ t('payments.overviewTab') }}</span></Tab>
        <Tab value="payments"><i class="pi pi-credit-card" /> <span>{{ t('payments.paymentsTab') }}</span></Tab>
        <Tab value="invoices"><i class="pi pi-file" /> <span>{{ t('payments.invoicesTab') }}</span></Tab>
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

          <div class="analytics-grid" v-if="!aggregationsLoading && paymentAggregations && invoiceAggregations">
            <section class="chart-card chart-wide">
              <div class="card-head">
                <div>
                  <h3>{{ t('payments.revenueTrend') }}</h3>
                  <p>Gross and net billing activity across the last 7 days.</p>
                </div>
              </div>
              <div class="chart-shell tall">
                <Chart type="line" :data="revenueTrendData" :options="revenueTrendOptions" />
              </div>
            </section>

            <section class="chart-card">
              <div class="card-head">
                <div>
                  <h3>{{ t('payments.byStatus') }}</h3>
                  <p>Distribution by operational state.</p>
                </div>
              </div>
              <div class="chart-shell">
                <Chart type="doughnut" :data="statusChartData" :options="statusChartOptions" />
              </div>
            </section>

            <section class="chart-card">
              <div class="card-head">
                <div>
                  <h3>{{ t('payments.byGateway') }}</h3>
                  <p>Processed totals by gateway.</p>
                </div>
              </div>
              <div class="chart-shell">
                <Chart type="bar" :data="gatewayChartData" :options="gatewayChartOptions" />
              </div>
            </section>

            <section class="insight-card">
              <div class="insight-block">
                <div class="card-head compact-head">
                  <h3>Top Customers</h3>
                </div>
                <div class="mini-list">
                  <div v-for="entry in paymentAggregations.top_users.slice(0, 4)" :key="entry.id" class="mini-row">
                    <div class="mini-avatar">{{ initials(entry.name) }}</div>
                    <div class="mini-copy">
                      <span class="mini-title">{{ entry.name }}</span>
                      <span class="mini-sub">{{ entry.payments_count }} payments · {{ formatMoney(entry.total_spent) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="insight-block">
                <div class="card-head compact-head">
                  <h3>Invoice Health</h3>
                </div>
                <div class="health-list">
                  <div class="health-row"><span>{{ t('payments.draft') }}</span><strong>{{ invoiceAggregations.summary.draft_count }}</strong></div>
                  <div class="health-row"><span>{{ t('payments.issued') }}</span><strong>{{ invoiceAggregations.summary.issued_count }}</strong></div>
                  <div class="health-row"><span>{{ t('payments.overdueStatus') }}</span><strong>{{ invoiceAggregations.summary.overdue_count }}</strong></div>
                  <div class="health-row"><span>Avg Invoice</span><strong>{{ formatMoney(invoiceAggregations.summary.avg_invoice_total || 0) }}</strong></div>
                </div>
              </div>
            </section>
          </div>
        </TabPanel>

        <TabPanel value="payments">
          <div class="filters-bar">
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText v-model="paymentSearch" :placeholder="t('payments.searchPayments')" size="small" class="filter-input" />
            </span>
            <Select v-model="paymentStatusFilter" :options="paymentStatusOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <Select v-model="paymentGatewayFilter" :options="gatewayOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <Select v-model="paymentTrashFilter" :options="trashOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <span class="filter-count">{{ paymentTotalRecords }} {{ t('payments.paymentsTab').toLowerCase() }}</span>
          </div>

          <div class="cards-list d-mobile">
            <article v-for="payment in payments" :key="payment.id" class="entity-card" @click="openPaymentDetail(payment)">
              <div class="entity-head">
                <div>
                  <div class="entity-title-row">
                    <span class="entity-id">#{{ payment.id }} · {{ payment.uuid }}</span>
                    <Tag :value="payment.status" :severity="paymentStatusSeverity(payment.status)" class="mini-tag" />
                  </div>
                  <span class="entity-user">{{ payment.user.name }} · {{ payment.payment_gateway }}</span>
                </div>
                <strong class="amount-strong">{{ formatMoney(payment.net_amount, payment.currency) }}</strong>
              </div>

              <p class="entity-desc">{{ shortText(payment.description) }}</p>

              <div class="meta-grid">
                <span><i class="pi pi-wallet" /> {{ payment.payment_method || '—' }}</span>
                <span><i class="pi pi-file" /> {{ payment.invoices_count }} invoices</span>
                <span><i class="pi pi-ticket" /> {{ payment.coupon?.code || 'No coupon' }}</span>
                <span><i class="pi pi-calendar" /> {{ formatDateTime(payment.paid_at || payment.created_at) }}</span>
              </div>

              <div class="card-actions" @click.stop>
                <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openPaymentDetail(payment)" />
                <Button v-if="payment.deleted_at" icon="pi pi-replay" severity="secondary" text rounded size="small" @click="handleRestorePayment(payment)" />
                <Button v-else icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete('payment', payment.id, payment.uuid)" />
              </div>
            </article>
          </div>

          <div class="table-card d-desktop">
            <DataTable
              :value="payments"
              :loading="paymentsLoading"
              :rows="paymentPerPage"
              :totalRecords="paymentTotalRecords"
              :lazy="true"
              :paginator="true"
              :rowsPerPageOptions="[10, 15, 25, 50]"
              paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
              sortMode="single"
              :sortField="paymentSortField"
              :sortOrder="paymentSortOrder === 'asc' ? 1 : -1"
              @sort="onPaymentSort"
              @page="onPaymentPage"
              stripedRows
              size="small"
              scrollable
              class="entity-table"
              dataKey="id"
            >
              <Column field="uuid" header="Payment" sortable style="min-width: 230px">
                <template #body="{ data }">
                  <div class="name-cell" @click="openPaymentDetail(data)">
                    <div class="name-icon payment-icon"><i class="pi pi-credit-card" /></div>
                    <div class="name-copy">
                      <span class="name-title">{{ data.user.name }}</span>
                      <span class="name-sub">{{ data.uuid }} · {{ data.payment_gateway }}</span>
                    </div>
                  </div>
                </template>
              </Column>
              <Column field="status" header="Status" sortable style="min-width: 110px">
                <template #body="{ data }">
                  <Tag :value="data.status" :severity="paymentStatusSeverity(data.status)" class="mini-tag" />
                </template>
              </Column>
              <Column field="net_amount" header="Net" sortable style="min-width: 110px">
                <template #body="{ data }">
                  <span class="metric-strong">{{ formatMoney(data.net_amount, data.currency) }}</span>
                </template>
              </Column>
              <Column field="payment_method" header="Method" style="min-width: 100px">
                <template #body="{ data }">
                  <span class="muted-text">{{ data.payment_method || '—' }}</span>
                </template>
              </Column>
              <Column field="invoices_count" header="Invoices" style="min-width: 90px">
                <template #body="{ data }">
                  <span class="metric-chip"><i class="pi pi-file" /> {{ data.invoices_count }}</span>
                </template>
              </Column>
              <Column field="paid_at" header="Paid" sortable style="min-width: 120px">
                <template #body="{ data }">
                  <span class="muted-text">{{ formatDateTime(data.paid_at || data.created_at) }}</span>
                </template>
              </Column>
              <Column header="" style="min-width: 120px; text-align: right" frozen alignFrozen="right">
                <template #body="{ data }">
                  <div class="row-actions">
                    <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openPaymentDetail(data)" v-tooltip.left="'View'" />
                    <Button v-if="data.deleted_at" icon="pi pi-replay" severity="secondary" text rounded size="small" @click="handleRestorePayment(data)" v-tooltip.left="'Restore'" />
                    <Button v-else icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete('payment', data.id, data.uuid)" v-tooltip.left="'Delete'" />
                  </div>
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>

        <TabPanel value="invoices">
          <div class="filters-bar">
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText v-model="invoiceSearch" :placeholder="t('payments.searchInvoices')" size="small" class="filter-input" />
            </span>
            <Select v-model="invoiceStatusFilter" :options="invoiceStatusOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <Select v-model="invoiceTrashFilter" :options="trashOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <label class="toggle-filter">
              <Checkbox v-model="invoiceOverdueFilter" :binary="true" />
              <span>{{ t('payments.overdueOnly') }}</span>
            </label>
            <span class="filter-count">{{ invoiceTotalRecords }} {{ t('payments.invoicesTab').toLowerCase() }}</span>
          </div>

          <div class="cards-list d-mobile">
            <article v-for="invoice in invoices" :key="invoice.id" class="entity-card" @click="openInvoiceDetail(invoice)">
              <div class="entity-head">
                <div>
                  <div class="entity-title-row">
                    <span class="entity-id">{{ invoice.invoice_number }}</span>
                    <Tag :value="invoice.status" :severity="invoiceStatusSeverity(invoice.status)" class="mini-tag" />
                  </div>
                  <span class="entity-user">{{ invoice.user.name }} · {{ invoice.payment?.payment_gateway || 'manual' }}</span>
                </div>
                <strong class="amount-strong">{{ formatMoney(invoice.total, invoice.currency) }}</strong>
              </div>

              <div class="meta-grid">
                <span><i class="pi pi-calendar" /> Issued {{ formatDateTime(invoice.issued_at || invoice.created_at) }}</span>
                <span><i class="pi pi-clock" /> Due {{ formatDateTime(invoice.due_at) }}</span>
                <span><i class="pi pi-check-circle" /> Paid {{ formatDateTime(invoice.paid_at) }}</span>
                <span><i class="pi pi-credit-card" /> {{ invoice.payment?.uuid || 'No payment' }}</span>
              </div>

              <div class="card-actions" @click.stop>
                <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openInvoiceDetail(invoice)" />
                <Button v-if="invoice.deleted_at" icon="pi pi-replay" severity="secondary" text rounded size="small" @click="handleRestoreInvoice(invoice)" />
                <Button v-else icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete('invoice', invoice.id, invoice.invoice_number)" />
              </div>
            </article>
          </div>

          <div class="table-card d-desktop">
            <DataTable
              :value="invoices"
              :loading="invoicesLoading"
              :rows="invoicePerPage"
              :totalRecords="invoiceTotalRecords"
              :lazy="true"
              :paginator="true"
              :rowsPerPageOptions="[10, 15, 25, 50]"
              paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
              sortMode="single"
              :sortField="invoiceSortField"
              :sortOrder="invoiceSortOrder === 'asc' ? 1 : -1"
              @sort="onInvoiceSort"
              @page="onInvoicePage"
              stripedRows
              size="small"
              scrollable
              class="entity-table"
              dataKey="id"
            >
              <Column field="invoice_number" header="Invoice" sortable style="min-width: 230px">
                <template #body="{ data }">
                  <div class="name-cell" @click="openInvoiceDetail(data)">
                    <div class="name-icon invoice-icon"><i class="pi pi-file" /></div>
                    <div class="name-copy">
                      <span class="name-title">{{ data.invoice_number }}</span>
                      <span class="name-sub">{{ data.user.name }} · {{ data.uuid }}</span>
                    </div>
                  </div>
                </template>
              </Column>
              <Column field="status" header="Status" sortable style="min-width: 100px">
                <template #body="{ data }">
                  <Tag :value="data.status" :severity="invoiceStatusSeverity(data.status)" class="mini-tag" />
                </template>
              </Column>
              <Column field="total" header="Total" sortable style="min-width: 110px">
                <template #body="{ data }">
                  <span class="metric-strong">{{ formatMoney(data.total, data.currency) }}</span>
                </template>
              </Column>
              <Column field="payment" header="Payment" style="min-width: 120px">
                <template #body="{ data }">
                  <span class="muted-text">{{ data.payment?.uuid || '—' }}</span>
                </template>
              </Column>
              <Column field="due_at" header="Due" sortable style="min-width: 120px">
                <template #body="{ data }">
                  <span class="muted-text">{{ formatDateTime(data.due_at) }}</span>
                </template>
              </Column>
              <Column field="paid_at" header="Paid" sortable style="min-width: 120px">
                <template #body="{ data }">
                  <span class="muted-text">{{ formatDateTime(data.paid_at) }}</span>
                </template>
              </Column>
              <Column header="" style="min-width: 120px; text-align: right" frozen alignFrozen="right">
                <template #body="{ data }">
                  <div class="row-actions">
                    <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openInvoiceDetail(data)" v-tooltip.left="'View'" />
                    <Button v-if="data.deleted_at" icon="pi pi-replay" severity="secondary" text rounded size="small" @click="handleRestoreInvoice(data)" v-tooltip.left="'Restore'" />
                    <Button v-else icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete('invoice', data.id, data.invoice_number)" v-tooltip.left="'Delete'" />
                  </div>
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <PaymentDetailDrawer v-model:visible="showPaymentDetail" :paymentId="paymentDetailId" @updated="onEntityUpdated" />
    <InvoiceDetailDrawer v-model:visible="showInvoiceDetail" :invoiceId="invoiceDetailId" @updated="onEntityUpdated" />

    <Dialog v-model:visible="showDeleteConfirm" :header="t('common.delete')" :modal="true" :style="{ width: '360px' }">
      <div class="confirm-body">
        <i class="pi pi-exclamation-triangle confirm-icon" />
        <p>{{ t('payments.deleteConfirm', { label: deleteTarget?.label }) }}</p>
        <p class="confirm-sub">{{ t('payments.noUndo') }}</p>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text size="small" @click="showDeleteConfirm = false" />
        <Button :label="t('common.delete')" severity="danger" size="small" :loading="actionLoading" @click="handleDelete" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.billing-page { display: flex; flex-direction: column; gap: 10px; min-width: 0; overflow: hidden; }
.page-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.page-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin: 0; }

:deep(.billing-tabs .p-tablist) { background: transparent; }
:deep(.billing-tabs .p-tab) {
  font-size: 0.72rem !important;
  padding: 7px 12px !important;
  color: var(--text-muted) !important;
  background: transparent !important;
  border: none !important;
  display: flex;
  align-items: center;
  gap: 5px;
}
:deep(.billing-tabs .p-tab-active) { color: var(--active-color) !important; }
:deep(.billing-tabs .p-tabpanels) { background: transparent; padding: 8px 0 !important; }

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 8px;
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
  }
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
  background: linear-gradient(135deg, #10b981, #059669);
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
.health-list { display: flex; flex-direction: column; gap: 6px; }
.health-row {
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

.filters-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
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
.toggle-filter { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; color: var(--text-primary); }
.filter-count { font-size: 0.7rem; color: var(--text-muted); margin-left: auto; }

.d-mobile { display: grid; }
.d-desktop { display: none; }
@media (min-width: 768px) {
  .d-mobile { display: none; }
  .d-desktop { display: block; }
}

.cards-list { display: grid; grid-template-columns: 1fr; gap: 8px; }
.entity-card {
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
.entity-card:hover {
  border-color: var(--active-color);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--active-color) 20%, transparent);
}
.entity-head { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; }
.entity-title-row { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.entity-id { font-size: 0.72rem; font-weight: 600; color: var(--text-primary); }
.entity-user,
.entity-desc,
.muted-text { font-size: 0.64rem; color: var(--text-muted); }
.entity-desc { margin: 0; }
.amount-strong { font-size: 0.84rem; color: var(--text-primary); }
.meta-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 10px;
  font-size: 0.66rem;
  color: var(--text-muted);
}
.meta-grid span { display: flex; align-items: center; gap: 5px; }
.card-actions,
.row-actions { display: flex; align-items: center; gap: 0; }

.table-card {
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  overflow: hidden;
  min-width: 0;
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
:deep(.entity-table .p-datatable-tbody > tr) { background: var(--card-bg); color: var(--text-primary); }
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
:deep(.entity-table .p-paginator) {
  background: var(--card-bg);
  border-color: var(--card-border);
  padding: 6px 12px;
}

.name-cell { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.name-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #fff;
  font-size: 0.66rem;
}
.payment-icon { background: linear-gradient(135deg, #10b981, #059669); }
.invoice-icon { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.name-copy { display: flex; flex-direction: column; min-width: 0; }
.name-title { font-size: 0.76rem; font-weight: 600; color: var(--text-primary); }
.name-sub { font-size: 0.62rem; color: var(--text-muted); }
.metric-strong { font-size: 0.76rem; font-weight: 600; color: var(--text-primary); }
.metric-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 0.68rem; color: var(--text-secondary); }
.mini-tag { font-size: 0.56rem !important; padding: 2px 7px !important; }

.confirm-body { text-align: center; padding: 6px 0; }
.confirm-icon { font-size: 2rem; color: #ef4444; margin-bottom: 8px; }
.confirm-body p { font-size: 0.82rem; color: var(--text-primary); margin: 4px 0; }
.confirm-sub { font-size: 0.7rem; color: var(--text-muted); }
</style>