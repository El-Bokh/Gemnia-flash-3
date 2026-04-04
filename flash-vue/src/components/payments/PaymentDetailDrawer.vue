<script setup lang="ts">
import { ref, watch } from 'vue'
import { generateInvoiceFromPayment } from '@/services/invoiceService'
import { deletePayment, getPayment, refundPayment, restorePayment, updatePayment } from '@/services/paymentService'
import type { PaymentDetail, UpdatePaymentData } from '@/types/payments'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'

const props = defineProps<{
  visible: boolean
  paymentId: number | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void
  (e: 'updated'): void
}>()

const loading = ref(false)
const saving = ref(false)
const refunding = ref(false)
const actionLoading = ref(false)
const generatingInvoice = ref(false)

const detail = ref<PaymentDetail | null>(null)

const statusOptions = [
  { label: 'Pending', value: 'pending' },
  { label: 'Completed', value: 'completed' },
  { label: 'Failed', value: 'failed' },
  { label: 'Refunded', value: 'refunded' },
  { label: 'Partially Refunded', value: 'partially_refunded' },
  { label: 'Cancelled', value: 'cancelled' },
  { label: 'Disputed', value: 'disputed' },
] as Array<{ label: string; value: string }>

const form = ref({
  status: 'pending',
  description: '',
  billing_name: '',
  billing_email: '',
  billing_address: '',
  billing_city: '',
  billing_state: '',
  billing_zip: '',
  billing_country: '',
})

const refundForm = ref({
  amount: null as number | null,
  reason: '',
})

watch(
  () => props.visible,
  visible => {
    if (!visible || !props.paymentId) {
      detail.value = null
      return
    }
    loadPayment()
  },
)

async function loadPayment() {
  if (!props.paymentId) return

  loading.value = true
  try {
    const res = await getPayment(props.paymentId)
    detail.value = res.data
  } catch {
    detail.value = buildMockPayment(props.paymentId)
  } finally {
    if (detail.value) {
      form.value = {
        status: detail.value.status,
        description: detail.value.description || '',
        billing_name: detail.value.billing_name || '',
        billing_email: detail.value.billing_email || '',
        billing_address: detail.value.billing_address || '',
        billing_city: detail.value.billing_city || '',
        billing_state: detail.value.billing_state || '',
        billing_zip: detail.value.billing_zip || '',
        billing_country: detail.value.billing_country || '',
      }
      refundForm.value = { amount: null, reason: '' }
    }
    loading.value = false
  }
}

async function saveChanges() {
  if (!detail.value) return

  saving.value = true
  try {
    const payload: UpdatePaymentData = {
      status: form.value.status,
      description: form.value.description || null || undefined,
      billing_name: form.value.billing_name || null || undefined,
      billing_email: form.value.billing_email || null || undefined,
      billing_address: form.value.billing_address || null || undefined,
      billing_city: form.value.billing_city || null || undefined,
      billing_state: form.value.billing_state || null || undefined,
      billing_zip: form.value.billing_zip || null || undefined,
      billing_country: form.value.billing_country || null || undefined,
      metadata: detail.value.metadata || undefined,
    }
    await updatePayment(detail.value.id, payload)
    await loadPayment()
    emit('updated')
  } catch {
    // noop
  } finally {
    saving.value = false
  }
}

async function handleRefund() {
  if (!detail.value || !refundForm.value.reason.trim()) return

  refunding.value = true
  try {
    await refundPayment(detail.value.id, {
      amount: refundForm.value.amount || undefined,
      reason: refundForm.value.reason,
    })
    await loadPayment()
    emit('updated')
  } catch {
    // noop
  } finally {
    refunding.value = false
  }
}

async function handleGenerateInvoice() {
  if (!detail.value) return

  generatingInvoice.value = true
  try {
    await generateInvoiceFromPayment(detail.value.id)
    await loadPayment()
    emit('updated')
  } catch {
    // noop
  } finally {
    generatingInvoice.value = false
  }
}

async function handleDelete() {
  if (!detail.value) return

  actionLoading.value = true
  try {
    await deletePayment(detail.value.id)
    await loadPayment()
    emit('updated')
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleRestore() {
  if (!detail.value) return

  actionLoading.value = true
  try {
    await restorePayment(detail.value.id)
    await loadPayment()
    emit('updated')
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

function close() {
  emit('update:visible', false)
}

function buildMockPayment(id: number): PaymentDetail {
  return {
    id,
    uuid: 'pay_001',
    payment_gateway: 'stripe',
    gateway_payment_id: 'pi_001',
    gateway_customer_id: 'cus_001',
    status: 'completed',
    amount: 49,
    discount_amount: 5,
    tax_amount: 2.2,
    net_amount: 46.2,
    currency: 'USD',
    payment_method: 'card',
    description: 'Pro monthly renewal',
    refunded_amount: 0,
    refunded_at: null,
    refund_reason: null,
    billing_name: 'Sara Ahmed',
    billing_email: 'sara@flash.io',
    billing_address: '25 Nile Street',
    billing_city: 'Cairo',
    billing_state: 'Cairo',
    billing_zip: '11511',
    billing_country: 'EG',
    gateway_response: { authorization: 'auth_01', card_brand: 'visa', last4: '4242' },
    metadata: { source: 'web_checkout', risk_score: 2 },
    paid_at: '2026-04-04T08:30:00Z',
    created_at: '2026-04-04T08:28:00Z',
    updated_at: '2026-04-04T08:30:00Z',
    deleted_at: null,
    user: { id: 1, name: 'Sara Ahmed', email: 'sara@flash.io', avatar: null, status: 'active' },
    subscription: {
      id: 21,
      status: 'active',
      billing_cycle: 'monthly',
      starts_at: '2026-04-04T08:30:00Z',
      ends_at: '2026-05-04T08:30:00Z',
      plan: { id: 3, name: 'Pro', slug: 'pro', price_monthly: 49, price_yearly: 490 },
    },
    coupon: { id: 3, code: 'SPRING10', discount_type: 'percent', discount_value: 10, name: 'Spring Promo' },
    invoices: [
      { id: 701, uuid: 'inv_001', invoice_number: 'INV-2026-0001', status: 'paid', total: 46.2, issued_at: '2026-04-04T08:29:00Z', paid_at: '2026-04-04T08:30:00Z' },
    ],
  }
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

function formatMoney(value: number, currency: string = 'USD') {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: value % 1 === 0 ? 0 : 2 }).format(value)
}

function formatDateTime(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function initials(name: string) {
  return name.split(' ').map(item => item[0]).join('').slice(0, 2)
}

function formatJson(value: Record<string, unknown> | null) {
  if (!value) return '—'
  return JSON.stringify(value, null, 2)
}

function canRefund(status: string) {
  return ['completed', 'partially_refunded'].includes(status)
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="close"
    header="Payment Detail"
    :modal="true"
    position="right"
    :style="{ width: '680px', maxWidth: '95vw', height: '100vh', margin: 0, borderRadius: 0 }"
    :draggable="false"
    class="payment-detail-drawer"
  >
    <div v-if="loading" class="drawer-loading"><i class="pi pi-spin pi-spinner" /></div>

    <div v-else-if="detail" class="drawer-content">
      <div class="hero-card">
        <div class="hero-row">
          <div class="hero-main">
            <div class="hero-title-row">
              <h2>#{{ detail.id }} · {{ detail.uuid }}</h2>
              <Tag :value="detail.status" :severity="paymentStatusSeverity(detail.status)" class="mini-tag" />
              <Tag :value="detail.payment_gateway" severity="info" class="mini-tag" />
            </div>
            <p class="hero-sub">{{ detail.user.name }} · {{ detail.payment_method || 'No method' }}</p>
            <p class="hero-desc">{{ detail.description || 'No description provided.' }}</p>
          </div>
          <div class="hero-actions">
            <Button icon="pi pi-file-plus" severity="secondary" text rounded size="small" :loading="generatingInvoice" @click="handleGenerateInvoice" />
            <Button v-if="detail.deleted_at" icon="pi pi-replay" severity="secondary" text rounded size="small" :loading="actionLoading" @click="handleRestore" />
            <Button v-else icon="pi pi-trash" severity="danger" text rounded size="small" :loading="actionLoading" @click="handleDelete" />
          </div>
        </div>

        <div class="stats-grid">
          <article class="stat-card">
            <span class="stat-k">Gross</span>
            <strong>{{ formatMoney(detail.amount, detail.currency) }}</strong>
            <small>{{ formatMoney(detail.discount_amount, detail.currency) }} discount</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Net</span>
            <strong>{{ formatMoney(detail.net_amount, detail.currency) }}</strong>
            <small>{{ formatMoney(detail.tax_amount, detail.currency) }} tax</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Refunded</span>
            <strong>{{ formatMoney(detail.refunded_amount, detail.currency) }}</strong>
            <small>{{ formatDateTime(detail.refunded_at) }}</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Invoices</span>
            <strong>{{ detail.invoices.length }}</strong>
            <small>{{ formatDateTime(detail.paid_at || detail.created_at) }}</small>
          </article>
        </div>
      </div>

      <Tabs value="overview" class="drawer-tabs">
        <TabList>
          <Tab value="overview">Overview</Tab>
          <Tab value="billing">Billing</Tab>
          <Tab value="actions">Actions</Tab>
          <Tab value="payloads">Payloads</Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="overview">
            <div class="section-grid">
              <section class="info-card">
                <h3 class="section-title">Customer</h3>
                <div class="user-row">
                  <div class="user-avatar">
                    <img v-if="detail.user.avatar" :src="detail.user.avatar" :alt="detail.user.name" />
                    <span v-else>{{ initials(detail.user.name) }}</span>
                  </div>
                  <div class="user-copy">
                    <span class="user-name">{{ detail.user.name }}</span>
                    <span class="user-sub">{{ detail.user.email }}</span>
                    <span class="user-sub">Status: {{ detail.user.status }}</span>
                  </div>
                </div>
              </section>

              <section class="info-card">
                <h3 class="section-title">Subscription</h3>
                <div class="meta-list">
                  <div class="meta-row"><span>Plan</span><span>{{ detail.subscription?.plan.name || '—' }}</span></div>
                  <div class="meta-row"><span>Cycle</span><span>{{ detail.subscription?.billing_cycle || '—' }}</span></div>
                  <div class="meta-row"><span>Status</span><span>{{ detail.subscription?.status || '—' }}</span></div>
                  <div class="meta-row"><span>Coupon</span><span>{{ detail.coupon?.code || '—' }}</span></div>
                </div>
              </section>
            </div>

            <section class="info-card">
              <h3 class="section-title">Invoices</h3>
              <div class="mini-list stack-list">
                <div v-for="invoice in detail.invoices" :key="invoice.id" class="stack-row">
                  <div>
                    <span class="row-title">{{ invoice.invoice_number }}</span>
                    <span class="row-sub">{{ invoice.uuid }}</span>
                  </div>
                  <div class="row-meta">
                    <Tag :value="invoice.status" :severity="paymentStatusSeverity(invoice.status === 'paid' ? 'completed' : invoice.status)" class="mini-tag" />
                    <span class="row-sub">{{ formatMoney(invoice.total, detail.currency) }}</span>
                  </div>
                </div>
              </div>
            </section>
          </TabPanel>

          <TabPanel value="billing">
            <div class="edit-grid">
              <div class="form-field">
                <label>Billing Name</label>
                <InputText v-model="form.billing_name" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>Billing Email</label>
                <InputText v-model="form.billing_email" size="small" class="w-full" />
              </div>
              <div class="form-field form-field-full">
                <label>Address</label>
                <InputText v-model="form.billing_address" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>City</label>
                <InputText v-model="form.billing_city" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>State</label>
                <InputText v-model="form.billing_state" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>ZIP</label>
                <InputText v-model="form.billing_zip" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>Country</label>
                <InputText v-model="form.billing_country" size="small" class="w-full" />
              </div>
            </div>
          </TabPanel>

          <TabPanel value="actions">
            <div class="edit-grid">
              <div class="form-field">
                <label>Status</label>
                <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
              </div>
              <div class="form-field form-field-full">
                <label>Description</label>
                <Textarea v-model="form.description" rows="3" autoResize class="w-full" />
              </div>
            </div>

            <div class="section-block">
              <div class="section-head-inline">
                <h3 class="section-title">Refund</h3>
                <Tag :value="canRefund(detail.status) ? 'Allowed' : 'Locked'" :severity="canRefund(detail.status) ? 'success' : 'secondary'" class="mini-tag" />
              </div>
              <div class="edit-grid">
                <div class="form-field">
                  <label>Refund Amount</label>
                  <input v-model.number="refundForm.amount" type="number" min="0" :max="detail.net_amount" step="0.01" class="native-input" placeholder="Full refund if empty" />
                </div>
                <div class="form-field form-field-full">
                  <label>Reason</label>
                  <Textarea v-model="refundForm.reason" rows="3" autoResize class="w-full" placeholder="Refund reason" />
                </div>
              </div>
              <div class="edit-actions split-actions">
                <Button label="Save Changes" size="small" :loading="saving" @click="saveChanges" />
                <Button label="Refund Payment" size="small" severity="warn" :disabled="!canRefund(detail.status) || !refundForm.reason.trim()" :loading="refunding" @click="handleRefund" />
              </div>
            </div>
          </TabPanel>

          <TabPanel value="payloads">
            <div class="payload-grid">
              <section class="payload-card">
                <h3 class="section-title">Gateway Response</h3>
                <pre>{{ formatJson(detail.gateway_response) }}</pre>
              </section>
              <section class="payload-card">
                <h3 class="section-title">Metadata</h3>
                <pre>{{ formatJson(detail.metadata) }}</pre>
              </section>
            </div>
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
.hero-row { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; }
.hero-main { min-width: 0; flex: 1; }
.hero-title-row { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.hero-title-row h2 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.hero-sub { margin: 4px 0 0; font-size: 0.64rem; color: var(--text-muted); }
.hero-desc { margin: 8px 0 0; font-size: 0.74rem; line-height: 1.45; color: var(--text-primary); }
.hero-actions { display: flex; gap: 0; }
.stats-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
@media (min-width: 640px) { .stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.stat-card { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border-radius: 10px; background: var(--hover-bg); }
.stat-k { font-size: 0.58rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
.stat-card strong { font-size: 0.85rem; color: var(--text-primary); }
.stat-card small { font-size: 0.62rem; color: var(--text-muted); }
:deep(.drawer-tabs .p-tablist) { background: transparent; }
:deep(.drawer-tabs .p-tab) { font-size: 0.7rem !important; padding: 6px 10px !important; color: var(--text-muted) !important; background: transparent !important; border: none !important; }
:deep(.drawer-tabs .p-tab-active) { color: var(--active-color) !important; }
:deep(.drawer-tabs .p-tabpanels) { background: transparent; padding: 10px 0 0 !important; }
.section-grid,.payload-grid,.edit-grid { display: grid; grid-template-columns: 1fr; gap: 8px; }
@media (min-width: 768px) { .section-grid,.payload-grid,.edit-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.info-card,.payload-card,.section-block { border: 1px solid var(--card-border); border-radius: 10px; background: var(--card-bg); padding: 10px; }
.payload-card pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-size: 0.65rem; color: var(--text-primary); }
.section-title { margin: 0 0 8px; font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
.section-head-inline { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.user-row { display: flex; align-items: center; gap: 8px; }
.user-avatar { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; overflow: hidden; flex-shrink: 0; }
.user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.user-copy { display: flex; flex-direction: column; gap: 2px; }
.user-name { font-size: 0.74rem; font-weight: 600; color: var(--text-primary); }
.user-sub { font-size: 0.62rem; color: var(--text-muted); }
.meta-list { display: flex; flex-direction: column; gap: 0; border: 1px solid var(--card-border); border-radius: 8px; overflow: hidden; }
.meta-row { display: flex; justify-content: space-between; gap: 10px; padding: 7px 9px; border-bottom: 1px solid var(--card-border); font-size: 0.66rem; }
.meta-row:last-child { border-bottom: none; }
.meta-row span:first-child { color: var(--text-muted); }
.meta-row span:last-child { color: var(--text-primary); text-align: right; }
.stack-list { gap: 8px; }
.stack-row { display: flex; justify-content: space-between; gap: 8px; align-items: center; padding: 8px 9px; border-radius: 8px; background: var(--hover-bg); }
.row-title { display: block; font-size: 0.7rem; font-weight: 600; color: var(--text-primary); }
.row-sub { display: block; font-size: 0.62rem; color: var(--text-muted); }
.row-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.form-field { display: flex; flex-direction: column; gap: 4px; }
.form-field label { font-size: 0.68rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; }
.form-field-full { grid-column: 1 / -1; }
.w-full { width: 100%; }
.native-input { width: 100%; min-height: 34px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--card-border); background: var(--card-bg); color: var(--text-primary); font-size: 0.76rem; outline: none; }
.native-input:focus { border-color: var(--active-color); }
.edit-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px; }
.split-actions { justify-content: space-between; flex-wrap: wrap; }
.mini-tag { font-size: 0.56rem !important; padding: 2px 7px !important; }
:deep(.payment-detail-drawer) { margin: 0 !important; border-radius: 0 !important; }
:deep(.payment-detail-drawer .p-dialog-header) { background: var(--card-bg); border-color: var(--card-border); color: var(--text-primary); padding: 10px 16px; }
:deep(.payment-detail-drawer .p-dialog-content) { background: var(--card-bg); color: var(--text-primary); padding: 12px 16px; overflow-y: auto; }
</style>
