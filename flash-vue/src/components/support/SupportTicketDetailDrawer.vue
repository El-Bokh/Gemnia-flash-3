<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  assignTicket,
  closeTicket,
  deleteSupportTicket,
  getSupportTicket,
  reopenTicket,
  replyToTicket,
  restoreSupportTicket,
  updateSupportTicket,
} from '@/services/supportTicketService'
import { getUsers } from '@/services/userService'
import type {
  AssignTicketData,
  ListSupportTicketsParams,
  ReplyTicketData,
  SupportTicketDetail,
  UpdateSupportTicketData,
} from '@/types/supportTickets'
import type { User } from '@/types/users'
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

type TicketStatus = NonNullable<ListSupportTicketsParams['status']>
type TicketPriority = NonNullable<ListSupportTicketsParams['priority']>

const props = defineProps<{
  visible: boolean
  ticketId: number | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void
  (e: 'updated'): void
}>()

const { t } = useI18n()
const loading = ref(false)
const saving = ref(false)
const replying = ref(false)
const assigning = ref(false)
const stateActionLoading = ref(false)
const destructiveLoading = ref(false)

const detail = ref<SupportTicketDetail | null>(null)
const agentOptions = ref<Array<{ label: string; value: number; meta: string }>>([])

const statusOptions = computed(() => [
  { label: t('support.open'), value: 'open' },
  { label: t('support.inProgress'), value: 'in_progress' },
  { label: t('support.waitingReply'), value: 'waiting_reply' },
  { label: t('support.resolved'), value: 'resolved' },
  { label: t('ticketDetail.closed'), value: 'closed' },
] as Array<{ label: string; value: TicketStatus }>)

const priorityOptions = computed(() => [
  { label: t('support.low'), value: 'low' },
  { label: t('support.medium'), value: 'medium' },
  { label: t('support.high'), value: 'high' },
  { label: t('support.urgent'), value: 'urgent' },
] as Array<{ label: string; value: TicketPriority }>)

const form = ref<{
  status: TicketStatus
  priority: TicketPriority
  category: string
}>({
  status: 'open',
  priority: 'medium',
  category: '',
})

const assignForm = ref<{ assigned_to: number | null }>({ assigned_to: null })
const replyForm = ref<{ message: string }>({ message: '' })

type ConversationEntry = {
  id: number | string
  message: string
  is_staff_reply: boolean
  attachments: string[] | null
  created_at: string
  user: {
    name: string
    avatar: string | null
  }
  is_opening?: boolean
}

const stateActionLabel = computed(() => {
  if (!detail.value) return t('ticketDetail.closeTicket')
  return detail.value.status === 'closed' ? t('ticketDetail.reopenTicket') : t('ticketDetail.closeTicket')
})

const replyDisabled = computed(() => !detail.value || detail.value.status === 'closed' || !!detail.value.deleted_at)

const conversationMessages = computed<ConversationEntry[]>(() => {
  if (!detail.value) return []

  return [
    {
      id: `opening-${detail.value.id}`,
      message: detail.value.message,
      is_staff_reply: false,
      attachments: detail.value.attachments,
      created_at: detail.value.created_at,
      user: {
        name: detail.value.user.name,
        avatar: detail.value.user.avatar,
      },
      is_opening: true,
    },
    ...(detail.value.replies ?? []).map(reply => ({
      id: reply.id,
      message: reply.message,
      is_staff_reply: reply.is_staff_reply,
      attachments: reply.attachments,
      created_at: reply.created_at,
      user: {
        name: reply.user.name,
        avatar: reply.user.avatar,
      },
    })),
  ]
})

watch(
  () => props.visible,
  visible => {
    if (!visible || !props.ticketId) {
      detail.value = null
      return
    }

    Promise.all([loadAgents(), loadTicket()])
  },
)

async function loadAgents() {
  try {
    const res = await getUsers({ per_page: 60, sort_by: 'created_at', sort_dir: 'desc' })
    const candidates = res.data.filter(user => {
      const roleNames = (user.roles || []).map(role => role.name.toLowerCase())
      return roleNames.some(role => role.includes('admin') || role.includes('support') || role.includes('moderator') || role.includes('agent'))
    })
    const source = candidates.length ? candidates : res.data
    agentOptions.value = source.map(user => ({
      label: user.name,
      value: user.id,
      meta: `${user.email} · ${(user.roles || []).map(role => role.name).join(', ') || 'Team Member'}`,
    }))
  } catch {
    agentOptions.value = [
      { label: 'Nour Sayed', value: 6, meta: 'nour@klek.ai · Support Agent' },
      { label: 'Mona Khaled', value: 3, meta: 'mona@klek.ai · Admin' },
      { label: 'Omar Ali', value: 2, meta: 'omar@klek.ai · Moderator' },
    ]
  }
}

async function loadTicket() {
  if (!props.ticketId) return

  loading.value = true
  try {
    const res = await getSupportTicket(props.ticketId)
    detail.value = res.data
  } catch {
    detail.value = buildMockTicket(props.ticketId)
  } finally {
    if (detail.value) {
      form.value = {
        status: detail.value.status as TicketStatus,
        priority: detail.value.priority as TicketPriority,
        category: detail.value.category || '',
      }
      assignForm.value = { assigned_to: detail.value.assigned_agent?.id || null }
      replyForm.value = { message: '' }
    }
    loading.value = false
  }
}

async function saveChanges() {
  if (!detail.value) return

  saving.value = true
  try {
    const payload: UpdateSupportTicketData = {
      status: form.value.status,
      priority: form.value.priority,
      category: form.value.category || null,
      metadata: detail.value.metadata || undefined,
    }
    await updateSupportTicket(detail.value.id, payload)
    await loadTicket()
    emit('updated')
  } catch {
    // noop
  } finally {
    saving.value = false
  }
}

async function saveAssignment() {
  if (!detail.value || !assignForm.value.assigned_to) return

  assigning.value = true
  try {
    const payload: AssignTicketData = { assigned_to: assignForm.value.assigned_to }
    await assignTicket(detail.value.id, payload)
    await loadTicket()
    emit('updated')
  } catch {
    // noop
  } finally {
    assigning.value = false
  }
}

async function sendReply() {
  if (!detail.value || !replyForm.value.message.trim()) return

  replying.value = true
  try {
    const payload: ReplyTicketData = { message: replyForm.value.message }
    await replyToTicket(detail.value.id, payload)
    await loadTicket()
    emit('updated')
  } catch {
    // noop
  } finally {
    replying.value = false
  }
}

async function toggleTicketState() {
  if (!detail.value) return

  stateActionLoading.value = true
  try {
    if (detail.value.status === 'closed') {
      await reopenTicket(detail.value.id)
    } else {
      await closeTicket(detail.value.id)
    }
    await loadTicket()
    emit('updated')
  } catch {
    // noop
  } finally {
    stateActionLoading.value = false
  }
}

async function handleDelete() {
  if (!detail.value) return

  destructiveLoading.value = true
  try {
    await deleteSupportTicket(detail.value.id)
    await loadTicket()
    emit('updated')
  } catch {
    // noop
  } finally {
    destructiveLoading.value = false
  }
}

async function handleRestore() {
  if (!detail.value) return

  destructiveLoading.value = true
  try {
    await restoreSupportTicket(detail.value.id)
    await loadTicket()
    emit('updated')
  } catch {
    // noop
  } finally {
    destructiveLoading.value = false
  }
}

function close() {
  emit('update:visible', false)
}

function buildMockTicket(id: number): SupportTicketDetail {
  return {
    id,
    uuid: 'tkt_001',
    ticket_number: 'SUP-2026-0018',
    subject: 'Refund was processed but credits were not restored',
    message: 'The customer completed a refund for the previous Pro renewal, but the subscription credits are still deducted from the workspace. Please check payment and credit ledger sync.',
    status: 'in_progress',
    priority: 'high',
    category: 'billing',
    attachments: ['refund-receipt.pdf', 'ledger-screenshot.png'],
    metadata: { source: 'dashboard_widget', sentiment: 'frustrated', sla_minutes_left: 82 },
    last_reply_at: '2026-04-04T09:38:00Z',
    resolved_at: null,
    closed_at: null,
    created_at: '2026-04-04T08:55:00Z',
    updated_at: '2026-04-04T09:38:00Z',
    deleted_at: null,
    replies_count: 3,
    user: {
      id: 1,
      name: 'Sara Ahmed',
      email: 'sara@klek.ai',
      avatar: null,
      phone: '+20 1000000000',
      status: 'active',
      subscription: {
        id: 21,
        status: 'active',
        billing_cycle: 'monthly',
        starts_at: '2026-04-04T08:30:00Z',
        ends_at: '2026-05-04T08:30:00Z',
        plan: { id: 3, name: 'Pro', slug: 'pro' },
      },
    },
    assigned_agent: { id: 6, name: 'Nour Sayed', email: 'nour@klek.ai', avatar: null },
    replies: [
      {
        id: 91,
        message: 'I can confirm the refund landed on Stripe, but my credits remain locked in the account.',
        is_staff_reply: false,
        attachments: null,
        created_at: '2026-04-04T08:55:00Z',
        updated_at: '2026-04-04T08:55:00Z',
        user: { id: 1, name: 'Sara Ahmed', email: 'sara@klek.ai', avatar: null },
      },
      {
        id: 92,
        message: 'We are reviewing the credit ledger now. I will update you once reconciliation finishes.',
        is_staff_reply: true,
        attachments: null,
        created_at: '2026-04-04T09:12:00Z',
        updated_at: '2026-04-04T09:12:00Z',
        user: { id: 6, name: 'Nour Sayed', email: 'nour@klek.ai', avatar: null },
      },
      {
        id: 93,
        message: 'Understood. Sharing the latest screenshot from the billing page for reference.',
        is_staff_reply: false,
        attachments: ['billing-page.png'],
        created_at: '2026-04-04T09:38:00Z',
        updated_at: '2026-04-04T09:38:00Z',
        user: { id: 1, name: 'Sara Ahmed', email: 'sara@klek.ai', avatar: null },
      },
    ],
  }
}

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

function formatDateTime(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
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
    :header="t('ticketDetail.title')"
    :modal="true"
    position="right"
    :style="{ width: '720px', maxWidth: '96vw', height: '100vh', margin: 0, borderRadius: 0 }"
    :draggable="false"
    class="support-ticket-drawer"
  >
    <div v-if="loading" class="drawer-loading"><i class="pi pi-spin pi-spinner" /></div>

    <div v-else-if="detail" class="drawer-content">
      <div class="hero-card">
        <div class="hero-row">
          <div class="hero-main">
            <div class="hero-title-row">
              <h2>{{ detail.subject }}</h2>
              <Tag :value="detail.status" :severity="ticketStatusSeverity(detail.status)" class="mini-tag" />
              <Tag :value="detail.priority" :severity="ticketPrioritySeverity(detail.priority)" class="mini-tag" />
            </div>
            <p class="hero-sub">{{ detail.ticket_number }} · {{ detail.user.name }} · {{ detail.category || 'general' }}</p>
            <p class="hero-desc">{{ detail.message }}</p>
          </div>
          <div class="hero-actions">
            <Button :label="stateActionLabel" size="small" severity="secondary" outlined :loading="stateActionLoading" @click="toggleTicketState" />
            <Button v-if="detail.deleted_at" icon="pi pi-replay" severity="secondary" text rounded size="small" :loading="destructiveLoading" @click="handleRestore" />
            <Button v-else icon="pi pi-trash" severity="danger" text rounded size="small" :loading="destructiveLoading" @click="handleDelete" />
          </div>
        </div>

        <div class="stats-grid">
          <article class="stat-card">
            <span class="stat-k">{{ t('ticketDetail.replies') }}</span>
            <strong>{{ detail.replies_count }}</strong>
            <small>{{ formatDateTime(detail.last_reply_at) }}</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">{{ t('ticketDetail.assigned') }}</span>
            <strong>{{ detail.assigned_agent?.name || t('ticketDetail.unassigned') }}</strong>
            <small>{{ detail.assigned_agent?.email || t('ticketDetail.queue') }}</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">{{ t('ticketDetail.created') }}</span>
            <strong>{{ formatDateTime(detail.created_at) }}</strong>
            <small>{{ formatDateTime(detail.updated_at) }} updated</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">{{ t('ticketDetail.closed') }}</span>
            <strong>{{ detail.closed_at ? 'Yes' : 'No' }}</strong>
            <small>{{ formatDateTime(detail.closed_at) }}</small>
          </article>
        </div>
      </div>

      <Tabs value="overview" class="drawer-tabs">
        <TabList>
          <Tab value="overview">{{ t('ticketDetail.overview') }}</Tab>
          <Tab value="conversation">{{ t('ticketDetail.conversation') }}</Tab>
          <Tab value="workflow">{{ t('ticketDetail.workflow') }}</Tab>
          <Tab value="payloads">{{ t('ticketDetail.payloads') }}</Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="overview">
            <div class="section-grid">
              <section class="info-card">
                <h3 class="section-title">{{ t('ticketDetail.customer') }}</h3>
                <div class="user-row">
                  <div class="user-avatar">
                    <img v-if="detail.user.avatar" :src="detail.user.avatar" :alt="detail.user.name" />
                    <span v-else>{{ initials(detail.user.name) }}</span>
                  </div>
                  <div class="user-copy">
                    <span class="user-name">{{ detail.user.name }}</span>
                    <span class="user-sub">{{ detail.user.email }}</span>
                    <span class="user-sub">{{ detail.user.phone || t('ticketDetail.noPhone') }} · {{ detail.user.status }}</span>
                  </div>
                </div>
              </section>

              <section class="info-card">
                <h3 class="section-title">{{ t('ticketDetail.subscription') }}</h3>
                <div class="meta-list">
                  <div class="meta-row"><span>{{ t('invoiceDetail.plan') }}</span><span>{{ detail.user.subscription?.plan?.name || '—' }}</span></div>
                  <div class="meta-row"><span>{{ t('invoiceDetail.cycle') }}</span><span>{{ detail.user.subscription?.billing_cycle || '—' }}</span></div>
                  <div class="meta-row"><span>{{ t('common.status') }}</span><span>{{ detail.user.subscription?.status || '—' }}</span></div>
                  <div class="meta-row"><span>{{ t('ticketDetail.ends') }}</span><span>{{ formatDateTime(detail.user.subscription?.ends_at || null) }}</span></div>
                </div>
              </section>
            </div>

            <section class="info-card">
              <h3 class="section-title">{{ t('ticketDetail.ticketMeta') }}</h3>
              <div class="meta-list">
                <div class="meta-row"><span>{{ t('ticketDetail.uuid') }}</span><span>{{ detail.uuid }}</span></div>
                <div class="meta-row"><span>{{ t('ticketDetail.category') }}</span><span>{{ detail.category || '—' }}</span></div>
                <div class="meta-row"><span>{{ t('ticketDetail.resolved') }}</span><span>{{ formatDateTime(detail.resolved_at) }}</span></div>
                <div class="meta-row"><span>{{ t('ticketDetail.closed') }}</span><span>{{ formatDateTime(detail.closed_at) }}</span></div>
              </div>
            </section>
          </TabPanel>

          <TabPanel value="conversation">
            <div class="conversation-panel">
              <div class="thread-list">
                <article v-for="entry in conversationMessages" :key="entry.id" class="thread-item" :class="{ staff: entry.is_staff_reply, opening: entry.is_opening }">
                <div class="thread-head">
                  <div class="thread-user">
                    <div class="thread-avatar">
                      <img v-if="entry.user.avatar" :src="entry.user.avatar" :alt="entry.user.name" />
                      <span v-else>{{ initials(entry.user.name) }}</span>
                    </div>
                    <div class="thread-copy">
                      <span class="thread-name">{{ entry.user.name }}</span>
                      <span class="thread-meta">{{ entry.is_opening ? t('ticketDetail.openingMessage') : (entry.is_staff_reply ? t('ticketDetail.staffReply') : t('ticketDetail.customerReply')) }} · {{ formatDateTime(entry.created_at) }}</span>
                    </div>
                  </div>
                  <Tag :value="entry.is_staff_reply ? t('ticketDetail.staff') : t('ticketDetail.customerTag')" :severity="entry.is_staff_reply ? 'info' : 'secondary'" class="mini-tag" />
                </div>
                <p class="thread-message">{{ entry.message }}</p>
                <div v-if="entry.attachments?.length" class="attachment-row">
                  <span v-for="attachment in entry.attachments" :key="attachment" class="attachment-chip">{{ attachment }}</span>
                </div>
              </article>
            </div>

              <section class="info-card conversation-reply-card">
                <h3 class="section-title">{{ t('ticketDetail.reply') }}</h3>
                <div class="form-field form-field-full">
                  <label>{{ t('ticketDetail.message') }}</label>
                  <Textarea v-model="replyForm.message" rows="5" autoResize class="w-full" :placeholder="t('ticketDetail.messagePlaceholder')" :disabled="replyDisabled" />
                </div>
                <div class="edit-actions">
                  <Button :label="t('ticketDetail.sendReply')" size="small" :disabled="replyDisabled || !replyForm.message.trim()" :loading="replying" @click="sendReply" />
                </div>
              </section>
            </div>
          </TabPanel>

          <TabPanel value="workflow">
            <section class="info-card">
              <h3 class="section-title">{{ t('ticketDetail.ticketSettings') }}</h3>
              <div class="edit-grid">
                <div class="form-field">
                  <label>{{ t('common.status') }}</label>
                  <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
                </div>
                <div class="form-field">
                  <label>{{ t('support.priority') }}</label>
                  <Select v-model="form.priority" :options="priorityOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
                </div>
                <div class="form-field form-field-full">
                  <label>{{ t('ticketDetail.category') }}</label>
                  <InputText v-model="form.category" size="small" class="w-full" placeholder="billing / technical / account" />
                </div>
              </div>
              <div class="edit-actions">
                <Button :label="t('ticketDetail.saveChanges')" size="small" :loading="saving" @click="saveChanges" />
              </div>
            </section>

            <section class="info-card">
              <h3 class="section-title">{{ t('ticketDetail.assignment') }}</h3>
              <div class="edit-grid">
                <div class="form-field form-field-full">
                  <label>{{ t('ticketDetail.assignTo') }}</label>
                  <Select v-model="assignForm.assigned_to" :options="agentOptions" optionLabel="label" optionValue="value" size="small" class="w-full">
                    <template #option="slotProps">
                      <div class="agent-option">
                        <span class="agent-label">{{ slotProps.option.label }}</span>
                        <small class="agent-meta">{{ slotProps.option.meta }}</small>
                      </div>
                    </template>
                  </Select>
                </div>
              </div>
              <div class="edit-actions">
                <Button :label="t('ticketDetail.assignTicket')" size="small" severity="secondary" :disabled="!assignForm.assigned_to" :loading="assigning" @click="saveAssignment" />
              </div>
            </section>

          </TabPanel>

          <TabPanel value="payloads">
            <div class="payload-grid">
              <section class="payload-card">
                <h3 class="section-title">{{ t('ticketDetail.attachments') }}</h3>
                <div v-if="detail.attachments?.length" class="attachment-row multi-line">
                  <span v-for="attachment in detail.attachments" :key="attachment" class="attachment-chip">{{ attachment }}</span>
                </div>
                <pre v-else>—</pre>
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
.hero-row { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.hero-main { min-width: 0; flex: 1; }
.hero-title-row { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.hero-title-row h2 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.hero-sub { margin: 4px 0 0; font-size: 0.64rem; color: var(--text-muted); }
.hero-desc { margin: 8px 0 0; font-size: 0.74rem; line-height: 1.45; color: var(--text-primary); }
.hero-actions { display: flex; gap: 6px; align-items: flex-start; flex-wrap: wrap; justify-content: flex-end; }
.stats-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
@media (min-width: 640px) { .stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.stat-card { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border-radius: 10px; background: var(--hover-bg); }
.stat-k { font-size: 0.58rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
.stat-card strong { font-size: 0.82rem; color: var(--text-primary); }
.stat-card small { font-size: 0.62rem; color: var(--text-muted); }
:deep(.drawer-tabs .p-tablist) { background: transparent; }
:deep(.drawer-tabs .p-tab) { font-size: 0.7rem !important; padding: 6px 10px !important; color: var(--text-muted) !important; background: transparent !important; border: none !important; }
:deep(.drawer-tabs .p-tab-active) { color: var(--active-color) !important; }
:deep(.drawer-tabs .p-tabpanels) { background: transparent; padding: 10px 0 0 !important; }
.section-grid,.payload-grid,.edit-grid { display: grid; grid-template-columns: 1fr; gap: 8px; }
@media (min-width: 768px) { .section-grid,.payload-grid,.edit-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.info-card,.payload-card { border: 1px solid var(--card-border); border-radius: 10px; background: var(--card-bg); padding: 10px; }
.section-title { margin: 0 0 8px; font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
.payload-card pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-size: 0.65rem; color: var(--text-primary); }
.user-row,.thread-user { display: flex; align-items: center; gap: 8px; }
.user-avatar,.thread-avatar { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #f59e0b, #ea580c); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; overflow: hidden; flex-shrink: 0; }
.user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.thread-avatar img { width: 100%; height: 100%; object-fit: cover; }
.user-copy,.thread-copy { display: flex; flex-direction: column; gap: 2px; }
.user-name,.thread-name { font-size: 0.74rem; font-weight: 600; color: var(--text-primary); }
.user-sub,.thread-meta { font-size: 0.62rem; color: var(--text-muted); }
.meta-list { display: flex; flex-direction: column; gap: 0; border: 1px solid var(--card-border); border-radius: 8px; overflow: hidden; }
.meta-row { display: flex; justify-content: space-between; gap: 10px; padding: 7px 9px; border-bottom: 1px solid var(--card-border); font-size: 0.66rem; }
.meta-row:last-child { border-bottom: none; }
.meta-row span:first-child { color: var(--text-muted); }
.meta-row span:last-child { color: var(--text-primary); text-align: right; }
.conversation-panel { display: flex; flex-direction: column; gap: 10px; }
.thread-list { display: flex; flex-direction: column; gap: 8px; }
.thread-item { padding: 10px; border: 1px solid var(--card-border); border-radius: 10px; background: var(--card-bg); }
.thread-item.staff { background: color-mix(in srgb, var(--card-bg) 88%, rgba(14, 165, 233, 0.08) 12%); }
.thread-item.opening { border-style: dashed; }
.thread-head { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; margin-bottom: 8px; }
.thread-message { margin: 0; font-size: 0.72rem; line-height: 1.5; color: var(--text-primary); }
.attachment-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.attachment-row.multi-line { margin-top: 0; }
.attachment-chip { display: inline-flex; align-items: center; padding: 3px 8px; border-radius: 999px; background: var(--hover-bg); font-size: 0.62rem; color: var(--text-secondary); }
.conversation-reply-card { position: sticky; bottom: 0; }
.form-field { display: flex; flex-direction: column; gap: 4px; }
.form-field label { font-size: 0.68rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; }
.form-field-full { grid-column: 1 / -1; }
.w-full { width: 100%; }
.edit-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px; }
.mini-tag { font-size: 0.56rem !important; padding: 2px 7px !important; }
.agent-option { display: flex; flex-direction: column; gap: 2px; }
.agent-label { font-size: 0.7rem; color: var(--text-primary); }
.agent-meta { font-size: 0.6rem; color: var(--text-muted); }
:deep(.support-ticket-drawer) { margin: 0 !important; border-radius: 0 !important; }
:deep(.support-ticket-drawer .p-dialog-header) { background: var(--card-bg); border-color: var(--card-border); color: var(--text-primary); padding: 10px 16px; }
:deep(.support-ticket-drawer .p-dialog-content) { background: var(--card-bg); color: var(--text-primary); padding: 12px 16px; overflow-y: auto; }
</style>
