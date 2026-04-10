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
  getProducts,
  deleteProduct,
  forceDeleteProduct,
  restoreProduct,
  duplicateProduct,
  toggleProductActive,
  createProduct,
  updateProduct,
} from '@/services/productService'
import type { Product, ListProductsParams } from '@/types/products'

const { t } = useI18n()

// ─── State ─────────────────────────────────────────────
const loading = ref(false)
const products = ref<Product[]>([])
const categories = ref<string[]>([])
const search = ref('')
const filterCategory = ref<string | null>(null)
const filterActive = ref<string | null>(null)
const filterPremium = ref<string | null>(null)

// Dialog states
const showForm = ref(false)
const editingId = ref<number | null>(null)
const showDetail = ref(false)
const detailProduct = ref<Product | null>(null)
const showDeleteConfirm = ref(false)
const deletingId = ref<number | null>(null)
const deletingName = ref('')
const actionLoading = ref(false)

// Form
const form = ref({
  name: '',
  slug: '',
  description: '',
  hidden_prompt: '',
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
  { label: t('products.all'), value: null },
  { label: t('products.active'), value: 'true' },
  { label: t('products.inactive'), value: 'false' },
] as { label: string; value: string | null }[])

const premiumOptions = computed(() => [
  { label: t('products.all'), value: null },
  { label: t('products.premium'), value: 'true' },
  { label: t('products.free'), value: 'false' },
] as { label: string; value: string | null }[])

const categoryOptions = computed(() => {
  const opts: { label: string; value: string | null }[] = [
    { label: t('products.allCategories'), value: null },
  ]
  categories.value.forEach((c) => opts.push({ label: c, value: c }))
  return opts
})

// ─── Stats ─────────────────────────────────────────────
const stats = computed(() => [
  { label: t('products.totalProducts'), value: products.value.length, tone: '#6366f1', icon: 'pi pi-shopping-bag' },
  { label: t('products.activeProducts'), value: products.value.filter((p) => p.is_active).length, tone: '#10b981', icon: 'pi pi-check-circle' },
  { label: t('products.premiumProducts'), value: products.value.filter((p) => p.is_premium).length, tone: '#f59e0b', icon: 'pi pi-star' },
  {
    label: t('products.totalUsage'),
    value: products.value.reduce((sum, p) => sum + (p.ai_requests_count ?? 0), 0),
    tone: '#8b5cf6',
    icon: 'pi pi-chart-bar',
  },
])

// ─── Fetch ─────────────────────────────────────────────
async function fetchProducts() {
  loading.value = true
  try {
    const params: ListProductsParams = {}
    if (search.value) params.search = search.value
    if (filterCategory.value) params.category = filterCategory.value
    if (filterActive.value !== null) params.is_active = filterActive.value === 'true'
    if (filterPremium.value !== null) params.is_premium = filterPremium.value === 'true'

    const res = await getProducts(params)
    const payload = res.data as any
    products.value = Array.isArray(payload) ? payload : (payload?.data ?? [])
    categories.value = payload?.categories ?? []
  } catch {
    products.value = []
  } finally {
    loading.value = false
  }
}

let debounceTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchProducts, 350)
})
watch([filterCategory, filterActive, filterPremium], fetchProducts)
onMounted(fetchProducts)

// ─── Thumbnail URL ─────────────────────────────────────
const apiOrigin = (import.meta.env.VITE_API_BASE_URL || 'https://klek.studio/api').replace(/\/api$/, '')

function thumbUrl(product: Product): string {
  if (!product.thumbnail) return ''
  if (product.thumbnail.startsWith('http')) return product.thumbnail
  return apiOrigin + product.thumbnail
}

// ─── Form Actions ──────────────────────────────────────
function openCreate() {
  editingId.value = null
  form.value = { name: '', slug: '', description: '', hidden_prompt: '', negative_prompt: '', category: '', is_active: true, is_premium: false, sort_order: 0 }
  thumbnailFile.value = null
  thumbnailPreview.value = null
  showForm.value = true
}

function openEdit(product: Product) {
  editingId.value = product.id
  form.value = {
    name: product.name,
    slug: product.slug,
    description: product.description ?? '',
    hidden_prompt: product.hidden_prompt ?? '',
    negative_prompt: product.negative_prompt ?? '',
    category: product.category ?? '',
    is_active: product.is_active,
    is_premium: product.is_premium,
    sort_order: product.sort_order,
  }
  thumbnailFile.value = null
  thumbnailPreview.value = product.thumbnail ? thumbUrl(product) : null
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

async function saveProduct() {
  actionLoading.value = true
  try {
    const fd = new FormData()
    fd.append('name', form.value.name)
    if (form.value.slug) fd.append('slug', form.value.slug)
    fd.append('description', form.value.description || '')
    fd.append('hidden_prompt', form.value.hidden_prompt || '')
    fd.append('negative_prompt', form.value.negative_prompt || '')
    fd.append('category', form.value.category || '')
    fd.append('is_active', form.value.is_active ? '1' : '0')
    fd.append('is_premium', form.value.is_premium ? '1' : '0')
    fd.append('sort_order', String(form.value.sort_order))
    if (thumbnailFile.value) fd.append('thumbnail', thumbnailFile.value)

    if (editingId.value) {
      await updateProduct(editingId.value, fd)
    } else {
      await createProduct(fd)
    }
    showForm.value = false
    await fetchProducts()
  } catch {
    // errors handled by interceptor
  } finally {
    actionLoading.value = false
  }
}

// ─── Quick Actions ─────────────────────────────────────
async function onToggleActive(product: Product) {
  try {
    await toggleProductActive(product.id)
    await fetchProducts()
  } catch { /* */ }
}

async function onDuplicate(product: Product) {
  try {
    await duplicateProduct(product.id)
    await fetchProducts()
  } catch { /* */ }
}

async function onRestore(product: Product) {
  try {
    await restoreProduct(product.id)
    await fetchProducts()
  } catch { /* */ }
}

function confirmDelete(product: Product) {
  deletingId.value = product.id
  deletingName.value = product.name
  showDeleteConfirm.value = true
}

async function onDelete() {
  if (!deletingId.value) return
  actionLoading.value = true
  try {
    await deleteProduct(deletingId.value)
    showDeleteConfirm.value = false
    await fetchProducts()
  } catch { /* */ }
  finally { actionLoading.value = false }
}

function openDetail(product: Product) {
  detailProduct.value = product
  showDetail.value = true
}
</script>

<template>
  <div class="admin-products">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h2 class="page-title">{{ t('products.title') }}</h2>
        <p class="page-sub">{{ t('products.subtitle') }}</p>
      </div>
      <Button :label="t('products.addProduct')" icon="pi pi-plus" size="small" @click="openCreate" />
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
        <InputText v-model="search" :placeholder="t('products.search')" size="small" class="w-full" />
      </span>
      <Select v-model="filterCategory" :options="categoryOptions" optionLabel="label" optionValue="value" :placeholder="t('products.category')" size="small" class="filter-item" />
      <Select v-model="filterActive" :options="activeOptions" optionLabel="label" optionValue="value" :placeholder="t('products.status')" size="small" class="filter-item" />
      <Select v-model="filterPremium" :options="premiumOptions" optionLabel="label" optionValue="value" :placeholder="t('products.type')" size="small" class="filter-item" />
    </div>

    <!-- ─── Mobile Cards ─────────────────────────────── -->
    <div class="d-mobile">
      <div v-if="loading" class="loading-state"><i class="pi pi-spin pi-spinner" /></div>
      <div v-else-if="products.length === 0" class="empty-state">{{ t('products.noProducts') }}</div>
      <div v-else class="product-cards">
        <article v-for="product in products" :key="product.id" class="product-card" :class="{ inactive: !product.is_active }">
          <div class="card-thumb" @click="openDetail(product)">
            <img v-if="product.thumbnail" :src="thumbUrl(product)" :alt="product.name" class="thumb-img" />
            <div v-else class="thumb-placeholder"><i class="pi pi-image" /></div>
          </div>
          <div class="card-body">
            <div class="card-top">
              <h4 class="card-name" @click="openDetail(product)">{{ product.name }}</h4>
              <div class="card-badges">
                <Tag v-if="product.is_premium" value="PRO" severity="warn" class="badge-sm" />
                <Tag :value="product.is_active ? t('products.active') : t('products.inactive')" :severity="product.is_active ? 'success' : 'danger'" class="badge-sm" />
              </div>
            </div>
            <p class="card-cat">{{ product.category ?? '—' }}</p>
            <p class="card-desc">{{ product.description ?? '' }}</p>
            <div class="card-actions">
              <Button icon="pi pi-eye" text rounded size="small" @click="openDetail(product)" />
              <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(product)" />
              <Button icon="pi pi-copy" text rounded size="small" @click="onDuplicate(product)" />
              <Button :icon="product.is_active ? 'pi pi-eye-slash' : 'pi pi-eye'" text rounded size="small" @click="onToggleActive(product)" />
              <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(product)" />
            </div>
          </div>
        </article>
      </div>
    </div>

    <!-- ─── Desktop Table ────────────────────────────── -->
    <div class="d-desktop">
      <DataTable :value="products" :loading="loading" stripedRows size="small" dataKey="id" responsiveLayout="scroll" class="products-table">
        <Column field="thumbnail" :header="t('products.thumbnail')" style="width: 80px">
          <template #body="{ data }">
            <div class="table-thumb" @click="openDetail(data)">
              <img v-if="data.thumbnail" :src="thumbUrl(data)" :alt="data.name" class="table-thumb-img" />
              <div v-else class="table-thumb-placeholder"><i class="pi pi-image" /></div>
            </div>
          </template>
        </Column>
        <Column field="name" :header="t('products.name')" sortable>
          <template #body="{ data }">
            <span class="clickable" @click="openDetail(data)">{{ data.name }}</span>
          </template>
        </Column>
        <Column field="slug" :header="t('products.slug')" sortable />
        <Column field="category" :header="t('products.category')" sortable>
          <template #body="{ data }">
            <Tag :value="data.category ?? '—'" severity="info" class="badge-sm" />
          </template>
        </Column>
        <Column field="is_active" :header="t('products.status')" sortable style="width: 100px">
          <template #body="{ data }">
            <Tag :value="data.is_active ? t('products.active') : t('products.inactive')" :severity="data.is_active ? 'success' : 'danger'" class="badge-sm" />
          </template>
        </Column>
        <Column field="is_premium" header="PRO" sortable style="width: 70px">
          <template #body="{ data }">
            <Tag v-if="data.is_premium" value="PRO" severity="warn" class="badge-sm" />
            <span v-else>—</span>
          </template>
        </Column>
        <Column field="ai_requests_count" :header="t('products.usage')" sortable style="width: 90px">
          <template #body="{ data }">{{ data.ai_requests_count ?? 0 }}</template>
        </Column>
        <Column field="sort_order" :header="t('products.order')" sortable style="width: 70px" />
        <Column :header="t('products.actions')" style="width: 180px">
          <template #body="{ data }">
            <div class="table-actions">
              <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" v-tooltip.top="t('products.edit')" />
              <Button icon="pi pi-copy" text rounded size="small" @click="onDuplicate(data)" v-tooltip.top="t('products.duplicate')" />
              <Button :icon="data.is_active ? 'pi pi-eye-slash' : 'pi pi-eye'" text rounded size="small" @click="onToggleActive(data)" v-tooltip.top="data.is_active ? t('products.deactivate') : t('products.activate')" />
              <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" v-tooltip.top="t('products.delete')" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- ─── Create / Edit Dialog ─────────────────────── -->
    <Dialog v-model:visible="showForm" :header="editingId ? t('products.editProduct') : t('products.addProduct')" :modal="true" :style="{ width: '640px' }" class="product-dialog">
      <div class="form-grid">
        <!-- Thumbnail -->
        <div class="form-group form-full">
          <label class="form-label">{{ t('products.thumbnail') }}</label>
          <div class="thumb-upload-area">
            <div v-if="thumbnailPreview" class="thumb-preview-wrap">
              <img :src="thumbnailPreview" class="thumb-preview" />
              <Button icon="pi pi-times" text rounded size="small" class="thumb-remove" @click="clearThumbnail" />
            </div>
            <FileUpload v-else mode="basic" accept="image/*" :maxFileSize="4194304" :auto="false" :chooseLabel="t('products.uploadImage')" @select="onThumbnailSelect" class="thumb-uploader" />
          </div>
        </div>

        <!-- Name & Slug -->
        <div class="form-group">
          <label class="form-label">{{ t('products.name') }} *</label>
          <InputText v-model="form.name" size="small" class="w-full" />
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('products.slug') }}</label>
          <InputText v-model="form.slug" size="small" class="w-full" :placeholder="t('products.autoGenerated')" />
        </div>

        <!-- Category & Sort Order -->
        <div class="form-group">
          <label class="form-label">{{ t('products.category') }}</label>
          <InputText v-model="form.category" size="small" class="w-full" placeholder="e.g. electronics, clothing, food" />
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('products.order') }}</label>
          <InputNumber v-model="form.sort_order" :min="0" size="small" class="w-full" />
        </div>

        <!-- Description -->
        <div class="form-group form-full">
          <label class="form-label">{{ t('products.description') }}</label>
          <Textarea v-model="form.description" rows="2" class="w-full" />
        </div>

        <!-- Hidden Prompt -->
        <div class="form-group form-full">
          <label class="form-label">{{ t('products.hiddenPrompt') }}</label>
          <Textarea v-model="form.hidden_prompt" rows="4" class="w-full prompt-field" :placeholder="t('products.hiddenPromptHelp')" />
        </div>

        <!-- Negative Prompt -->
        <div class="form-group form-full">
          <label class="form-label">{{ t('products.negativePrompt') }}</label>
          <Textarea v-model="form.negative_prompt" rows="2" class="w-full prompt-field" :placeholder="t('products.negativePromptHelp')" />
        </div>

        <!-- Switches -->
        <div class="form-group form-switches">
          <div class="switch-item">
            <InputSwitch v-model="form.is_active" />
            <label>{{ t('products.active') }}</label>
          </div>
          <div class="switch-item">
            <InputSwitch v-model="form.is_premium" />
            <label>{{ t('products.premium') }}</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="t('products.cancel')" text size="small" @click="showForm = false" />
        <Button :label="editingId ? t('products.save') : t('products.create')" size="small" :loading="actionLoading" @click="saveProduct" :disabled="!form.name" />
      </template>
    </Dialog>

    <!-- ─── Detail Dialog ────────────────────────────── -->
    <Dialog v-model:visible="showDetail" :header="detailProduct?.name ?? ''" :modal="true" :style="{ width: '600px' }" class="product-dialog">
      <div v-if="detailProduct" class="detail-content">
        <div class="detail-thumb" v-if="detailProduct.thumbnail">
          <Image :src="thumbUrl(detailProduct)" :alt="detailProduct.name" preview class="detail-img" />
        </div>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">{{ t('products.slug') }}</span>
            <span class="detail-value">{{ detailProduct.slug }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ t('products.category') }}</span>
            <Tag :value="detailProduct.category ?? '—'" severity="info" />
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ t('products.status') }}</span>
            <Tag :value="detailProduct.is_active ? t('products.active') : t('products.inactive')" :severity="detailProduct.is_active ? 'success' : 'danger'" />
          </div>
          <div class="detail-item">
            <span class="detail-label">{{ t('products.usage') }}</span>
            <span class="detail-value">{{ detailProduct.ai_requests_count ?? 0 }}</span>
          </div>
        </div>
        <div class="detail-section" v-if="detailProduct.description">
          <h4 class="detail-section-title">{{ t('products.description') }}</h4>
          <p class="detail-text">{{ detailProduct.description }}</p>
        </div>
        <div class="detail-section" v-if="detailProduct.hidden_prompt">
          <h4 class="detail-section-title">{{ t('products.hiddenPrompt') }}</h4>
          <pre class="detail-code">{{ detailProduct.hidden_prompt }}</pre>
        </div>
        <div class="detail-section" v-if="detailProduct.negative_prompt">
          <h4 class="detail-section-title">{{ t('products.negativePrompt') }}</h4>
          <pre class="detail-code">{{ detailProduct.negative_prompt }}</pre>
        </div>
      </div>
      <template #footer>
        <Button :label="t('products.edit')" icon="pi pi-pencil" size="small" @click="() => { showDetail = false; openEdit(detailProduct!) }" />
      </template>
    </Dialog>

    <!-- ─── Delete Confirm ───────────────────────────── -->
    <Dialog v-model:visible="showDeleteConfirm" :header="t('products.confirmDelete')" :modal="true" :style="{ width: '400px' }">
      <p>{{ t('products.deleteMessage', { name: deletingName }) }}</p>
      <template #footer>
        <Button :label="t('products.cancel')" text size="small" @click="showDeleteConfirm = false" />
        <Button :label="t('products.delete')" severity="danger" size="small" :loading="actionLoading" @click="onDelete" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.admin-products {
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
.product-cards { display: flex; flex-direction: column; gap: 10px; }
.product-card {
  display: flex; gap: 12px;
  background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px;
  padding: 12px; transition: opacity 0.2s;
}
.product-card.inactive { opacity: 0.6; }
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
