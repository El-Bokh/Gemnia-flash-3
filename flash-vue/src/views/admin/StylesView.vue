<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import InputSwitch from 'primevue/inputswitch'
import InputNumber from 'primevue/inputnumber'
import FileUpload from 'primevue/fileupload'
import Image from 'primevue/image'
import {
  getStyles,
  deleteStyle,
  forceDeleteStyle,
  restoreStyle,
  duplicateStyle,
  toggleStyleActive,
  createStyle,
  updateStyle,
} from '@/services/styleService'
import type { VisualStyle, ListStylesParams } from '@/types/styles'

const { t } = useI18n()

// ─── State ─────────────────────────────────────────────
const loading = ref(false)
const styles = ref<VisualStyle[]>([])
const categories = ref<string[]>([])
const search = ref('')
const filterCategory = ref<string | null>(null)
const filterActive = ref<string | null>(null)
const filterPremium = ref<string | null>(null)

// Dialog states
const showForm = ref(false)
const editingId = ref<number | null>(null)
const showDetail = ref(false)
const detailStyle = ref<VisualStyle | null>(null)
const showDeleteConfirm = ref(false)
const deletingId = ref<number | null>(null)
const deletingName = ref('')
const actionLoading = ref(false)

// Form
const form = ref({
  name: '',
  slug: '',
  description: '',
  prompt_prefix: '',
  prompt_suffix: '',
  negative_prompt: '',
  category: '',
  is_active: true,
  is_premium: false,
  sort_order: 0,
})
const thumbnailFile = ref<File | null>(null)
const thumbnailPreview = ref<string | null>(null)

// ─── Filter Options ────────────────────────────────────
const activeOptions = computed(() => [
  { label: t('styles.all'), value: null },
  { label: t('styles.active'), value: 'true' },
  { label: t('styles.inactive'), value: 'false' },
] as { label: string; value: string | null }[])

const premiumOptions = computed(() => [
  { label: t('styles.all'), value: null },
  { label: t('styles.premium'), value: 'true' },
  { label: t('styles.free'), value: 'false' },
] as { label: string; value: string | null }[])

const categoryOptions = computed(() => {
  const opts: { label: string; value: string | null }[] = [
    { label: t('styles.allCategories'), value: null },
  ]
  categories.value.forEach((c) => opts.push({ label: c, value: c }))
  return opts
})

// ─── Stats ─────────────────────────────────────────────
const stats = computed(() => [
  { label: t('styles.totalStyles'), value: styles.value.length, tone: '#6366f1', icon: 'pi pi-palette' },
  { label: t('styles.activeStyles'), value: styles.value.filter((s) => s.is_active).length, tone: '#10b981', icon: 'pi pi-check-circle' },
  { label: t('styles.premiumStyles'), value: styles.value.filter((s) => s.is_premium).length, tone: '#f59e0b', icon: 'pi pi-star' },
  {
    label: t('styles.totalUsage'),
    value: styles.value.reduce((sum, s) => sum + (s.ai_requests_count ?? 0), 0),
    tone: '#8b5cf6',
    icon: 'pi pi-chart-bar',
  },
])

// ─── Fetch ─────────────────────────────────────────────
async function fetchStyles() {
  loading.value = true
  try {
    const params: ListStylesParams = {}
    if (search.value) params.search = search.value
    if (filterCategory.value) params.category = filterCategory.value
    if (filterActive.value !== null) params.is_active = filterActive.value === 'true'
    if (filterPremium.value !== null) params.is_premium = filterPremium.value === 'true'

    const res = await getStyles(params)
    const payload = res.data as any
    styles.value = Array.isArray(payload) ? payload : (payload?.data ?? [])
    categories.value = payload?.categories ?? []
  } catch {
    styles.value = []
  } finally {
    loading.value = false
  }
}

let debounceTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchStyles, 350)
})
watch([filterCategory, filterActive, filterPremium], fetchStyles)
onMounted(fetchStyles)

// ─── Thumbnail URL ─────────────────────────────────────
const apiOrigin = (import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8099/api').replace(/\/api$/, '')

function thumbUrl(style: VisualStyle): string {
  if (!style.thumbnail) return ''
  if (style.thumbnail.startsWith('http')) return style.thumbnail
  return apiOrigin + style.thumbnail
}

// ─── Form Actions ──────────────────────────────────────
function openCreate() {
  editingId.value = null
  form.value = { name: '', slug: '', description: '', prompt_prefix: '', prompt_suffix: '', negative_prompt: '', category: '', is_active: true, is_premium: false, sort_order: 0 }
  thumbnailFile.value = null
  thumbnailPreview.value = null
  showForm.value = true
}

function openEdit(style: VisualStyle) {
  editingId.value = style.id
  form.value = {
    name: style.name,
    slug: style.slug,
    description: style.description ?? '',
    prompt_prefix: style.prompt_prefix ?? '',
    prompt_suffix: style.prompt_suffix ?? '',
    negative_prompt: style.negative_prompt ?? '',
    category: style.category ?? '',
    is_active: style.is_active,
    is_premium: style.is_premium,
    sort_order: style.sort_order,
  }
  thumbnailFile.value = null
  thumbnailPreview.value = style.thumbnail ? thumbUrl(style) : null
  showForm.value = true
}

function onThumbnailSelect(event: any) {
  const file = event.files?.[0]
  if (file) {
    thumbnailFile.value = file
    thumbnailPreview.value = URL.createObjectURL(file)
  }
}

function clearThumbnail() {
  thumbnailFile.value = null
  thumbnailPreview.value = null
}

async function saveStyle() {
  actionLoading.value = true
  try {
    const fd = new FormData()
    fd.append('name', form.value.name)
    if (form.value.slug) fd.append('slug', form.value.slug)
    fd.append('description', form.value.description || '')
    fd.append('prompt_prefix', form.value.prompt_prefix || '')
    fd.append('prompt_suffix', form.value.prompt_suffix || '')
    fd.append('negative_prompt', form.value.negative_prompt || '')
    fd.append('category', form.value.category || '')
    fd.append('is_active', form.value.is_active ? '1' : '0')
    fd.append('is_premium', form.value.is_premium ? '1' : '0')
    fd.append('sort_order', String(form.value.sort_order))
    if (thumbnailFile.value) fd.append('thumbnail', thumbnailFile.value)

    if (editingId.value) {
      await updateStyle(editingId.value, fd)
    } else {
      await createStyle(fd)
    }
    showForm.value = false
    await fetchStyles()
  } catch {
    // errors handled by interceptor
  } finally {
    actionLoading.value = false
  }
}

// ─── Quick Actions ─────────────────────────────────────
async function onToggleActive(style: VisualStyle) {
  try {
    await toggleStyleActive(style.id)
    await fetchStyles()
  } catch { /* */ }
}

async function onDuplicate(style: VisualStyle) {
  try {
    await duplicateStyle(style.id)
    await fetchStyles()
  } catch { /* */ }
}

async function onRestore(style: VisualStyle) {
  try {
    await restoreStyle(style.id)
    await fetchStyles()
  } catch { /* */ }
}

function confirmDelete(style: VisualStyle) {
  deletingId.value = style.id
  deletingName.value = style.name
  showDeleteConfirm.value = true
}

async function onDelete() {
  if (!deletingId.value) return
  actionLoading.value = true
  try {
    await deleteStyle(deletingId.value)
    showDeleteConfirm.value = false
    await fetchStyles()
  } catch { /* */ }
  finally { actionLoading.value = false }
}

function openDetail(style: VisualStyle) {
  detailStyle.value = style
  showDetail.value = true
}
</script>

<template>
  <div class="admin-styles">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h2 class="page-title">{{ t('styles.title') }}</h2>
        <p class="page-sub">{{ t('styles.subtitle') }}</p>
      </div>
      <Button :label="t('styles.addStyle')" icon="pi pi-plus" size="small" @click="openCreate" />
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div v-for="(stat, i) in stats" :key="i" class="stat-card">
        <div class="stat-icon" :style="{ background: stat.tone + '18', color: stat.tone }">
          <i :class="stat.icon" />
        </div>
        <div class="stat-info">
          <span class="stat-value">{{ stat.value }}</span>
          <span class="stat-label">{{ stat.label }}</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <span class="p-input-icon-left filter-item filter-search">
        <i class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('styles.search')" size="small" class="w-full" />
      </span>
      <Select v-model="filterCategory" :options="categoryOptions" optionLabel="label" optionValue="value" :placeholder="t('styles.category')" size="small" class="filter-item" />
      <Select v-model="filterActive" :options="activeOptions" optionLabel="label" optionValue="value" :placeholder="t('styles.status')" size="small" class="filter-item" />
      <Select v-model="filterPremium" :options="premiumOptions" optionLabel="label" optionValue="value" :placeholder="t('styles.type')" size="small" class="filter-item" />
    </div>

    <!-- ─── Mobile Cards ─────────────────────────────── -->
    <div class="d-mobile">
      <div v-if="loading" class="loading-state"><i class="pi pi-spin pi-spinner" /></div>
      <div v-else-if="styles.length === 0" class="empty-state">{{ t('styles.noStyles') }}</div>
      <div v-else class="style-cards">
        <article v-for="style in styles" :key="style.id" class="style-card" :class="{ inactive: !style.is_active }">
          <div class="card-thumb" @click="openDetail(style)">
            <img v-if="style.thumbnail" :src="thumbUrl(style)" :alt="style.name" class="thumb-img" />
            <div v-else class="thumb-placeholder"><i class="pi pi-image" /></div>
          </div>
          <div class="card-body">
            <div class="card-top">
              <h4 class="card-name" @click="openDetail(style)">{{ style.name }}</h4>
              <div class="card-badges">
                <Tag v-if="style.is_premium" value="PRO" severity="warn" class="badge-sm" />
                <Tag :value="style.is_active ? t('styles.active') : t('styles.inactive')" :severity="style.is_active ? 'success' : 'danger'" class="badge-sm" />
              </div>
            </div>
            <p class="card-cat">{{ style.category ?? '—' }}</p>
            <p class="card-desc">{{ style.description ?? '' }}</p>
            <div class="card-actions">
              <Button icon="pi pi-eye" text rounded size="small" @click="openDetail(style)" />
              <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(style)" />
              <Button icon="pi pi-copy" text rounded size="small" @click="onDuplicate(style)" />
              <Button :icon="style.is_active ? 'pi pi-eye-slash' : 'pi pi-eye'" text rounded size="small" @click="onToggleActive(style)" />
              <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(style)" />
            </div>
          </div>
        </article>
      </div>
    </div>

    <!-- ─── Desktop Table ────────────────────────────── -->
    <div class="d-desktop">
      <DataTable :value="styles" :loading="loading" stripedRows size="small" dataKey="id" responsiveLayout="scroll" class="styles-table">
        <Column field="thumbnail" :header="t('styles.thumbnail')" style="width: 80px">
          <template #body="{ data }">
            <div class="table-thumb" @click="openDetail(data)">
              <img v-if="data.thumbnail" :src="thumbUrl(data)" :alt="data.name" class="table-thumb-img" />
              <div v-else class="table-thumb-placeholder"><i class="pi pi-image" /></div>
            </div>
          </template>
        </Column>
        <Column field="name" :header="t('styles.name')" sortable>
          <template #body="{ data }">
            <span class="clickable" @click="openDetail(data)">{{ data.name }}</span>
          </template>
        </Column>
        <Column field="slug" :header="t('styles.slug')" sortable />
        <Column field="category" :header="t('styles.category')" sortable>
          <template #body="{ data }">
            <Tag :value="data.category ?? '—'" severity="info" class="badge-sm" />
          </template>
        </Column>
        <Column field="is_active" :header="t('styles.status')" sortable style="width: 100px">
          <template #body="{ data }">
            <Tag :value="data.is_active ? t('styles.active') : t('styles.inactive')" :severity="data.is_active ? 'success' : 'danger'" class="badge-sm" />
          </template>
        </Column>
        <Column field="is_premium" header="PRO" sortable style="width: 70px">
          <template #body="{ data }">
            <Tag v-if="data.is_premium" value="PRO" severity="warn" class="badge-sm" />
            <span v-else>—</span>
          </template>
        </Column>
        <Column field="ai_requests_count" :header="t('styles.usage')" sortable style="width: 90px">
          <template #body="{ data }">{{ data.ai_requests_count ?? 0 }}</template>
        </Column>
        <Column field="sort_order" :header="t('styles.order')" sortable style="width: 70px" />
        <Column :header="t('styles.actions')" style="width: 180px">
          <template #body="{ data }">
            <div class="table-actions">
              <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" v-tooltip.top="t('styles.edit')" />
              <Button icon="pi pi-copy" text rounded size="small" @click="onDuplicate(data)" v-tooltip.top="t('styles.duplicate')" />
              <Button :icon="data.is_active ? 'pi pi-eye-slash' : 'pi pi-eye'" text rounded size="small" @click="onToggleActive(data)" v-tooltip.top="data.is_active ? t('styles.deactivate') : t('styles.activate')" />
              <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" v-tooltip.top="t('styles.delete')" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- ─── Create / Edit Dialog ─────────────────────── -->
    <Dialog v-model:visible="showForm" :header="editingId ? t('styles.editStyle') : t('styles.addStyle')" :modal="true" :style="{ width: '640px' }" class="style-dialog">
      <div class="form-grid">
        <!-- Thumbnail -->
        <div class="form-group form-full">
          <label class="form-label">{{ t('styles.thumbnail') }}</label>
          <div class="thumb-upload-area">
            <div v-if="thumbnailPreview" class="thumb-preview-wrap">
              <img :src="thumbnailPreview" class="thumb-preview" />
              <Button icon="pi pi-times" text rounded size="small" class="thumb-remove" @click="clearThumbnail" />
            </div>
            <FileUpload v-else mode="basic" accept="image/*" :maxFileSize="4194304" :auto="false" :chooseLabel="t('styles.uploadImage')" @select="onThumbnailSelect" class="thumb-uploader" />
          </div>
        </div>

        <!-- Name & Slug -->
        <div class="form-group">
          <label class="form-label">{{ t('styles.name') }} *</label>
          <InputText v-model="form.name" size="small" class="w-full" />
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('styles.slug') }}</label>
          <InputText v-model="form.slug" size="small" class="w-full" :placeholder="t('styles.autoGenerated')" />
        </div>

        <!-- Category & Sort Order -->
        <div class="form-group">
          <label class="form-label">{{ t('styles.category') }}</label>
          <InputText v-model="form.category" size="small" class="w-full" placeholder="e.g. art, digital, photography" />
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('styles.order') }}</label>
          <InputNumber v-model="form.sort_order" :min="0" size="small" class="w-full" />
        </div>

        <!-- Description -->
        <div class="form-group form-full">
          <label class="form-label">{{ t('styles.description') }}</label>
          <Textarea v-model="form.description" rows="2" class="w-full" />
        </div>

        <!-- Prompt Prefix -->
        <div class="form-group form-full">
          <label class="form-label">{{ t('styles.promptPrefix') }}</label>
          <Textarea v-model="form.prompt_prefix" rows="3" class="w-full prompt-field" :placeholder="t('styles.promptPrefixHelp')" />
        </div>

        <!-- Prompt Suffix -->
        <div class="form-group form-full">
          <label class="form-label">{{ t('styles.promptSuffix') }}</label>
          <Textarea v-model="form.prompt_suffix" rows="2" class="w-full prompt-field" :placeholder="t('styles.promptSuffixHelp')" />
        </div>

        <!-- Negative Prompt -->
        <div class="form-group form-full">
          <label class="form-label">{{ t('styles.negativePrompt') }}</label>
          <Textarea v-model="form.negative_prompt" rows="2" class="w-full prompt-field" :placeholder="t('styles.negativePromptHelp')" />
        </div>

        <!-- Switches -->
        <div class="form-group form-switches">
          <div class="switch-item">
            <InputSwitch v-model="form.is_active" />
            <label>{{ t('styles.active') }}</label>
          </div>
          <div class="switch-item">
            <InputSwitch v-model="form.is_premium" />
            <label>{{ t('styles.premium') }}</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="t('styles.cancel')" text size="small" @click="showForm = false" />
        <Button :label="editingId ? t('styles.save') : t('styles.create')" size="small" :loading="actionLoading" @click="saveStyle" :disabled="!form.name" />
      </template>
    </Dialog>

    <!-- ─── Detail Dialog ────────────────────────────── -->
    <Dialog v-model:visible="showDetail" :header="detailStyle?.name ?? ''" :modal="true" :style="{ width: '600px' }" class="style-dialog">
      <div v-if="detailStyle" class="detail-content">
        <div class="detail-thumb" v-if="detailStyle.thumbnail">
          <Image :src="thumbUrl(detailStyle)" :alt="detailStyle.name" preview class="detail-img" />
        </div>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">{{ t('styles.slug') }}</span>
            <span class="detail-value">{{ detailStyle.slug }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ t('styles.category') }}</span>
            <Tag :value="detailStyle.category ?? '—'" severity="info" />
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ t('styles.status') }}</span>
            <Tag :value="detailStyle.is_active ? t('styles.active') : t('styles.inactive')" :severity="detailStyle.is_active ? 'success' : 'danger'" />
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ t('styles.usage') }}</span>
            <span class="detail-value">{{ detailStyle.ai_requests_count ?? 0 }}</span>
          </div>
        </div>
        <div class="detail-section" v-if="detailStyle.description">
          <h4 class="detail-section-title">{{ t('styles.description') }}</h4>
          <p class="detail-text">{{ detailStyle.description }}</p>
        </div>
        <div class="detail-section" v-if="detailStyle.prompt_prefix">
          <h4 class="detail-section-title">{{ t('styles.promptPrefix') }}</h4>
          <pre class="detail-code">{{ detailStyle.prompt_prefix }}</pre>
        </div>
        <div class="detail-section" v-if="detailStyle.prompt_suffix">
          <h4 class="detail-section-title">{{ t('styles.promptSuffix') }}</h4>
          <pre class="detail-code">{{ detailStyle.prompt_suffix }}</pre>
        </div>
        <div class="detail-section" v-if="detailStyle.negative_prompt">
          <h4 class="detail-section-title">{{ t('styles.negativePrompt') }}</h4>
          <pre class="detail-code">{{ detailStyle.negative_prompt }}</pre>
        </div>
      </div>
      <template #footer>
        <Button :label="t('styles.edit')" icon="pi pi-pencil" size="small" @click="() => { showDetail = false; openEdit(detailStyle!) }" />
      </template>
    </Dialog>

    <!-- ─── Delete Confirm ───────────────────────────── -->
    <Dialog v-model:visible="showDeleteConfirm" :header="t('styles.confirmDelete')" :modal="true" :style="{ width: '400px' }">
      <p>{{ t('styles.deleteMessage', { name: deletingName }) }}</p>
      <template #footer>
        <Button :label="t('styles.cancel')" text size="small" @click="showDeleteConfirm = false" />
        <Button :label="t('styles.delete')" severity="danger" size="small" :loading="actionLoading" @click="onDelete" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.admin-styles {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 0;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  flex-wrap: wrap;
}
.page-title { font-size: 1.3rem; font-weight: 700; margin: 0; color: var(--text-primary); }
.page-sub { font-size: 0.78rem; color: var(--text-muted); margin: 4px 0 0; }

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
@media (min-width: 768px) { .stats-grid { grid-template-columns: repeat(4, 1fr); } }
.stat-card {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 12px;
  padding: 14px 16px;
}
.stat-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; flex-shrink: 0;
}
.stat-info { display: flex; flex-direction: column; }
.stat-value { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); line-height: 1; }
.stat-label { font-size: 0.68rem; color: var(--text-muted); margin-top: 2px; }

/* Filters */
.filters-bar {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.filter-item { min-width: 140px; }
.filter-search { flex: 1; min-width: 200px; }

/* Mobile / Desktop toggle */
.d-mobile { display: block; }
.d-desktop { display: none; }
@media (min-width: 768px) {
  .d-mobile { display: none; }
  .d-desktop { display: block; }
}

/* Loading / Empty */
.loading-state, .empty-state {
  text-align: center; padding: 40px 0; color: var(--text-muted); font-size: 0.85rem;
}
.loading-state i { font-size: 1.6rem; }

/* Mobile Cards */
.style-cards { display: flex; flex-direction: column; gap: 10px; }
.style-card {
  display: flex; gap: 12px;
  background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px;
  padding: 12px; transition: opacity 0.2s;
}
.style-card.inactive { opacity: 0.6; }
.card-thumb { width: 72px; height: 72px; border-radius: 8px; overflow: hidden; flex-shrink: 0; cursor: pointer; }
.thumb-img { width: 100%; height: 100%; object-fit: cover; }
.thumb-placeholder {
  width: 100%; height: 100%; background: var(--surface-100); display: flex;
  align-items: center; justify-content: center; color: var(--text-muted); font-size: 1.4rem;
}
.card-body { flex: 1; display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.card-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 6px; }
.card-name { margin: 0; font-size: 0.88rem; font-weight: 600; color: var(--text-primary); cursor: pointer; }
.card-badges { display: flex; gap: 4px; flex-shrink: 0; }
.badge-sm { font-size: 0.6rem !important; padding: 2px 6px !important; }
.card-cat { margin: 0; font-size: 0.7rem; color: var(--text-muted); }
.card-desc { margin: 0; font-size: 0.72rem; color: var(--text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.card-actions { display: flex; gap: 2px; margin-top: auto; }

/* Table */
.table-thumb { width: 48px; height: 48px; border-radius: 6px; overflow: hidden; cursor: pointer; }
.table-thumb-img { width: 100%; height: 100%; object-fit: cover; }
.table-thumb-placeholder {
  width: 100%; height: 100%; background: var(--surface-100); display: flex;
  align-items: center; justify-content: center; color: var(--text-muted); font-size: 1rem;
}
.clickable { cursor: pointer; color: var(--active-color); }
.clickable:hover { text-decoration: underline; }
.table-actions { display: flex; gap: 2px; }

/* Form Dialog */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-full { grid-column: 1 / -1; }
.form-label { font-size: 0.76rem; font-weight: 600; color: var(--text-secondary); }
.form-switches { grid-column: 1 / -1; display: flex; gap: 20px; }
.switch-item { display: flex; align-items: center; gap: 8px; }
.switch-item label { font-size: 0.8rem; color: var(--text-secondary); }
.prompt-field { font-family: 'Cascadia Code', 'Fira Code', monospace; font-size: 0.78rem !important; }

/* Thumbnail upload */
.thumb-upload-area { display: flex; align-items: center; gap: 12px; }
.thumb-preview-wrap { position: relative; width: 100px; height: 100px; border-radius: 8px; overflow: hidden; }
.thumb-preview { width: 100%; height: 100%; object-fit: cover; }
.thumb-remove { position: absolute !important; top: 2px; right: 2px; background: rgba(0,0,0,0.5) !important; color: white !important; }

/* Detail Dialog */
.detail-content { display: flex; flex-direction: column; gap: 16px; }
.detail-thumb { text-align: center; }
.detail-img { max-width: 100%; max-height: 200px; border-radius: 8px; }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.detail-item { display: flex; flex-direction: column; gap: 2px; }
.detail-label { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; }
.detail-value { font-size: 0.85rem; color: var(--text-primary); }
.detail-section { display: flex; flex-direction: column; gap: 4px; }
.detail-section-title { margin: 0; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); }
.detail-text { margin: 0; font-size: 0.8rem; color: var(--text-primary); line-height: 1.5; }
.detail-code {
  margin: 0; padding: 10px 12px; background: var(--surface-100); border-radius: 8px;
  font-family: 'Cascadia Code', 'Fira Code', monospace; font-size: 0.74rem;
  white-space: pre-wrap; word-break: break-word; color: var(--text-primary); line-height: 1.5;
}

.w-full { width: 100%; }
</style>
