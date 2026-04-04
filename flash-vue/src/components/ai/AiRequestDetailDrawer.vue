<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  cancelAiRequest,
  deleteAiRequest,
  getAiRequest,
  notifyAiRequestUser,
  restoreAiRequest,
  retryAiRequest,
  updateAiRequest,
} from '@/services/aiRequestService'
import type { AiRequestDetail, AiRequestStatus, UpdateAiRequestPayload } from '@/types/aiRequests'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import InputText from 'primevue/inputtext'

const props = defineProps<{
  visible: boolean
  requestId: number | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void
  (e: 'updated'): void
}>()

const loading = ref(false)
const saving = ref(false)
const actionLoading = ref(false)
const notifying = ref<'completed' | 'failed' | null>(null)

const detail = ref<AiRequestDetail | null>(null)

const statusOptions = [
  { label: 'Pending', value: 'pending' },
  { label: 'Processing', value: 'processing' },
  { label: 'Completed', value: 'completed' },
  { label: 'Failed', value: 'failed' },
  { label: 'Cancelled', value: 'cancelled' },
  { label: 'Timeout', value: 'timeout' },
] as Array<{ label: string; value: AiRequestStatus }>

const form = ref({
  status: 'pending' as AiRequestStatus,
  processed_prompt: '',
  negative_prompt: '',
  model_used: '',
  engine_provider: '',
  error_message: '',
  error_code: '',
})

watch(
  () => props.visible,
  visible => {
    if (!visible || !props.requestId) {
      detail.value = null
      return
    }
    loadRequest()
  },
)

async function loadRequest() {
  if (!props.requestId) return
  loading.value = true
  try {
    const res = await getAiRequest(props.requestId)
    detail.value = res.data
  } catch {
    detail.value = buildMockRequest(props.requestId)
  } finally {
    if (detail.value) {
      form.value = {
        status: detail.value.status as AiRequestStatus,
        processed_prompt: detail.value.processed_prompt || '',
        negative_prompt: detail.value.negative_prompt || '',
        model_used: detail.value.model_used || '',
        engine_provider: detail.value.engine_provider || '',
        error_message: detail.value.error_message || '',
        error_code: detail.value.error_code || '',
      }
    }
    loading.value = false
  }
}

async function saveChanges() {
  if (!detail.value) return
  saving.value = true
  try {
    const payload: UpdateAiRequestPayload = {
      status: form.value.status,
      processed_prompt: form.value.processed_prompt || null,
      negative_prompt: form.value.negative_prompt || null,
      model_used: form.value.model_used || null,
      engine_provider: form.value.engine_provider || null,
      error_message: form.value.error_message || null,
      error_code: form.value.error_code || null,
      metadata: detail.value.metadata || null,
    }
    await updateAiRequest(detail.value.id, payload)
    await loadRequest()
    emit('updated')
  } catch {
    // noop
  } finally {
    saving.value = false
  }
}

async function handleRetry() {
  if (!detail.value) return
  actionLoading.value = true
  try {
    await retryAiRequest(detail.value.id)
    await loadRequest()
    emit('updated')
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleCancel() {
  if (!detail.value) return
  actionLoading.value = true
  try {
    await cancelAiRequest(detail.value.id)
    await loadRequest()
    emit('updated')
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleDelete() {
  if (!detail.value) return
  actionLoading.value = true
  try {
    await deleteAiRequest(detail.value.id)
    await loadRequest()
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
    await restoreAiRequest(detail.value.id)
    await loadRequest()
    emit('updated')
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleNotify(type: 'completed' | 'failed') {
  if (!detail.value) return
  notifying.value = type
  try {
    await notifyAiRequestUser(detail.value.id, { type })
  } catch {
    // noop
  } finally {
    notifying.value = null
  }
}

function close() {
  emit('update:visible', false)
}

function buildMockRequest(requestId: number): AiRequestDetail {
  return {
    id: requestId,
    uuid: 'req_001',
    type: 'text_to_image',
    status: 'completed',
    user_prompt: 'Luxury perfume bottle on stone podium with soft shadows and cinematic highlights.',
    processed_prompt: 'Luxury perfume bottle on dark stone podium, premium editorial lighting, clean luxury branding aesthetic.',
    negative_prompt: 'blur, duplicate bottle, extra cap, low quality, watermark',
    hidden_prompt: 'internal style boosts',
    model_used: 'flux-pro',
    engine_provider: 'fal',
    width: 1024,
    height: 1024,
    steps: 30,
    cfg_scale: 6.5,
    sampler: 'dpm++',
    seed: 4839201,
    num_images: 4,
    denoising_strength: null,
    input_image_path: null,
    mask_image_path: null,
    credits_consumed: 12,
    processing_time_ms: 4200,
    error_message: null,
    error_code: null,
    retry_count: 0,
    ip_address: '192.168.1.12',
    user_agent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    request_payload: { prompt: 'Luxury perfume bottle...', width: 1024, height: 1024, num_images: 4 },
    response_payload: { provider_request_id: 'fal_98x2', images: 4, latency_ms: 4200 },
    metadata: { queue: 'premium', source: 'web_dashboard' },
    started_at: '2026-04-04T08:30:00Z',
    completed_at: '2026-04-04T08:30:04Z',
    created_at: '2026-04-04T08:30:00Z',
    updated_at: '2026-04-04T08:30:04Z',
    deleted_at: null,
    user: { id: 1, name: 'Sara Ahmed', email: 'sara@flash.io', avatar: null, status: 'active' },
    subscription: { id: 10, status: 'active', plan: { id: 2, name: 'Starter', slug: 'starter' } },
    visual_style: { id: 3, name: 'Editorial Luxe', slug: 'editorial-luxe', thumbnail: null, category: 'Luxury', prompt_prefix: 'premium studio shot', prompt_suffix: 'dramatic highlights' },
    generated_images: [
      { id: 1, uuid: 'img_001', file_path: '/demo/perfume-1.png', file_name: 'perfume-1.png', disk: 'public', mime_type: 'image/png', file_size: 284000, width: 1024, height: 1024, thumbnail_path: null, is_public: false, is_nsfw: false, download_count: 12, view_count: 45, created_at: '2026-04-04T08:30:04Z' },
      { id: 2, uuid: 'img_002', file_path: '/demo/perfume-2.png', file_name: 'perfume-2.png', disk: 'public', mime_type: 'image/png', file_size: 278000, width: 1024, height: 1024, thumbnail_path: null, is_public: false, is_nsfw: false, download_count: 7, view_count: 29, created_at: '2026-04-04T08:30:04Z' },
    ],
    usage_logs: [
      { id: 1, action: 'generation', credits_used: 8, feature: { id: 1, name: 'Text to Image', slug: 'text-to-image' }, created_at: '2026-04-04T08:30:04Z' },
      { id: 2, action: 'premium_queue', credits_used: 4, feature: { id: 5, name: 'Priority Queue', slug: 'priority-queue' }, created_at: '2026-04-04T08:30:04Z' },
    ],
    stats: { generated_images_count: 2, usage_logs_count: 2, total_credits_logged: 12 },
  }
}

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

function formatDuration(value: number | null) {
  if (!value) return '—'
  if (value < 1000) return `${value} ms`
  return `${(value / 1000).toFixed(1)} s`
}

function formatBytes(value: number) {
  if (value < 1024) return `${value} B`
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(0)} KB`
  return `${(value / (1024 * 1024)).toFixed(1)} MB`
}

function formatJson(value: Record<string, unknown> | null) {
  if (!value) return '—'
  return JSON.stringify(value, null, 2)
}

function initials(name: string) {
  return name.split(' ').map(item => item[0]).join('').slice(0, 2)
}

function canRetry(status: string) {
  return ['failed', 'cancelled', 'timeout'].includes(status)
}

function canCancel(status: string) {
  return ['pending', 'processing'].includes(status)
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="close"
    header="AI Request Detail"
    :modal="true"
    position="right"
    :style="{ width: '680px', maxWidth: '95vw', height: '100vh', margin: 0, borderRadius: 0 }"
    :draggable="false"
    class="ai-detail-drawer"
  >
    <div v-if="loading" class="drawer-loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <div v-else-if="detail" class="drawer-content">
      <div class="hero-card">
        <div class="hero-row">
          <div class="hero-main">
            <div class="hero-title-row">
              <h2>#{{ detail.id }} · {{ detail.uuid }}</h2>
              <Tag :value="detail.status" :severity="statusSeverity(detail.status)" class="mini-tag" />
              <Tag :value="typeLabel(detail.type)" severity="info" class="mini-tag" />
            </div>
            <p class="hero-sub">
              {{ detail.user?.name || 'Unknown User' }}
              <template v-if="detail.subscription?.plan"> · {{ detail.subscription.plan.name }}</template>
              <template v-if="detail.engine_provider"> · {{ detail.engine_provider }}</template>
              <template v-if="detail.model_used"> · {{ detail.model_used }}</template>
            </p>
            <p class="hero-prompt">{{ detail.user_prompt }}</p>
          </div>
          <div class="hero-actions">
            <Button v-if="canRetry(detail.status)" icon="pi pi-refresh" severity="secondary" text rounded size="small" :loading="actionLoading" @click="handleRetry" />
            <Button v-if="canCancel(detail.status)" icon="pi pi-times" severity="secondary" text rounded size="small" :loading="actionLoading" @click="handleCancel" />
            <Button v-if="detail.deleted_at" icon="pi pi-replay" severity="secondary" text rounded size="small" :loading="actionLoading" @click="handleRestore" />
            <Button v-else icon="pi pi-trash" severity="danger" text rounded size="small" :loading="actionLoading" @click="handleDelete" />
          </div>
        </div>

        <div class="stats-grid">
          <article class="stat-card">
            <span class="stat-k">Credits</span>
            <strong>{{ detail.credits_consumed }}</strong>
            <small>{{ detail.stats.total_credits_logged }} logged</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Images</span>
            <strong>{{ detail.stats.generated_images_count }}</strong>
            <small>{{ detail.num_images }} requested</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Duration</span>
            <strong>{{ formatDuration(detail.processing_time_ms) }}</strong>
            <small>{{ detail.retry_count }} retries</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Resolution</span>
            <strong>{{ detail.width || '—' }}×{{ detail.height || '—' }}</strong>
            <small>{{ detail.steps || '—' }} steps</small>
          </article>
        </div>

        <div class="notify-row">
          <Button label="Notify Completed" size="small" severity="success" outlined :loading="notifying === 'completed'" @click="handleNotify('completed')" />
          <Button label="Notify Failed" size="small" severity="danger" outlined :loading="notifying === 'failed'" @click="handleNotify('failed')" />
        </div>
      </div>

      <Tabs value="overview" class="drawer-tabs">
        <TabList>
          <Tab value="overview">Overview</Tab>
          <Tab value="outputs">Outputs</Tab>
          <Tab value="payloads">Payloads</Tab>
          <Tab value="edit">Edit</Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="overview">
            <div class="section-grid">
              <section class="info-card">
                <h3 class="section-title">User</h3>
                <div class="user-row">
                  <div class="user-avatar">
                    <img v-if="detail.user?.avatar" :src="detail.user.avatar" :alt="detail.user.name" />
                    <span v-else>{{ initials(detail.user?.name || 'Unknown User') }}</span>
                  </div>
                  <div class="user-copy">
                    <span class="user-name">{{ detail.user?.name || 'Unknown User' }}</span>
                    <span class="user-sub">{{ detail.user?.email || 'No email' }}</span>
                    <span class="user-sub">Status: {{ detail.user?.status || '—' }}</span>
                  </div>
                </div>
              </section>

              <section class="info-card">
                <h3 class="section-title">Generation Params</h3>
                <div class="meta-list">
                  <div class="meta-row"><span>Model</span><span>{{ detail.model_used || '—' }}</span></div>
                  <div class="meta-row"><span>Engine</span><span>{{ detail.engine_provider || '—' }}</span></div>
                  <div class="meta-row"><span>Sampler</span><span>{{ detail.sampler || '—' }}</span></div>
                  <div class="meta-row"><span>CFG Scale</span><span>{{ detail.cfg_scale || '—' }}</span></div>
                  <div class="meta-row"><span>Seed</span><span>{{ detail.seed || '—' }}</span></div>
                  <div class="meta-row"><span>Started</span><span>{{ formatDateTime(detail.started_at) }}</span></div>
                </div>
              </section>
            </div>

            <section class="info-card">
              <h3 class="section-title">Prompt Stack</h3>
              <div class="prompt-stack">
                <div class="prompt-block">
                  <span class="prompt-label">User Prompt</span>
                  <p>{{ detail.user_prompt }}</p>
                </div>
                <div class="prompt-block">
                  <span class="prompt-label">Processed Prompt</span>
                  <p>{{ detail.processed_prompt || '—' }}</p>
                </div>
                <div class="prompt-block">
                  <span class="prompt-label">Negative Prompt</span>
                  <p>{{ detail.negative_prompt || '—' }}</p>
                </div>
              </div>
            </section>

            <section v-if="detail.error_message || detail.error_code" class="info-card error-card">
              <h3 class="section-title">Error Info</h3>
              <div class="meta-list">
                <div class="meta-row"><span>Error Code</span><span>{{ detail.error_code || '—' }}</span></div>
                <div class="meta-row"><span>Message</span><span>{{ detail.error_message || '—' }}</span></div>
              </div>
            </section>
          </TabPanel>

          <TabPanel value="outputs">
            <section class="info-card">
              <h3 class="section-title">Generated Images</h3>
              <div class="image-grid">
                <article v-for="image in detail.generated_images || []" :key="image.id" class="image-card">
                  <div class="image-preview">
                    <img v-if="image.file_path" :src="image.file_path" :alt="image.file_name" />
                    <span v-else>{{ image.file_name }}</span>
                  </div>
                  <div class="image-copy">
                    <span class="image-name">{{ image.file_name }}</span>
                    <span class="image-meta">{{ image.width }}×{{ image.height }} · {{ formatBytes(image.file_size) }}</span>
                    <span class="image-meta">Views {{ image.view_count }} · Downloads {{ image.download_count }}</span>
                  </div>
                </article>
              </div>
            </section>

            <section class="info-card">
              <h3 class="section-title">Usage Logs</h3>
              <div class="log-list">
                <div v-for="log in detail.usage_logs || []" :key="log.id" class="log-row">
                  <div>
                    <span class="log-action">{{ log.action }}</span>
                    <span class="log-sub">{{ log.feature?.name || 'No feature linked' }}</span>
                  </div>
                  <div class="log-meta">
                    <strong>{{ log.credits_used }} cr</strong>
                    <span>{{ formatDateTime(log.created_at) }}</span>
                  </div>
                </div>
              </div>
            </section>
          </TabPanel>

          <TabPanel value="payloads">
            <div class="payload-grid">
              <section class="payload-card">
                <h3 class="section-title">Request Payload</h3>
                <pre>{{ formatJson(detail.request_payload) }}</pre>
              </section>
              <section class="payload-card">
                <h3 class="section-title">Response Payload</h3>
                <pre>{{ formatJson(detail.response_payload) }}</pre>
              </section>
              <section class="payload-card">
                <h3 class="section-title">Metadata</h3>
                <pre>{{ formatJson(detail.metadata) }}</pre>
              </section>
            </div>
          </TabPanel>

          <TabPanel value="edit">
            <div class="edit-grid">
              <div class="form-field">
                <label>Status</label>
                <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
              </div>
              <div class="form-field">
                <label>Model</label>
                <InputText v-model="form.model_used" size="small" class="w-full" placeholder="flux-pro" />
              </div>
              <div class="form-field">
                <label>Provider</label>
                <InputText v-model="form.engine_provider" size="small" class="w-full" placeholder="fal" />
              </div>
              <div class="form-field form-field-full">
                <label>Processed Prompt</label>
                <Textarea v-model="form.processed_prompt" rows="4" autoResize class="w-full" />
              </div>
              <div class="form-field form-field-full">
                <label>Negative Prompt</label>
                <Textarea v-model="form.negative_prompt" rows="3" autoResize class="w-full" />
              </div>
              <div class="form-field form-field-full">
                <label>Error Message</label>
                <Textarea v-model="form.error_message" rows="3" autoResize class="w-full" />
              </div>
              <div class="form-field">
                <label>Error Code</label>
                <InputText v-model="form.error_code" size="small" class="w-full" placeholder="WORKER_TIMEOUT" />
              </div>
            </div>

            <div class="edit-actions">
              <Button label="Save Changes" size="small" :loading="saving" @click="saveChanges" />
            </div>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </div>
  </Dialog>
</template>

<style scoped>
.drawer-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 220px;
  color: var(--text-muted);
  font-size: 1.2rem;
}

.drawer-content {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.hero-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--card-border);
  border-radius: 12px;
  background: var(--card-bg);
}

.hero-row {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: flex-start;
}

.hero-main { min-width: 0; flex: 1; }
.hero-title-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}
.hero-title-row h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
}
.hero-sub {
  margin: 4px 0 0;
  font-size: 0.64rem;
  color: var(--text-muted);
}
.hero-prompt {
  margin: 8px 0 0;
  font-size: 0.74rem;
  line-height: 1.45;
  color: var(--text-primary);
}
.hero-actions { display: flex; gap: 0; }

.stats-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
@media (min-width: 640px) {
  .stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.stat-card {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--hover-bg);
}
.stat-k {
  font-size: 0.58rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted);
}
.stat-card strong { font-size: 0.85rem; color: var(--text-primary); }
.stat-card small { font-size: 0.62rem; color: var(--text-muted); }

.notify-row { display: flex; flex-wrap: wrap; gap: 8px; }

:deep(.drawer-tabs .p-tablist) { background: transparent; }
:deep(.drawer-tabs .p-tab) {
  font-size: 0.7rem !important;
  padding: 6px 10px !important;
  color: var(--text-muted) !important;
  background: transparent !important;
  border: none !important;
}
:deep(.drawer-tabs .p-tab-active) { color: var(--active-color) !important; }
:deep(.drawer-tabs .p-tabpanels) { background: transparent; padding: 10px 0 0 !important; }

.section-grid,
.payload-grid,
.edit-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}
@media (min-width: 768px) {
  .section-grid,
  .payload-grid,
  .edit-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

.info-card,
.payload-card {
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
  padding: 10px;
}

.payload-card pre {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.65rem;
  color: var(--text-primary);
}

.section-title {
  margin: 0 0 8px;
  font-size: 0.7rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.user-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: linear-gradient(135deg, #0ea5e9, #2563eb);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  overflow: hidden;
  flex-shrink: 0;
}
.user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.user-copy { display: flex; flex-direction: column; gap: 2px; }
.user-name { font-size: 0.74rem; font-weight: 600; color: var(--text-primary); }
.user-sub { font-size: 0.62rem; color: var(--text-muted); }

.meta-list {
  display: flex;
  flex-direction: column;
  gap: 0;
  border: 1px solid var(--card-border);
  border-radius: 8px;
  overflow: hidden;
}
.meta-row {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 7px 9px;
  border-bottom: 1px solid var(--card-border);
  font-size: 0.66rem;
}
.meta-row:last-child { border-bottom: none; }
.meta-row span:first-child { color: var(--text-muted); }
.meta-row span:last-child { color: var(--text-primary); text-align: right; }

.prompt-stack { display: flex; flex-direction: column; gap: 8px; }
.prompt-block {
  padding: 8px 9px;
  border-radius: 8px;
  background: var(--hover-bg);
}
.prompt-label {
  display: block;
  margin-bottom: 4px;
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted);
}
.prompt-block p {
  margin: 0;
  font-size: 0.72rem;
  line-height: 1.45;
  color: var(--text-primary);
}
.error-card { border-color: rgba(239, 68, 68, 0.3); }

.image-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
@media (max-width: 480px) {
  .image-grid { grid-template-columns: 1fr; }
}
.image-card {
  border: 1px solid var(--card-border);
  border-radius: 10px;
  overflow: hidden;
  background: var(--card-bg);
}
.image-preview {
  aspect-ratio: 1 / 1;
  background: linear-gradient(135deg, rgba(14, 165, 233, 0.18), rgba(37, 99, 235, 0.12));
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  font-size: 0.66rem;
}
.image-preview img { width: 100%; height: 100%; object-fit: cover; }
.image-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 9px;
}
.image-name { font-size: 0.7rem; font-weight: 600; color: var(--text-primary); }
.image-meta { font-size: 0.62rem; color: var(--text-muted); }

.log-list { display: flex; flex-direction: column; gap: 8px; }
.log-row {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: center;
  padding: 8px 9px;
  border-radius: 8px;
  background: var(--hover-bg);
}
.log-action { display: block; font-size: 0.7rem; font-weight: 600; color: var(--text-primary); }
.log-sub,
.log-meta span { display: block; font-size: 0.62rem; color: var(--text-muted); }
.log-meta { text-align: right; }
.log-meta strong { display: block; font-size: 0.7rem; color: var(--text-primary); }

.form-field { display: flex; flex-direction: column; gap: 4px; }
.form-field label {
  font-size: 0.68rem;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.form-field-full { grid-column: 1 / -1; }
.w-full { width: 100%; }
.edit-actions { display: flex; justify-content: flex-end; margin-top: 10px; }
.mini-tag { font-size: 0.56rem !important; padding: 2px 7px !important; }

:deep(.ai-detail-drawer) { margin: 0 !important; border-radius: 0 !important; }
:deep(.ai-detail-drawer .p-dialog-header) {
  background: var(--card-bg);
  border-color: var(--card-border);
  color: var(--text-primary);
  padding: 10px 16px;
}
:deep(.ai-detail-drawer .p-dialog-content) {
  background: var(--card-bg);
  color: var(--text-primary);
  padding: 12px 16px;
  overflow-y: auto;
}
</style>
