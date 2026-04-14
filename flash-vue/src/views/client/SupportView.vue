<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useSeo } from '@/composables/useSeo'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'
import Select from 'primevue/select'
import {
  getMyTickets,
  getMyTicket,
  createTicket,
  replyToMyTicket,
  type ClientTicket,
  type ClientTicketDetail,
} from '@/services/clientSupportService'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

useSeo({
  title: computed(() => t('clientSupport.pageTitle')),
  description: computed(() => t('clientSupport.pageSub')),
  path: '/support',
})

// ── State ──────────────────────────────────────────
const view = ref<'list' | 'detail' | 'new'>('list')
const loading = ref(false)
const sending = ref(false)
const tickets = ref<ClientTicket[]>([])
const totalTickets = ref(0)
const currentPage = ref(1)
const lastPage = ref(1)
const filterStatus = ref('')
const activeTicket = ref<ClientTicketDetail | null>(null)
const replyText = ref('')

// New ticket form
const newSubject = ref('')
const newMessage = ref('')
const newCategory = ref('')
const newPriority = ref('medium')

const statusOptions = computed(() => [
  { label: t('clientSupport.allStatuses'), value: '' },
  { label: t('clientSupport.open'), value: 'open' },
  { label: t('clientSupport.inProgress'), value: 'in_progress' },
  { label: t('clientSupport.waitingReply'), value: 'waiting_reply' },
  { label: t('clientSupport.resolved'), value: 'resolved' },
  { label: t('clientSupport.closed'), value: 'closed' },
])

const priorityOptions = computed(() => [
  { label: t('clientSupport.low'), value: 'low' },
  { label: t('clientSupport.medium'), value: 'medium' },
  { label: t('clientSupport.high'), value: 'high' },
])

const categoryOptions = computed(() => [
  { label: t('clientSupport.catGeneral'), value: 'general' },
  { label: t('clientSupport.catBilling'), value: 'billing' },
  { label: t('clientSupport.catTechnical'), value: 'technical' },
  { label: t('clientSupport.catAccount'), value: 'account' },
])

// ── Helpers ─────────────────────────────────────────
function statusSeverity(status: string) {
  const map: Record<string, string> = {
    open: 'info',
    in_progress: 'warn',
    waiting_reply: 'secondary',
    resolved: 'success',
    closed: 'danger',
  }
  return (map[status] ?? 'secondary') as 'info' | 'warn' | 'secondary' | 'success' | 'danger'
}

function statusLabel(status: string) {
  const map: Record<string, string> = {
    open: t('clientSupport.open'),
    in_progress: t('clientSupport.inProgress'),
    waiting_reply: t('clientSupport.waitingReply'),
    resolved: t('clientSupport.resolved'),
    closed: t('clientSupport.closed'),
  }
  return map[status] ?? status
}

function priorityLabel(p: string) {
  const map: Record<string, string> = {
    low: t('clientSupport.low'),
    medium: t('clientSupport.medium'),
    high: t('clientSupport.high'),
    urgent: t('clientSupport.urgent'),
  }
  return map[p] ?? p
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

// ── API Calls ───────────────────────────────────────
async function loadTickets(page = 1) {
  loading.value = true
  try {
    const res = await getMyTickets({
      status: filterStatus.value || undefined,
      page,
      per_page: 10,
    })
    tickets.value = Array.isArray(res.data) ? res.data : []
    totalTickets.value = res.meta?.total ?? tickets.value.length
    currentPage.value = res.meta?.current_page ?? 1
    lastPage.value = res.meta?.last_page ?? 1
  } catch { /* handled by interceptor */ } finally {
    loading.value = false
  }
}

async function openTicket(id: number) {
  loading.value = true
  try {
    const res = await getMyTicket(id)
    activeTicket.value = {
      ...res.data,
      replies: Array.isArray(res.data?.replies) ? res.data.replies : [],
    }
    view.value = 'detail'

    if (route.query.ticket !== String(id)) {
      void router.replace({
        name: 'support',
        query: { ...route.query, ticket: String(id) },
      })
    }
  } catch { /* handled by interceptor */ } finally {
    loading.value = false
  }
}

async function submitNewTicket() {
  if (!newSubject.value.trim() || !newMessage.value.trim()) return
  sending.value = true
  try {
    await createTicket({
      subject: newSubject.value.trim(),
      message: newMessage.value.trim(),
      category: newCategory.value || undefined,
      priority: newPriority.value,
    })
    newSubject.value = ''
    newMessage.value = ''
    newCategory.value = ''
    newPriority.value = 'medium'
    view.value = 'list'
    await loadTickets()
  } catch { /* handled by interceptor */ } finally {
    sending.value = false
  }
}

async function sendReply() {
  if (!replyText.value.trim() || !activeTicket.value) return
  sending.value = true
  try {
    const res = await replyToMyTicket(activeTicket.value.id, replyText.value.trim())
    activeTicket.value.replies = [...(activeTicket.value.replies ?? []), res.data]
    replyText.value = ''
  } catch { /* handled by interceptor */ } finally {
    sending.value = false
  }
}

function goBack() {
  view.value = 'list'
  activeTicket.value = null
  replyText.value = ''
  const nextQuery = { ...route.query }
  delete nextQuery.ticket
  void router.replace({ name: 'support', query: nextQuery })
  loadTickets(currentPage.value)
}

async function syncTicketFromRoute(ticketQuery: unknown) {
  const ticketId = typeof ticketQuery === 'string' ? Number(ticketQuery) : NaN

  if (Number.isInteger(ticketId) && ticketId > 0) {
    if (activeTicket.value?.id !== ticketId || view.value !== 'detail') {
      await openTicket(ticketId)
    }
    return
  }

  if (view.value === 'detail') {
    view.value = 'list'
    activeTicket.value = null
    replyText.value = ''
  }
}

onMounted(async () => {
  await loadTickets()
  await syncTicketFromRoute(route.query.ticket)
})

watch(() => route.query.ticket, ticketQuery => {
  void syncTicketFromRoute(ticketQuery)
})
</script>

<template>
  <div class="support-page">
    <!-- ═══ Header ═══ -->
    <div class="support-header">
      <div class="header-left">
        <Button
          v-if="view !== 'list'"
          icon="pi pi-arrow-left"
          severity="secondary"
          text
          rounded
          size="small"
          @click="goBack"
        />
        <div>
          <h1 class="page-title">{{ t('clientSupport.pageTitle') }}</h1>
          <p class="page-sub">{{ t('clientSupport.pageSub') }}</p>
        </div>
      </div>
      <Button
        v-if="view === 'list'"
        :label="t('clientSupport.newTicket')"
        icon="pi pi-plus"
        size="small"
        @click="view = 'new'"
      />
    </div>

    <!-- ═══ New Ticket Form ═══ -->
    <div v-if="view === 'new'" class="new-ticket-card">
      <h2 class="card-title">{{ t('clientSupport.newTicket') }}</h2>

      <div class="form-field">
        <label>{{ t('clientSupport.subject') }}</label>
        <InputText
          v-model="newSubject"
          :placeholder="t('clientSupport.subjectPlaceholder')"
          class="w-full"
          maxlength="255"
        />
      </div>

      <div class="form-row">
        <div class="form-field">
          <label>{{ t('clientSupport.category') }}</label>
          <Select
            v-model="newCategory"
            :options="categoryOptions"
            option-label="label"
            option-value="value"
            :placeholder="t('clientSupport.selectCategory')"
            class="w-full"
          />
        </div>
        <div class="form-field">
          <label>{{ t('clientSupport.priority') }}</label>
          <Select
            v-model="newPriority"
            :options="priorityOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
      </div>

      <div class="form-field">
        <label>{{ t('clientSupport.message') }}</label>
        <Textarea
          v-model="newMessage"
          :placeholder="t('clientSupport.messagePlaceholder')"
          rows="5"
          class="w-full"
          maxlength="5000"
        />
      </div>

      <div class="form-actions">
        <Button
          :label="t('clientSupport.cancel')"
          severity="secondary"
          text
          size="small"
          @click="view = 'list'"
        />
        <Button
          :label="t('clientSupport.submit')"
          icon="pi pi-send"
          size="small"
          :loading="sending"
          :disabled="!newSubject.trim() || !newMessage.trim()"
          @click="submitNewTicket"
        />
      </div>
    </div>

    <!-- ═══ Tickets List ═══ -->
    <div v-if="view === 'list'">
      <div class="filter-bar">
        <Select
          v-model="filterStatus"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          class="status-filter"
          @change="loadTickets(1)"
        />
        <span class="ticket-count">{{ totalTickets }} {{ t('clientSupport.tickets') }}</span>
      </div>

      <div v-if="loading" class="loading-state">
        <i class="pi pi-spin pi-spinner" />
      </div>

      <div v-else-if="tickets.length === 0" class="empty-state">
        <i class="pi pi-ticket empty-icon" />
        <p>{{ t('clientSupport.noTickets') }}</p>
        <Button
          :label="t('clientSupport.createFirst')"
          icon="pi pi-plus"
          size="small"
          @click="view = 'new'"
        />
      </div>

      <div v-else class="tickets-list">
        <div
          v-for="ticket in tickets"
          :key="ticket.id"
          class="ticket-card"
          @click="openTicket(ticket.id)"
        >
          <div class="ticket-top">
            <span class="ticket-number">{{ ticket.ticket_number }}</span>
            <Tag :value="statusLabel(ticket.status)" :severity="statusSeverity(ticket.status)" />
          </div>
          <h3 class="ticket-subject">{{ ticket.subject }}</h3>
          <div class="ticket-meta">
            <span><i class="pi pi-tag" /> {{ priorityLabel(ticket.priority) }}</span>
            <span v-if="ticket.replies_count"><i class="pi pi-comments" /> {{ ticket.replies_count }}</span>
            <span><i class="pi pi-clock" /> {{ formatDate(ticket.created_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="pagination">
        <Button
          icon="pi pi-chevron-left"
          severity="secondary"
          text
          rounded
          size="small"
          :disabled="currentPage <= 1"
          @click="loadTickets(currentPage - 1)"
        />
        <span class="page-info">{{ currentPage }} / {{ lastPage }}</span>
        <Button
          icon="pi pi-chevron-right"
          severity="secondary"
          text
          rounded
          size="small"
          :disabled="currentPage >= lastPage"
          @click="loadTickets(currentPage + 1)"
        />
      </div>
    </div>

    <!-- ═══ Ticket Detail ═══ -->
    <div v-if="view === 'detail' && activeTicket" class="detail-view">
      <div class="detail-header-card">
        <div class="detail-top">
          <span class="ticket-number">{{ activeTicket.ticket_number }}</span>
          <Tag :value="statusLabel(activeTicket.status)" :severity="statusSeverity(activeTicket.status)" />
        </div>
        <h2 class="detail-subject">{{ activeTicket.subject }}</h2>
        <div class="detail-meta">
          <span><i class="pi pi-tag" /> {{ priorityLabel(activeTicket.priority) }}</span>
          <span v-if="activeTicket.category"><i class="pi pi-folder" /> {{ activeTicket.category }}</span>
          <span><i class="pi pi-clock" /> {{ formatDate(activeTicket.created_at) }}</span>
        </div>
      </div>

      <!-- Conversation thread -->
      <div class="thread">
        <!-- Original message -->
        <div class="thread-msg user-msg">
          <div class="msg-avatar">
            <img v-if="auth.user.avatar" :src="auth.user.avatar" alt="" />
            <i v-else class="pi pi-user" />
          </div>
          <div class="msg-body">
            <div class="msg-header">
              <strong>{{ auth.user.name }}</strong>
              <span class="msg-time">{{ formatDate(activeTicket.created_at) }}</span>
            </div>
            <p class="msg-text">{{ activeTicket.message }}</p>
          </div>
        </div>

        <!-- Replies -->
        <div
          v-for="reply in activeTicket.replies"
          :key="reply.id"
          class="thread-msg"
          :class="reply.is_staff_reply ? 'staff-msg' : 'user-msg'"
        >
          <div class="msg-avatar">
            <img v-if="reply.user.avatar" :src="reply.user.avatar" alt="" />
            <i v-else :class="reply.is_staff_reply ? 'pi pi-shield' : 'pi pi-user'" />
          </div>
          <div class="msg-body">
            <div class="msg-header">
              <strong>{{ reply.user.name }}</strong>
              <Tag
                v-if="reply.is_staff_reply"
                value="Staff"
                severity="info"
                class="staff-badge"
              />
              <span class="msg-time">{{ formatDate(reply.created_at) }}</span>
            </div>
            <p class="msg-text">{{ reply.message }}</p>
          </div>
        </div>
      </div>

      <!-- Reply box -->
      <div v-if="activeTicket.status !== 'closed'" class="reply-box">
        <Textarea
          v-model="replyText"
          :placeholder="t('clientSupport.replyPlaceholder')"
          rows="3"
          class="w-full"
          maxlength="5000"
        />
        <Button
          :label="t('clientSupport.sendReply')"
          icon="pi pi-send"
          size="small"
          :loading="sending"
          :disabled="!replyText.trim()"
          @click="sendReply"
        />
      </div>
      <div v-else class="closed-notice">
        <i class="pi pi-lock" />
        <span>{{ t('clientSupport.ticketClosed') }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.support-page {
  --support-staff-bg: rgba(59, 130, 246, 0.08);
  --support-staff-border: rgba(59, 130, 246, 0.18);
  --support-staff-text: var(--text-color);
  --support-staff-meta: var(--text-color-secondary);
  --support-staff-badge-bg: rgba(59, 130, 246, 0.12);
  --support-staff-badge-text: #1d4ed8;

  max-width: 800px;
  margin: 0 auto;
  padding: 24px 16px 60px;
}

:global(.dark) .support-page {
  --support-staff-bg: rgba(30, 64, 175, 0.34);
  --support-staff-border: rgba(147, 197, 253, 0.24);
  --support-staff-text: #eff6ff;
  --support-staff-meta: #bfdbfe;
  --support-staff-badge-bg: rgba(147, 197, 253, 0.16);
  --support-staff-badge-text: #e0f2fe;
}

/* ── Header ────────────────────────────── */
.support-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 24px;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 8px;
}
.page-title {
  font-size: 1.3rem;
  font-weight: 700;
  margin: 0;
  color: var(--text-color);
}
.page-sub {
  font-size: 0.8rem;
  color: var(--text-color-secondary);
  margin: 2px 0 0;
}

/* ── Filter Bar ───────────────────────── */
.filter-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}
.status-filter {
  min-width: 160px;
}
.ticket-count {
  font-size: 0.78rem;
  color: var(--text-color-secondary);
}

/* ── Loading / Empty ──────────────────── */
.loading-state {
  text-align: center;
  padding: 60px 0;
  font-size: 1.5rem;
  color: var(--text-color-secondary);
}
.empty-state {
  text-align: center;
  padding: 60px 0;
  color: var(--text-color-secondary);
}
.empty-icon {
  font-size: 2.5rem;
  margin-bottom: 12px;
  opacity: 0.4;
}
.empty-state p {
  margin: 0 0 16px;
  font-size: 0.9rem;
}

/* ── Ticket Cards (List) ──────────────── */
.tickets-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ticket-card {
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
  border-radius: 12px;
  padding: 16px 18px;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.ticket-card:hover {
  border-color: var(--primary-color);
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.ticket-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}
.ticket-number {
  font-family: monospace;
  font-size: 0.72rem;
  color: var(--text-color-secondary);
}
.ticket-subject {
  font-size: 0.95rem;
  font-weight: 600;
  margin: 0 0 8px;
  color: var(--text-color);
}
.ticket-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  font-size: 0.72rem;
  color: var(--text-color-secondary);
}
.ticket-meta i {
  margin-right: 3px;
}

/* ── Pagination ───────────────────────── */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 20px;
}
.page-info {
  font-size: 0.8rem;
  color: var(--text-color-secondary);
}

/* ── New Ticket Form ──────────────────── */
.new-ticket-card {
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
  border-radius: 14px;
  padding: 24px;
}
.card-title {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0 0 20px;
  color: var(--text-color);
}
.form-field {
  margin-bottom: 16px;
}
.form-field label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-color);
  margin-bottom: 6px;
}
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 8px;
}

/* ── Detail View ──────────────────────── */
.detail-header-card {
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
  border-radius: 14px;
  padding: 20px 22px;
  margin-bottom: 20px;
}
.detail-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}
.detail-subject {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0 0 10px;
  color: var(--text-color);
}
.detail-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  font-size: 0.75rem;
  color: var(--text-color-secondary);
}
.detail-meta i {
  margin-right: 4px;
}

/* ── Thread ───────────────────────────── */
.thread {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-bottom: 20px;
}
.thread-msg {
  display: flex;
  gap: 12px;
}
.msg-avatar {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  overflow: hidden;
}
.user-msg .msg-avatar {
  background: var(--primary-color);
  color: #fff;
}
.staff-msg .msg-avatar {
  background: var(--p-blue-500, #3b82f6);
  color: #fff;
}
.msg-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.msg-body {
  flex: 1;
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
  border-radius: 12px;
  padding: 12px 16px;
}
.staff-msg .msg-body {
  background: var(--support-staff-bg);
  border-color: var(--support-staff-border);
}
.msg-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  font-size: 0.8rem;
}
.msg-header strong {
  color: var(--text-color);
}
.staff-msg .msg-header strong {
  color: var(--support-staff-text);
}
.msg-time {
  color: var(--text-color-secondary);
  font-size: 0.7rem;
  margin-left: auto;
}
.staff-msg .msg-time {
  color: var(--support-staff-meta);
}
.staff-badge {
  font-size: 0.6rem;
}
.staff-msg :deep(.staff-badge.p-tag) {
  background: var(--support-staff-badge-bg) !important;
  color: var(--support-staff-badge-text) !important;
  border: 1px solid var(--support-staff-border) !important;
}
.msg-text {
  font-size: 0.88rem;
  line-height: 1.6;
  color: var(--text-color);
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
}
.staff-msg .msg-text {
  color: var(--support-staff-text);
}

/* ── Reply Box ────────────────────────── */
.reply-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: flex-end;
}

.closed-notice {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px;
  border-radius: 10px;
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
  font-size: 0.82rem;
  color: var(--text-color-secondary);
}

/* ── Responsive ───────────────────────── */
@media (max-width: 640px) {
  .support-page { padding: 16px 12px 40px; }
  .form-row { grid-template-columns: 1fr; }
  .support-header { flex-direction: column; }
}
</style>
