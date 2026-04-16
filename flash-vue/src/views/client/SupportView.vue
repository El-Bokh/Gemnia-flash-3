<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useSeo } from '@/composables/useSeo'
import type { SupportAttachment } from '@/types/supportShared'
import { resolveMediaUrl } from '@/utils/mediaUrl'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'
import Select from 'primevue/select'
import {
  getMyTickets,
  getMyTicket,
  createTicket,
  replyToMyTicket,
  reopenMyTicket,
  resolveMyTicket,
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

const acceptedAttachmentTypes = '.png,.jpg,.jpeg,.webp,.gif,.pdf,.txt,.doc,.docx,.xls,.xlsx,.csv'

const view = ref<'list' | 'detail' | 'new'>('list')
const loading = ref(false)
const sending = ref(false)
const statusActionLoading = ref(false)
const tickets = ref<ClientTicket[]>([])
const totalTickets = ref(0)
const currentPage = ref(1)
const lastPage = ref(1)
const filterStatus = ref('')
const activeTicket = ref<ClientTicketDetail | null>(null)
const replyText = ref('')
const newAttachments = ref<File[]>([])
const replyAttachments = ref<File[]>([])
const newAttachmentInput = ref<HTMLInputElement | null>(null)
const replyAttachmentInput = ref<HTMLInputElement | null>(null)
const previewAttachment = ref<SupportAttachment | null>(null)
const previewDialogVisible = ref(false)

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

const quickStartCards = computed(() => [
  {
    category: 'technical',
    priority: 'high',
    icon: 'pi pi-cog',
    title: t('clientSupport.catTechnical'),
    desc: t('clientSupport.quickTechnicalDesc'),
  },
  {
    category: 'billing',
    priority: 'medium',
    icon: 'pi pi-credit-card',
    title: t('clientSupport.catBilling'),
    desc: t('clientSupport.quickBillingDesc'),
  },
  {
    category: 'account',
    priority: 'medium',
    icon: 'pi pi-user',
    title: t('clientSupport.catAccount'),
    desc: t('clientSupport.quickAccountDesc'),
  },
  {
    category: 'general',
    priority: 'low',
    icon: 'pi pi-info-circle',
    title: t('clientSupport.catGeneral'),
    desc: t('clientSupport.quickGeneralDesc'),
  },
])

const canResolveActiveTicket = computed(() => !!activeTicket.value && !['resolved', 'closed'].includes(activeTicket.value.status))
const canReopenActiveTicket = computed(() => !!activeTicket.value && ['resolved', 'closed'].includes(activeTicket.value.status))

function normalizeAttachments(attachments: SupportAttachment[] | null | undefined) {
  return Array.isArray(attachments) ? attachments : []
}

function normalizeTicketDetail(ticket: ClientTicketDetail): ClientTicketDetail {
  return {
    ...ticket,
    attachments: normalizeAttachments(ticket.attachments),
    replies: Array.isArray(ticket.replies)
      ? ticket.replies.map(reply => ({
          ...reply,
          attachments: normalizeAttachments(reply.attachments),
        }))
      : [],
  }
}

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

function priorityLabel(priority: string) {
  const map: Record<string, string> = {
    low: t('clientSupport.low'),
    medium: t('clientSupport.medium'),
    high: t('clientSupport.high'),
    urgent: t('clientSupport.urgent'),
  }
  return map[priority] ?? priority
}

function categoryLabel(category: string | null | undefined) {
  const map: Record<string, string> = {
    general: t('clientSupport.catGeneral'),
    billing: t('clientSupport.catBilling'),
    technical: t('clientSupport.catTechnical'),
    account: t('clientSupport.catAccount'),
  }
  return category ? (map[category] ?? category) : t('clientSupport.catGeneral')
}

function formatDate(iso: string | null | undefined) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function formatAttachmentSize(size: number | null) {
  if (!size) return null
  if (size >= 1024 * 1024) return `${(size / (1024 * 1024)).toFixed(1)} MB`
  if (size >= 1024) return `${Math.round(size / 1024)} KB`
  return `${size} B`
}

function attachmentIcon(attachment: SupportAttachment) {
  return attachment.is_image ? 'pi pi-image' : 'pi pi-paperclip'
}

function avatarUrl(value: string | null | undefined) {
  return resolveMediaUrl(value) || undefined
}

function attachmentUrl(attachment: SupportAttachment) {
  return resolveMediaUrl(attachment.url)
}

const previewAttachmentUrl = computed(() => (previewAttachment.value ? attachmentUrl(previewAttachment.value) : null))

function fileKey(file: File) {
  return `${file.name}-${file.size}-${file.lastModified}`
}

function attachmentKey(attachment: SupportAttachment) {
  return `${attachment.name}-${attachment.url ?? 'none'}`
}

function canPreviewAttachment(attachment: SupportAttachment) {
  return attachment.is_image && !!attachmentUrl(attachment)
}

function openAttachmentPreview(attachment: SupportAttachment) {
  if (!canPreviewAttachment(attachment)) return
  previewAttachment.value = attachment
  previewDialogVisible.value = true
}

function closeAttachmentPreview() {
  previewDialogVisible.value = false
  previewAttachment.value = null
}

function downloadAttachment(attachment: SupportAttachment) {
  const url = attachmentUrl(attachment)
  if (!url) return

  const link = document.createElement('a')
  link.href = url
  link.download = attachment.name || 'attachment'
  link.target = '_blank'
  link.rel = 'noopener noreferrer'
  document.body.appendChild(link)
  link.click()
  link.remove()
}

function openAttachmentInNewTab(attachment: SupportAttachment) {
  const url = attachmentUrl(attachment)
  if (!url) return

  window.open(url, '_blank', 'noopener,noreferrer')
}

function handleAttachmentPrimaryAction(attachment: SupportAttachment) {
  if (!attachmentUrl(attachment)) return

  if (attachment.is_image) {
    openAttachmentPreview(attachment)
    return
  }

  downloadAttachment(attachment)
}

function mergeFiles(existing: File[], files: FileList | null) {
  if (!files) return existing

  const next = [...existing]
  Array.from(files).forEach(file => {
    const key = fileKey(file)
    if (!next.some(item => fileKey(item) === key)) {
      next.push(file)
    }
  })

  return next.slice(0, 5)
}

function selectNewAttachments() {
  newAttachmentInput.value?.click()
}

function selectReplyAttachments() {
  replyAttachmentInput.value?.click()
}

function onNewAttachmentsChange(event: Event) {
  const input = event.target as HTMLInputElement
  newAttachments.value = mergeFiles(newAttachments.value, input.files)
  input.value = ''
}

function onReplyAttachmentsChange(event: Event) {
  const input = event.target as HTMLInputElement
  replyAttachments.value = mergeFiles(replyAttachments.value, input.files)
  input.value = ''
}

function removeNewAttachment(file: File) {
  newAttachments.value = newAttachments.value.filter(item => fileKey(item) !== fileKey(file))
}

function removeReplyAttachment(file: File) {
  replyAttachments.value = replyAttachments.value.filter(item => fileKey(item) !== fileKey(file))
}

function resetNewTicketForm(category = '', priority = 'medium') {
  newSubject.value = ''
  newMessage.value = ''
  newCategory.value = category
  newPriority.value = priority
  newAttachments.value = []
}

function startNewTicket(category = '', priority = 'medium') {
  resetNewTicketForm(category, priority)
  view.value = 'new'
}

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
  } catch {
    // handled by interceptor
  } finally {
    loading.value = false
  }
}

async function openTicket(id: number) {
  loading.value = true
  try {
    const res = await getMyTicket(id)
    activeTicket.value = normalizeTicketDetail(res.data)
    view.value = 'detail'

    if (route.query.ticket !== String(id)) {
      void router.replace({
        name: 'support',
        query: { ...route.query, ticket: String(id) },
      })
    }
  } catch {
    // handled by interceptor
  } finally {
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
      attachments: newAttachments.value,
    })
    resetNewTicketForm()
    view.value = 'list'
    await loadTickets()
  } catch {
    // handled by interceptor
  } finally {
    sending.value = false
  }
}

async function sendReply() {
  if (!replyText.value.trim() || !activeTicket.value) return

  sending.value = true
  try {
    await replyToMyTicket(activeTicket.value.id, {
      message: replyText.value.trim(),
      attachments: replyAttachments.value,
    })
    replyText.value = ''
    replyAttachments.value = []
    await openTicket(activeTicket.value.id)
    await loadTickets(currentPage.value)
  } catch {
    // handled by interceptor
  } finally {
    sending.value = false
  }
}

async function markResolved() {
  if (!activeTicket.value) return

  statusActionLoading.value = true
  try {
    await resolveMyTicket(activeTicket.value.id)
    await openTicket(activeTicket.value.id)
    await loadTickets(currentPage.value)
  } catch {
    // handled by interceptor
  } finally {
    statusActionLoading.value = false
  }
}

async function reopenCurrentTicket() {
  if (!activeTicket.value) return

  statusActionLoading.value = true
  try {
    await reopenMyTicket(activeTicket.value.id)
    await openTicket(activeTicket.value.id)
    await loadTickets(currentPage.value)
  } catch {
    // handled by interceptor
  } finally {
    statusActionLoading.value = false
  }
}

function goBack() {
  view.value = 'list'
  activeTicket.value = null
  replyText.value = ''
  replyAttachments.value = []
  closeAttachmentPreview()
  const nextQuery = { ...route.query }
  delete nextQuery.ticket
  void router.replace({ name: 'support', query: nextQuery })
  void loadTickets(currentPage.value)
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
    replyAttachments.value = []
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
        @click="startNewTicket()"
      />
    </div>

    <template v-if="view === 'list'">
      <section class="support-shortcuts-card">
        <div class="shortcut-head">
          <h2>{{ t('clientSupport.quickStart') }}</h2>
        </div>

        <div class="shortcut-grid">
          <button
            v-for="item in quickStartCards"
            :key="item.category"
            type="button"
            class="shortcut-item"
            @click="startNewTicket(item.category, item.priority)"
          >
            <div class="shortcut-icon">
              <i :class="item.icon" />
            </div>
            <div class="shortcut-copy">
              <strong>{{ item.title }}</strong>
              <span>{{ item.desc }}</span>
            </div>
            <i class="pi pi-arrow-right shortcut-arrow" />
          </button>
        </div>
      </section>
    </template>

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

      <div class="form-field">
        <label>{{ t('clientSupport.attachments') }}</label>
        <input
          ref="newAttachmentInput"
          type="file"
          class="hidden-file-input"
          :accept="acceptedAttachmentTypes"
          multiple
          @change="onNewAttachmentsChange"
        >
        <div class="attachment-upload-card">
          <Button
            :label="t('clientSupport.addAttachments')"
            icon="pi pi-paperclip"
            severity="secondary"
            text
            size="small"
            @click="selectNewAttachments"
          />
          <span class="attachment-hint">{{ t('clientSupport.attachmentsHint') }}</span>
        </div>
        <div v-if="newAttachments.length" class="pending-attachments">
          <div v-for="file in newAttachments" :key="fileKey(file)" class="pending-attachment-item">
            <div class="pending-attachment-copy">
              <strong>{{ file.name }}</strong>
              <span>{{ formatAttachmentSize(file.size) }}</span>
            </div>
            <Button
              icon="pi pi-times"
              severity="secondary"
              text
              rounded
              size="small"
              :aria-label="t('clientSupport.removeAttachment')"
              @click="removeNewAttachment(file)"
            />
          </div>
        </div>
      </div>

      <div class="form-actions">
        <Button
          :label="t('clientSupport.cancel')"
          severity="secondary"
          text
          size="small"
          @click="goBack"
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
        <span class="ticket-count">{{ totalTickets }} {{ t('clientSupport.tickets') }}</span>
        <Select
          v-model="filterStatus"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          class="status-filter"
          @change="loadTickets(1)"
        />
      </div>

      <div v-if="loading" class="loading-state">
        <i class="pi pi-spin pi-spinner" />
      </div>

      <div v-else-if="tickets.length === 0" class="empty-state-card">
        <div class="empty-state-visual">
          <i class="pi pi-ticket empty-icon" />
        </div>
        <h3 class="empty-title">{{ t('clientSupport.emptyTitle') }}</h3>
        <p class="empty-sub">{{ t('clientSupport.emptySub') }}</p>
        <div class="empty-actions">
          <Button
            :label="t('clientSupport.createFirst')"
            icon="pi pi-plus"
            size="small"
            @click="startNewTicket()"
          />
        </div>
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
            <span v-if="ticket.category"><i class="pi pi-folder" /> {{ categoryLabel(ticket.category) }}</span>
            <span><i class="pi pi-tag" /> {{ priorityLabel(ticket.priority) }}</span>
            <span v-if="ticket.replies_count"><i class="pi pi-comments" /> {{ ticket.replies_count }}</span>
            <span><i class="pi pi-clock" /> {{ formatDate(ticket.last_reply_at || ticket.updated_at || ticket.created_at) }}</span>
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
          <div class="detail-heading-stack">
            <span class="ticket-number">{{ activeTicket.ticket_number }}</span>
            <Tag :value="statusLabel(activeTicket.status)" :severity="statusSeverity(activeTicket.status)" />
          </div>
          <div class="detail-actions">
            <Button
              v-if="canResolveActiveTicket"
              :label="t('clientSupport.markResolved')"
              icon="pi pi-check-circle"
              size="small"
              severity="success"
              outlined
              :loading="statusActionLoading"
              @click="markResolved"
            />
            <Button
              v-if="canReopenActiveTicket"
              :label="t('clientSupport.reopenTicket')"
              icon="pi pi-refresh"
              size="small"
              severity="secondary"
              outlined
              :loading="statusActionLoading"
              @click="reopenCurrentTicket"
            />
          </div>
        </div>
        <h2 class="detail-subject">{{ activeTicket.subject }}</h2>
        <div class="detail-meta">
          <span><i class="pi pi-tag" /> {{ priorityLabel(activeTicket.priority) }}</span>
          <span v-if="activeTicket.category"><i class="pi pi-folder" /> {{ categoryLabel(activeTicket.category) }}</span>
          <span><i class="pi pi-clock" /> {{ formatDate(activeTicket.created_at) }}</span>
        </div>
      </div>

      <!-- Conversation thread -->
      <div class="thread">
        <!-- Original message -->
        <div class="thread-msg user-msg">
          <div class="msg-avatar">
            <img v-if="avatarUrl(auth.user.avatar)" :src="avatarUrl(auth.user.avatar)" alt="" />
            <i v-else class="pi pi-user" />
          </div>
          <div class="msg-body">
            <div class="msg-header">
              <strong>{{ auth.user.name }}</strong>
              <span class="msg-time">{{ formatDate(activeTicket.created_at) }}</span>
            </div>
            <p class="msg-text">{{ activeTicket.message }}</p>
            <div v-if="activeTicket.attachments.length" class="message-attachments">
              <div
                v-for="attachment in activeTicket.attachments"
                :key="attachmentKey(attachment)"
                class="support-attachment-card"
                :class="{ disabled: !attachmentUrl(attachment), 'has-preview': canPreviewAttachment(attachment) }"
              >
                <button
                  type="button"
                  class="support-attachment-main"
                  :disabled="!attachmentUrl(attachment)"
                  @click="handleAttachmentPrimaryAction(attachment)"
                >
                  <img
                    v-if="attachment.is_image && attachmentUrl(attachment)"
                    :src="attachmentUrl(attachment) || undefined"
                    :alt="attachment.name"
                    class="support-attachment-thumb"
                    loading="lazy"
                  >
                  <span v-else class="support-attachment-icon-shell"><i :class="attachmentIcon(attachment)" /></span>
                  <span class="support-attachment-copy">
                    <strong>{{ attachment.name }}</strong>
                    <small v-if="formatAttachmentSize(attachment.size)">{{ formatAttachmentSize(attachment.size) }}</small>
                  </span>
                </button>
                <div class="support-attachment-actions">
                  <Button
                    v-if="canPreviewAttachment(attachment)"
                    icon="pi pi-search-plus"
                    severity="secondary"
                    text
                    rounded
                    size="small"
                    :aria-label="t('attachmentViewer.zoomImage')"
                    @click="openAttachmentPreview(attachment)"
                  />
                  <Button
                    icon="pi pi-download"
                    severity="secondary"
                    text
                    rounded
                    size="small"
                    :disabled="!attachmentUrl(attachment)"
                    :aria-label="t('attachmentViewer.download')"
                    @click="downloadAttachment(attachment)"
                  />
                </div>
              </div>
            </div>
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
            <img v-if="avatarUrl(reply.user.avatar)" :src="avatarUrl(reply.user.avatar)" alt="" />
            <i v-else :class="reply.is_staff_reply ? 'pi pi-shield' : 'pi pi-user'" />
          </div>
          <div class="msg-body">
            <div class="msg-header">
              <strong>{{ reply.user.name }}</strong>
              <Tag
                v-if="reply.is_staff_reply"
                :value="t('clientSupport.staff')"
                severity="info"
                class="staff-badge"
              />
              <span class="msg-time">{{ formatDate(reply.created_at) }}</span>
            </div>
            <p class="msg-text">{{ reply.message }}</p>
            <div v-if="reply.attachments.length" class="message-attachments">
              <div
                v-for="attachment in reply.attachments"
                :key="attachmentKey(attachment)"
                class="support-attachment-card"
                :class="{ disabled: !attachmentUrl(attachment), 'has-preview': canPreviewAttachment(attachment) }"
              >
                <button
                  type="button"
                  class="support-attachment-main"
                  :disabled="!attachmentUrl(attachment)"
                  @click="handleAttachmentPrimaryAction(attachment)"
                >
                  <img
                    v-if="attachment.is_image && attachmentUrl(attachment)"
                    :src="attachmentUrl(attachment) || undefined"
                    :alt="attachment.name"
                    class="support-attachment-thumb"
                    loading="lazy"
                  >
                  <span v-else class="support-attachment-icon-shell"><i :class="attachmentIcon(attachment)" /></span>
                  <span class="support-attachment-copy">
                    <strong>{{ attachment.name }}</strong>
                    <small v-if="formatAttachmentSize(attachment.size)">{{ formatAttachmentSize(attachment.size) }}</small>
                  </span>
                </button>
                <div class="support-attachment-actions">
                  <Button
                    v-if="canPreviewAttachment(attachment)"
                    icon="pi pi-search-plus"
                    severity="secondary"
                    text
                    rounded
                    size="small"
                    :aria-label="t('attachmentViewer.zoomImage')"
                    @click="openAttachmentPreview(attachment)"
                  />
                  <Button
                    icon="pi pi-download"
                    severity="secondary"
                    text
                    rounded
                    size="small"
                    :disabled="!attachmentUrl(attachment)"
                    :aria-label="t('attachmentViewer.download')"
                    @click="downloadAttachment(attachment)"
                  />
                </div>
              </div>
            </div>
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
        <input
          ref="replyAttachmentInput"
          type="file"
          class="hidden-file-input"
          :accept="acceptedAttachmentTypes"
          multiple
          @change="onReplyAttachmentsChange"
        >
        <div class="reply-toolbar">
          <Button
            :label="t('clientSupport.addAttachments')"
            icon="pi pi-paperclip"
            severity="secondary"
            text
            size="small"
            @click="selectReplyAttachments"
          />
          <span class="attachment-hint">{{ t('clientSupport.attachmentsHint') }}</span>
        </div>
        <div v-if="replyAttachments.length" class="pending-attachments compact">
          <div v-for="file in replyAttachments" :key="fileKey(file)" class="pending-attachment-item">
            <div class="pending-attachment-copy">
              <strong>{{ file.name }}</strong>
              <span>{{ formatAttachmentSize(file.size) }}</span>
            </div>
            <Button
              icon="pi pi-times"
              severity="secondary"
              text
              rounded
              size="small"
              :aria-label="t('clientSupport.removeAttachment')"
              @click="removeReplyAttachment(file)"
            />
          </div>
        </div>
        <div class="reply-actions">
          <Button
            :label="t('clientSupport.sendReply')"
            icon="pi pi-send"
            size="small"
            :loading="sending"
            :disabled="!replyText.trim()"
            @click="sendReply"
          />
        </div>
      </div>
      <div v-else class="closed-notice">
        <i class="pi pi-lock" />
        <span>{{ t('clientSupport.ticketClosed') }}</span>
      </div>
    </div>

    <Dialog
      v-model:visible="previewDialogVisible"
      modal
      dismissableMask
      class="attachment-preview-dialog"
      :header="previewAttachment?.name || t('clientSupport.attachments')"
      :style="{ width: 'min(92vw, 1080px)' }"
      @hide="closeAttachmentPreview"
    >
      <div v-if="previewAttachmentUrl" class="attachment-preview-shell">
        <img
          :src="previewAttachmentUrl"
          :alt="previewAttachment?.name || ''"
          class="attachment-preview-image"
        >
      </div>
      <template #footer>
        <div class="attachment-preview-footer">
          <span class="attachment-preview-meta">
            {{ previewAttachment?.name }}
            <template v-if="previewAttachment && formatAttachmentSize(previewAttachment.size)">
              · {{ formatAttachmentSize(previewAttachment.size) }}
            </template>
          </span>
          <div class="attachment-preview-actions">
            <Button
              icon="pi pi-external-link"
              severity="secondary"
              text
              :label="t('attachmentViewer.openOriginal')"
              @click="previewAttachment && openAttachmentInNewTab(previewAttachment)"
            />
            <Button
              icon="pi pi-download"
              :label="t('attachmentViewer.download')"
              @click="previewAttachment && downloadAttachment(previewAttachment)"
            />
          </div>
        </div>
      </template>
    </Dialog>
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
  --support-grid-line: rgba(99, 102, 241, 0.08);
  --support-grid-glow: rgba(99, 102, 241, 0.12);
  --support-grid-glow-alt: rgba(16, 185, 129, 0.09);

  position: relative;
  isolation: isolate;
  width: 100%;
  max-width: none;
  margin: 0;
  padding: 30px 26px 60px;
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 30px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0)),
    rgba(8, 10, 18, 0.82);
  box-shadow: 0 28px 70px rgba(0, 0, 0, 0.24);
  overflow: hidden;
}

:global(.dark) .support-page {
  --support-staff-bg: rgba(30, 64, 175, 0.34);
  --support-staff-border: rgba(147, 197, 253, 0.24);
  --support-staff-text: #eff6ff;
  --support-staff-meta: #bfdbfe;
  --support-staff-badge-bg: rgba(147, 197, 253, 0.16);
  --support-staff-badge-text: #e0f2fe;
  --support-grid-line: rgba(147, 197, 253, 0.08);
  --support-grid-glow: rgba(129, 140, 248, 0.16);
  --support-grid-glow-alt: rgba(56, 189, 248, 0.12);
}

.support-page::before,
.support-page::after {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.support-page::before {
  background:
    linear-gradient(var(--support-grid-line) 1px, transparent 1px),
    linear-gradient(90deg, var(--support-grid-line) 1px, transparent 1px);
  background-size: 42px 42px;
  mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.82), rgba(0, 0, 0, 0.28));
  opacity: 0.95;
  z-index: -2;
}

.support-page::after {
  background:
    radial-gradient(circle at 14% 12%, var(--support-grid-glow), transparent 26%),
    radial-gradient(circle at 84% 20%, var(--support-grid-glow-alt), transparent 24%),
    radial-gradient(circle at 70% 78%, rgba(99, 102, 241, 0.08), transparent 22%);
  z-index: -1;
}

.support-page > * {
  position: relative;
  z-index: 1;
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

.support-shortcuts-card,
.filter-bar,
.empty-state-card {
  background: color-mix(in srgb, var(--surface-card) 92%, transparent);
  border: 1px solid color-mix(in srgb, var(--surface-border) 80%, rgba(255, 255, 255, 0.08));
  border-radius: 18px;
  backdrop-filter: blur(14px);
}

.support-shortcuts-card {
  padding: 20px;
  margin-bottom: 16px;
}

.shortcut-head h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-color);
}

.shortcut-grid {
  display: grid;
  gap: 10px;
  margin-top: 16px;
}

.shortcut-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 12px;
  border: 1px solid var(--surface-border);
  border-radius: 14px;
  background: transparent;
  text-align: start;
  cursor: pointer;
  transition: border-color 0.2s, transform 0.2s, background 0.2s;
}

.shortcut-item:hover {
  border-color: rgba(99, 102, 241, 0.35);
  background: rgba(99, 102, 241, 0.06);
  transform: translateY(-1px);
}

.shortcut-icon {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(99, 102, 241, 0.12);
  color: var(--primary-color);
  flex-shrink: 0;
}

.shortcut-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.shortcut-copy strong {
  font-size: 0.84rem;
  color: var(--text-color);
}

.shortcut-copy span {
  margin-top: 2px;
  font-size: 0.75rem;
  line-height: 1.5;
  color: var(--text-color-secondary);
}

.shortcut-arrow {
  margin-inline-start: auto;
  font-size: 0.74rem;
  color: var(--text-color-secondary);
}

/* ── Filter Bar ───────────────────────── */
.filter-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 16px;
}
.status-filter {
  min-width: 160px;
}
.ticket-count {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--text-color);
}

/* ── Loading / Empty ──────────────────── */
.loading-state {
  text-align: center;
  padding: 60px 0;
  font-size: 1.5rem;
  color: var(--text-color-secondary);
}
.empty-state-card {
  text-align: center;
  padding: 36px 20px;
  color: var(--text-color-secondary);
}
.empty-state-visual {
  display: flex;
  justify-content: center;
  margin-bottom: 10px;
}
.empty-icon {
  font-size: 2.5rem;
  opacity: 0.4;
}
.empty-title {
  margin: 0;
  font-size: 1.05rem;
  color: var(--text-color);
}
.empty-sub {
  max-width: 520px;
  margin: 10px auto 0;
  font-size: 0.88rem;
  line-height: 1.7;
}
.empty-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
  margin-top: 20px;
}
.empty-shortcut {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid var(--surface-border);
  background: transparent;
  color: var(--text-color);
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}
.empty-shortcut:hover {
  border-color: rgba(99, 102, 241, 0.35);
  background: rgba(99, 102, 241, 0.06);
}

.support-attachment-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border: 1px solid color-mix(in srgb, var(--surface-border) 82%, rgba(255, 255, 255, 0.08));
  border-radius: 16px;
  background: color-mix(in srgb, var(--surface-card) 90%, transparent);
}

.support-attachment-card.disabled {
  opacity: 0.7;
}

.support-attachment-main {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: start;
  cursor: pointer;
}

.support-attachment-main:disabled {
  cursor: default;
}

.support-attachment-thumb,
.support-attachment-icon-shell {
  width: 58px;
  height: 58px;
  border-radius: 14px;
  flex-shrink: 0;
}

.support-attachment-thumb {
  object-fit: cover;
  background: rgba(255, 255, 255, 0.04);
}

.support-attachment-icon-shell {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(99, 102, 241, 0.12);
  color: var(--primary-color);
}

.support-attachment-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.support-attachment-copy strong {
  font-size: 0.82rem;
  color: var(--text-color);
  word-break: break-word;
}

.support-attachment-copy small {
  margin-top: 4px;
  color: var(--text-color-secondary);
}

.support-attachment-actions {
  display: flex;
  align-items: center;
  gap: 2px;
  flex-shrink: 0;
}

.attachment-preview-shell {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: min(70vh, 720px);
  padding: 12px;
  border-radius: 20px;
  background: rgba(6, 8, 14, 0.9);
}

.attachment-preview-image {
  max-width: 100%;
  max-height: min(68vh, 680px);
  border-radius: 16px;
  object-fit: contain;
}

.attachment-preview-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
}

.attachment-preview-meta {
  min-width: 0;
  color: var(--text-color-secondary);
  font-size: 0.82rem;
  word-break: break-word;
}

.attachment-preview-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

:deep(.attachment-preview-dialog .p-dialog-header),
:deep(.attachment-preview-dialog .p-dialog-content),
:deep(.attachment-preview-dialog .p-dialog-footer) {
  background: rgba(8, 10, 18, 0.98);
  color: var(--text-color);
  border-color: rgba(255, 255, 255, 0.06);
}

@media (max-width: 720px) {
  .support-attachment-card,
  .attachment-preview-footer {
    flex-direction: column;
    align-items: stretch;
  }

  .support-attachment-actions,
  .attachment-preview-actions {
    justify-content: flex-end;
  }
}

/* ── Ticket Cards (List) ──────────────── */
.tickets-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ticket-card {
  background: color-mix(in srgb, var(--surface-card) 94%, transparent);
  border: 1px solid color-mix(in srgb, var(--surface-border) 84%, rgba(255, 255, 255, 0.06));
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
  background: color-mix(in srgb, var(--surface-card) 94%, transparent);
  border: 1px solid color-mix(in srgb, var(--surface-border) 84%, rgba(255, 255, 255, 0.06));
  border-radius: 18px;
  padding: 24px;
  backdrop-filter: blur(14px);
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

.hidden-file-input {
  display: none;
}

.attachment-upload-card,
.reply-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border: 1px dashed color-mix(in srgb, var(--surface-border) 85%, rgba(255, 255, 255, 0.08));
  border-radius: 14px;
  background: color-mix(in srgb, var(--surface-card) 88%, transparent);
}

.attachment-hint {
  font-size: 0.74rem;
  color: var(--text-color-secondary);
}

.pending-attachments {
  display: grid;
  gap: 8px;
  margin-top: 10px;
}

.pending-attachments.compact {
  margin-top: 0;
}

.pending-attachment-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--surface-card) 92%, transparent);
  border: 1px solid color-mix(in srgb, var(--surface-border) 84%, rgba(255, 255, 255, 0.06));
}

.pending-attachment-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.pending-attachment-copy strong {
  font-size: 0.8rem;
  color: var(--text-color);
  word-break: break-word;
}

.pending-attachment-copy span {
  margin-top: 2px;
  font-size: 0.72rem;
  color: var(--text-color-secondary);
}

/* ── Detail View ──────────────────────── */
.detail-header-card {
  background: color-mix(in srgb, var(--surface-card) 94%, transparent);
  border: 1px solid color-mix(in srgb, var(--surface-border) 84%, rgba(255, 255, 255, 0.06));
  border-radius: 14px;
  padding: 20px 22px;
  margin-bottom: 20px;
  backdrop-filter: blur(14px);
}
.detail-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 6px;
}

.detail-heading-stack,
.detail-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
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
  background: color-mix(in srgb, var(--surface-card) 94%, transparent);
  border: 1px solid color-mix(in srgb, var(--surface-border) 84%, rgba(255, 255, 255, 0.06));
  border-radius: 12px;
  padding: 12px 16px;
  backdrop-filter: blur(12px);
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

.message-attachments {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.attachment-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 12px;
  border: 1px solid color-mix(in srgb, var(--surface-border) 84%, rgba(255, 255, 255, 0.08));
  background: color-mix(in srgb, var(--surface-card) 92%, transparent);
  color: var(--text-color);
  text-decoration: none;
  font-size: 0.75rem;
}

.attachment-link.has-preview {
  align-items: stretch;
  min-width: min(100%, 240px);
}

.attachment-thumb,
.attachment-icon-shell {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  flex-shrink: 0;
}

.attachment-thumb {
  object-fit: cover;
  background: rgba(255, 255, 255, 0.04);
}

.attachment-icon-shell {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: color-mix(in srgb, var(--surface-card) 84%, rgba(99, 102, 241, 0.12));
}

.attachment-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.attachment-copy strong {
  font-size: 0.72rem;
  line-height: 1.35;
  color: var(--text-color);
  word-break: break-word;
}

.attachment-link small {
  color: var(--text-color-secondary);
}

.attachment-link.disabled {
  opacity: 0.68;
  cursor: default;
}

/* ── Reply Box ────────────────────────── */
.reply-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.reply-actions {
  display: flex;
  justify-content: flex-end;
}

.closed-notice {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--surface-card) 94%, transparent);
  border: 1px solid color-mix(in srgb, var(--surface-border) 84%, rgba(255, 255, 255, 0.06));
  font-size: 0.82rem;
  color: var(--text-color-secondary);
}

/* ── Responsive ───────────────────────── */
@media (max-width: 640px) {
  .support-page {
    padding: 18px 12px 40px;
    border-radius: 22px;
    background-size: auto, 32px 32px;
  }
  .form-row,
  .shortcut-grid { grid-template-columns: 1fr; }
  .support-header,
  .filter-bar { flex-direction: column; }
  .status-filter { width: 100%; }
}

@media (min-width: 1180px) {
  .shortcut-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
