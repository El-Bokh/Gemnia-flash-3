<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { deleteInvoice, downloadInvoice, getInvoice, restoreInvoice, updateInvoice } from '@/services/invoiceService'
import type { InvoiceDetail, InvoiceDownloadData, UpdateInvoiceData } from '@/types/payments'
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
  invoiceId: number | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void
  (e: 'updated'): void
}>()

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const actionLoading = ref(false)
const downloading = ref(false)

const detail = ref<InvoiceDetail | null>(null)
const downloadData = ref<InvoiceDownloadData | null>(null)

const statusOptions = computed(() => [
  { label: t('payments.draft'), value: 'draft' },
  { label: t('payments.issued'), value: 'issued' },
  { label: t('payments.paid'), value: 'paid' },
  { label: t('payments.overdue'), value: 'overdue' },
  { label: t('payments.cancelled'), value: 'cancelled' },
  { label: t('paymentDetail.refunded'), value: 'refunded' },
] as Array<{ label: string; value: string }>)

const form = ref({
  status: 'draft',
  billing_name: '',
  billing_email: '',
  billing_address: '',
  billing_city: '',
  billing_state: '',
  billing_zip: '',
  billing_country: '',
  notes: '',
  footer: '',
  due_at: '',
})

watch(
  () => props.visible,
  visible => {
    if (!visible || !props.invoiceId) {
      detail.value = null
      downloadData.value = null
      return
    }
    loadInvoice()
  },
)

async function loadInvoice() {
  if (!props.invoiceId) return

  loading.value = true
  try {
    const res = await getInvoice(props.invoiceId)
    detail.value = res.data
  } catch {
    detail.value = buildMockInvoice(props.invoiceId)
  } finally {
    if (detail.value) {
      form.value = {
        status: detail.value.status,
        billing_name: detail.value.billing_name || '',
        billing_email: detail.value.billing_email || '',
        billing_address: detail.value.billing_address || '',
        billing_city: detail.value.billing_city || '',
        billing_state: detail.value.billing_state || '',
        billing_zip: detail.value.billing_zip || '',
        billing_country: detail.value.billing_country || '',
        notes: detail.value.notes || '',
        footer: detail.value.footer || '',
        due_at: detail.value.due_at ? detail.value.due_at.slice(0, 10) : '',
      }
    }
    loading.value = false
  }
}

async function saveChanges() {
  if (!detail.value) return

  saving.value = true
  try {
    const payload: UpdateInvoiceData = {
      status: form.value.status,
      billing_name: form.value.billing_name || undefined,
      billing_email: form.value.billing_email || undefined,
      billing_address: form.value.billing_address || undefined,
      billing_city: form.value.billing_city || undefined,
      billing_state: form.value.billing_state || undefined,
      billing_zip: form.value.billing_zip || undefined,
      billing_country: form.value.billing_country || undefined,
      notes: form.value.notes || undefined,
      footer: form.value.footer || undefined,
      due_at: form.value.due_at || undefined,
      metadata: detail.value.metadata || undefined,
    }
    await updateInvoice(detail.value.id, payload)
    await loadInvoice()
    emit('updated')
  } catch {
    // noop
  } finally {
    saving.value = false
  }
}

async function handleDownload() {
  if (!detail.value) return

  downloading.value = true
  try {
    const res = await downloadInvoice(detail.value.id)
    downloadData.value = res.data
  } catch {
    downloadData.value = {
      invoice: { invoice_number: detail.value.invoice_number, total: detail.value.total, currency: detail.value.currency, status: detail.value.status },
      company: { name: 'Klek AI', email: 'billing@klek.ai' },
    }
  } finally {
    downloading.value = false
  }
}

async function handleDelete() {
  if (!detail.value) return

  actionLoading.value = true
  try {
    await deleteInvoice(detail.value.id)
    await loadInvoice()
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
    await restoreInvoice(detail.value.id)
    await loadInvoice()
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

function buildMockInvoice(id: number): InvoiceDetail {
  return {
    id,
    uuid: 'inv_001',
    invoice_number: 'INV-2026-0001',
    status: 'paid',
    subtotal: 49,
    discount_amount: 5,
    tax_amount: 2.2,
    total: 46.2,
    currency: 'USD',
    billing_name: 'Sara Ahmed',
    billing_email: 'sara@klek.ai',
    billing_address: '25 Nile Street',
    billing_city: 'Cairo',
    billing_state: 'Cairo',
    billing_zip: '11511',
    billing_country: 'EG',
    line_items: [
      { description: 'Pro Subscription - Monthly', quantity: 1, unit_price: 49, total: 49 },
      { description: 'Spring Discount', quantity: 1, unit_price: -5, total: -5 },
    ],
    notes: 'Thank you for your business.',
    footer: 'Generated by Klek AI Billing.',
    metadata: { source: 'payment_autogen' },
    issued_at: '2026-04-04T08:29:00Z',
    due_at: '2026-04-11T08:29:00Z',
    paid_at: '2026-04-04T08:30:00Z',
    created_at: '2026-04-04T08:29:00Z',
    updated_at: '2026-04-04T08:30:00Z',
    deleted_at: null,
    user: { id: 1, name: 'Sara Ahmed', email: 'sara@klek.ai', avatar: null },
    payment: { id: 501, uuid: 'pay_001', payment_gateway: 'stripe', gateway_payment_id: 'pi_001', status: 'completed', amount: 49, net_amount: 46.2, payment_method: 'card', paid_at: '2026-04-04T08:30:00Z' },
    subscription: { id: 21, status: 'active', billing_cycle: 'monthly', starts_at: '2026-04-04T08:30:00Z', ends_at: '2026-05-04T08:30:00Z', plan: { id: 3, name: 'Pro', slug: 'pro' } },
  }
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

function initials(name: string) {
  return name.split(' ').map(item => item[0]).join('').slice(0, 2)
}

function formatJson(value: Record<string, unknown> | null) {
  if (!value) return '—'
  return JSON.stringify(value, null, 2)
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="close"
    :header="t('invoiceDetail.title')"
    :modal="true"
    position="right"
    :style="{ width: '680px', maxWidth: '95vw', height: '100vh', margin: 0, borderRadius: 0 }"
    :draggable="false"
    class="invoice-detail-drawer"
  >
    <div v-if="loading" class="drawer-loading"><i class="pi pi-spin pi-spinner" /></div>

    <div v-else-if="detail" class="drawer-content">
      <div class="hero-card">
        <div class="hero-row">
          <div class="hero-main">
            <div class="hero-title-row">
              <h2>{{ detail.invoice_number }}</h2>
              <Tag :value="detail.status" :severity="invoiceStatusSeverity(detail.status)" class="mini-tag" />
            </div>
            <p class="hero-sub">{{ detail.user.name }} · {{ detail.payment?.uuid || t('invoiceDetail.noPaymentLinked') }}</p>
            <p class="hero-desc">{{ formatMoney(detail.total, detail.currency) }} total · issued {{ formatDateTime(detail.issued_at || detail.created_at) }}</p>
          </div>
          <div class="hero-actions">
            <Button icon="pi pi-download" severity="secondary" text rounded size="small" :loading="downloading" @click="handleDownload" />
            <Button v-if="detail.deleted_at" icon="pi pi-replay" severity="secondary" text rounded size="small" :loading="actionLoading" @click="handleRestore" />
            <Button v-else icon="pi pi-trash" severity="danger" text rounded size="small" :loading="actionLoading" @click="handleDelete" />
          </div>
        </div>

        <div class="stats-grid">
          <article class="stat-card">
            <span class="stat-k">{{ t('invoiceDetail.subtotal') }}</span>
            <strong>{{ formatMoney(detail.subtotal, detail.currency) }}</strong>
            <small>{{ formatMoney(detail.discount_amount, detail.currency) }} {{ t('common.discount') }}</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">{{ t('invoiceDetail.tax') }}</span>
            <strong>{{ formatMoney(detail.tax_amount, detail.currency) }}</strong>
            <small>{{ formatMoney(detail.total, detail.currency) }} {{ t('common.total') }}</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">{{ t('invoiceDetail.due') }}</span>
            <strong>{{ formatDateTime(detail.due_at) }}</strong>
            <small>{{ t('invoiceDetail.paidSub', { date: formatDateTime(detail.paid_at) }) }}</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">{{ t('invoiceDetail.items') }}</span>
            <strong>{{ detail.line_items?.length || 0 }}</strong>
            <small>{{ detail.subscription?.plan.name || t('invoiceDetail.noPlan') }}</small>
          </article>
        </div>
      </div>

      <Tabs value="overview" class="drawer-tabs">
        <TabList>
          <Tab value="overview">{{ t('invoiceDetail.overview') }}</Tab>
          <Tab value="billing">{{ t('invoiceDetail.billing') }}</Tab>
          <Tab value="download">{{ t('invoiceDetail.download') }}</Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="overview">
            <div class="section-grid">
              <section class="info-card">
                <h3 class="section-title">{{ t('invoiceDetail.customer') }}</h3>
                <div class="user-row">
                  <div class="user-avatar">
                    <img v-if="detail.user.avatar" :src="detail.user.avatar" :alt="detail.user.name" />
                    <span v-else>{{ initials(detail.user.name) }}</span>
                  </div>
                  <div class="user-copy">
                    <span class="user-name">{{ detail.user.name }}</span>
                    <span class="user-sub">{{ detail.user.email }}</span>
                  </div>
                </div>
              </section>

              <section class="info-card">
                <h3 class="section-title">{{ t('invoiceDetail.links') }}</h3>
                <div class="meta-list">
                  <div class="meta-row"><span>{{ t('invoiceDetail.payment') }}</span><span>{{ detail.payment?.uuid || '—' }}</span></div>
                  <div class="meta-row"><span>{{ t('invoiceDetail.gateway') }}</span><span>{{ detail.payment?.payment_gateway || '—' }}</span></div>
                  <div class="meta-row"><span>{{ t('invoiceDetail.plan') }}</span><span>{{ detail.subscription?.plan.name || '—' }}</span></div>
                  <div class="meta-row"><span>{{ t('invoiceDetail.cycle') }}</span><span>{{ detail.subscription?.billing_cycle || '—' }}</span></div>
                </div>
              </section>
            </div>

            <section class="info-card">
              <h3 class="section-title">{{ t('invoiceDetail.lineItems') }}</h3>
              <div class="mini-list stack-list">
                <div v-for="(item, index) in detail.line_items || []" :key="`${item.description}-${index}`" class="stack-row">
                  <div>
                    <span class="row-title">{{ item.description }}</span>
                    <span class="row-sub">{{ t('invoiceDetail.qty') }} {{ item.quantity }} · {{ t('invoiceDetail.unit') }} {{ formatMoney(item.unit_price, detail.currency) }}</span>
                  </div>
                  <div class="row-meta">
                    <span class="row-title">{{ formatMoney(item.total, detail.currency) }}</span>
                  </div>
                </div>
              </div>
            </section>
          </TabPanel>

          <TabPanel value="billing">
            <div class="edit-grid">
              <div class="form-field">
                <label>{{ t('invoiceDetail.status') }}</label>
                <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>{{ t('invoiceDetail.dueDate') }}</label>
                <input v-model="form.due_at" type="date" class="native-input" />
              </div>
              <div class="form-field">
                <label>{{ t('invoiceDetail.billingName') }}</label>
                <InputText v-model="form.billing_name" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>{{ t('invoiceDetail.billingEmail') }}</label>
                <InputText v-model="form.billing_email" size="small" class="w-full" />
              </div>
              <div class="form-field form-field-full">
                <label>{{ t('invoiceDetail.address') }}</label>
                <InputText v-model="form.billing_address" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>{{ t('invoiceDetail.city') }}</label>
                <InputText v-model="form.billing_city" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>{{ t('invoiceDetail.state') }}</label>
                <InputText v-model="form.billing_state" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>{{ t('invoiceDetail.zip') }}</label>
                <InputText v-model="form.billing_zip" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>{{ t('invoiceDetail.country') }}</label>
                <InputText v-model="form.billing_country" size="small" class="w-full" />
              </div>
              <div class="form-field form-field-full">
                <label>{{ t('invoiceDetail.notes') }}</label>
                <Textarea v-model="form.notes" rows="3" autoResize class="w-full" />
              </div>
              <div class="form-field form-field-full">
                <label>{{ t('invoiceDetail.footer') }}</label>
                <Textarea v-model="form.footer" rows="3" autoResize class="w-full" />
              </div>
            </div>
            <div class="edit-actions">
              <Button :label="t('invoiceDetail.saveChanges')" size="small" :loading="saving" @click="saveChanges" />
            </div>
          </TabPanel>

          <TabPanel value="download">
            <div class="section-head-inline">
              <h3 class="section-title">{{ t('invoiceDetail.downloadPayload') }}</h3>
              <Button :label="t('invoiceDetail.fetchDownloadData')" size="small" :loading="downloading" @click="handleDownload" />
            </div>

            <div class="payload-grid">
              <section class="payload-card">
                <h3 class="section-title">{{ t('invoiceDetail.company') }}</h3>
                <pre>{{ formatJson(downloadData?.company ? { name: downloadData.company.name, email: downloadData.company.email } : null) }}</pre>
              </section>
              <section class="payload-card">
                <h3 class="section-title">{{ t('invoiceDetail.invoicePayload') }}</h3>
                <pre>{{ formatJson((downloadData?.invoice as Record<string, unknown> | null) || null) }}</pre>
              </section>
              <section class="payload-card">
                <h3 class="section-title">{{ t('invoiceDetail.metadata') }}</h3>
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
.info-card,.payload-card { border: 1px solid var(--card-border); border-radius: 10px; background: var(--card-bg); padding: 10px; }
.payload-card pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-size: 0.65rem; color: var(--text-primary); }
.section-title { margin: 0 0 8px; font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
.section-head-inline { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.user-row { display: flex; align-items: center; gap: 8px; }
.user-avatar { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; overflow: hidden; flex-shrink: 0; }
.user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.user-copy { display: flex; flex-direction: column; gap: 2px; }
.user-name { font-size: 0.74rem; font-weight: 600; color: var(--text-primary); }
.user-sub { font-size: 0.62rem; color: var(--text-muted); }
.meta-list { display: flex; flex-direction: column; gap: 0; border: 1px solid var(--card-border); border-radius: 8px; overflow: hidden; }
.meta-row { display: flex; justify-content: space-between; gap: 10px; padding: 7px 9px; border-bottom: 1px solid var(--card-border); font-size: 0.66rem; }
.meta-row:last-child { border-bottom: none; }
.meta-row span:first-child { color: var(--text-muted); }
.meta-row span:last-child { color: var(--text-primary); text-align: right; }
.stack-list { display: flex; flex-direction: column; gap: 8px; }
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
.edit-actions { display: flex; justify-content: flex-end; margin-top: 10px; }
.mini-tag { font-size: 0.56rem !important; padding: 2px 7px !important; }
:deep(.invoice-detail-drawer) { margin: 0 !important; border-radius: 0 !important; }
:deep(.invoice-detail-drawer .p-dialog-header) { background: var(--card-bg); border-color: var(--card-border); color: var(--text-primary); padding: 10px 16px; }
:deep(.invoice-detail-drawer .p-dialog-content) { background: var(--card-bg); color: var(--text-primary); padding: 12px 16px; overflow-y: auto; }
</style>
