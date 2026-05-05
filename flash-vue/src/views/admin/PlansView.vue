<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { deleteFeature, getFeatures, toggleFeatureActive } from '@/services/featureService'
import { deletePlan, duplicatePlan, getPlans, togglePlanActive } from '@/services/planService'
import type { Feature, ListFeaturesParams, ListPlansParams, Plan } from '@/types/plans'
import type { DataTableSortEvent } from 'primevue/datatable'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ProgressBar from 'primevue/progressbar'
import PlanFormDialog from '@/components/plans/PlanFormDialog.vue'
import PlanDetailDrawer from '@/components/plans/PlanDetailDrawer.vue'
import FeatureFormDialog from '@/components/plans/FeatureFormDialog.vue'
import FeatureDetailDrawer from '@/components/plans/FeatureDetailDrawer.vue'
import PlansComparisonView from '@/components/plans/PlansComparisonView.vue'

const { t } = useI18n()

const activeTab = ref('plans')

const plansLoading = ref(true)
const featuresLoading = ref(true)

const plans = ref<Plan[]>([])
const features = ref<Feature[]>([])

const planSearch = ref('')
const featureSearch = ref('')
const planFilter = ref<'all' | 'active' | 'inactive' | 'free' | 'paid'>('all')
const featureTypeFilter = ref<'all' | ListFeaturesParams['type']>('all')
const featureStatusFilter = ref<'all' | 'active' | 'inactive'>('all')

const planSortField = ref<'name' | 'sort_order' | 'price_monthly' | 'subscriptions_count'>('sort_order')
const planSortOrder = ref<'asc' | 'desc'>('asc')
const featureSortField = ref<string>('sort_order')
const featureSortOrder = ref<'asc' | 'desc'>('asc')

const showPlanForm = ref(false)
const showPlanDetail = ref(false)
const editPlan = ref<Plan | null>(null)
const detailPlanId = ref<number | null>(null)

const showFeatureForm = ref(false)
const showFeatureDetail = ref(false)
const editFeature = ref<Feature | null>(null)
const detailFeatureId = ref<number | null>(null)

const actionLoading = ref(false)
const deleteTarget = ref<{ kind: 'plan' | 'feature'; id: number; name: string } | null>(null)
const showDeleteConfirm = ref(false)

const planFilterOptions = computed(() => [
  { label: t('plans.allPlans'), value: 'all' },
  { label: t('plans.active'), value: 'active' },
  { label: t('common.inactive'), value: 'inactive' },
  { label: t('plans.free'), value: 'free' },
  { label: t('plans.paidPlans'), value: 'paid' },
] as Array<{ label: string; value: 'all' | 'active' | 'inactive' | 'free' | 'paid' }>)

const featureTypeOptions = computed(() => [
  { label: t('plans.allTypes'), value: 'all' },
  { label: t('aiRequests.textToImage'), value: 'text_to_image' },
  { label: t('aiRequests.imageToImage'), value: 'image_to_image' },
  { label: t('aiRequests.inpainting'), value: 'inpainting' },
  { label: t('aiRequests.upscale'), value: 'upscale' },
  { label: t('aiRequests.videos'), value: 'video_generation' },
  { label: t('aiRequests.other'), value: 'other' },
] as Array<{ label: string; value: 'all' | NonNullable<ListFeaturesParams['type']> }>)

const featureStatusOptions = computed(() => [
  { label: t('plans.allStatus'), value: 'all' },
  { label: t('plans.active'), value: 'active' },
  { label: t('common.inactive'), value: 'inactive' },
] as Array<{ label: string; value: 'all' | 'active' | 'inactive' }>)

const planStats = computed(() => {
  const total = plans.value.length
  const active = plans.value.filter(plan => plan.is_active).length
  const featured = plans.value.filter(plan => plan.is_featured).length
  const free = plans.value.filter(plan => plan.is_free).length
  return [
    { label: t('plans.plans'), value: total, tone: '#3b82f6', icon: 'pi pi-box' },
    { label: t('plans.active'), value: active, tone: '#10b981', icon: 'pi pi-check-circle' },
    { label: t('plans.featured'), value: featured, tone: '#f59e0b', icon: 'pi pi-star-fill' },
    { label: t('plans.free'), value: free, tone: '#8b5cf6', icon: 'pi pi-gift' },
  ]
})

const featureStats = computed(() => {
  const total = features.value.length
  const active = features.value.filter(feature => feature.is_active).length
  const linkedPlans = features.value.reduce((sum, feature) => sum + (feature.plans_count ?? 0), 0)
  const sorted = [...features.value].sort((left, right) => left.sort_order - right.sort_order)
  const firstSort = sorted[0]?.sort_order ?? 0
  return [
    { label: t('plans.features'), value: total, tone: '#06b6d4', icon: 'pi pi-sliders-h' },
    { label: t('plans.active'), value: active, tone: '#10b981', icon: 'pi pi-bolt' },
    { label: t('plans.linkedPlans'), value: linkedPlans, tone: '#f59e0b', icon: 'pi pi-sitemap' },
    { label: t('plans.topSort'), value: firstSort, tone: '#6b7280', icon: 'pi pi-sort-numeric-down' },
  ]
})

async function fetchPlans() {
  plansLoading.value = true
  try {
    const params: ListPlansParams = {
      search: planSearch.value || undefined,
      sort_by: planSortField.value,
      sort_dir: planSortOrder.value,
    }

    if (planFilter.value === 'active') params.is_active = true
    if (planFilter.value === 'inactive') params.is_active = false
    if (planFilter.value === 'free') params.is_free = true
    if (planFilter.value === 'paid') params.is_free = false

    const res = await getPlans(params)
    plans.value = [...res.data].sort((left, right) => left.sort_order - right.sort_order)
  } catch {
    // API unavailable
  } finally {
    plansLoading.value = false
  }
}

async function fetchFeatures() {
  featuresLoading.value = true
  try {
    const params: ListFeaturesParams = {
      search: featureSearch.value || undefined,
      sort_by: featureSortField.value,
      sort_dir: featureSortOrder.value,
    }

    if (featureTypeFilter.value !== 'all') params.type = featureTypeFilter.value
    if (featureStatusFilter.value === 'active') params.is_active = true
    if (featureStatusFilter.value === 'inactive') params.is_active = false

    const res = await getFeatures(params)
    features.value = [...res.data].sort((left, right) => left.sort_order - right.sort_order)
  } catch {
    // API unavailable
  } finally {
    featuresLoading.value = false
  }
}

function openCreatePlan() {
  editPlan.value = null
  showPlanForm.value = true
}

function openEditPlan(plan: Plan) {
  editPlan.value = plan
  showPlanForm.value = true
}

function openPlanDetail(plan: Plan) {
  detailPlanId.value = plan.id
  showPlanDetail.value = true
}

function openCreateFeature() {
  editFeature.value = null
  showFeatureForm.value = true
}

function openEditFeature(feature: Feature) {
  editFeature.value = feature
  showFeatureForm.value = true
}

function openFeatureDetail(feature: Feature) {
  detailFeatureId.value = feature.id
  showFeatureDetail.value = true
}

function confirmDelete(kind: 'plan' | 'feature', id: number, name: string) {
  deleteTarget.value = { kind, id, name }
  showDeleteConfirm.value = true
}

async function handleDelete() {
  if (!deleteTarget.value) return

  actionLoading.value = true
  try {
    if (deleteTarget.value.kind === 'plan') {
      await deletePlan(deleteTarget.value.id)
      await fetchPlans()
    } else {
      await deleteFeature(deleteTarget.value.id)
      await fetchFeatures()
    }
    showDeleteConfirm.value = false
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleDuplicatePlan(plan: Plan) {
  actionLoading.value = true
  try {
    await duplicatePlan(plan.id)
    await fetchPlans()
  } catch {
    // noop
  } finally {
    actionLoading.value = false
  }
}

async function handleTogglePlan(plan: Plan) {
  actionLoading.value = true
  try {
    await togglePlanActive(plan.id)
    await fetchPlans()
  } catch {
    plan.is_active = !plan.is_active
  } finally {
    actionLoading.value = false
  }
}

async function handleToggleFeature(feature: Feature) {
  actionLoading.value = true
  try {
    await toggleFeatureActive(feature.id)
    await fetchFeatures()
  } catch {
    feature.is_active = !feature.is_active
  } finally {
    actionLoading.value = false
  }
}

function onPlanSaved() {
  showPlanForm.value = false
  fetchPlans()
}

function onFeatureSaved() {
  showFeatureForm.value = false
  fetchFeatures()
}

function onPlanSort(event: DataTableSortEvent) {
  const nextSortField = typeof event.sortField === 'string' ? event.sortField : 'sort_order'
  const allowedFields: Array<typeof planSortField.value> = ['name', 'sort_order', 'price_monthly', 'subscriptions_count']
  planSortField.value = allowedFields.includes(nextSortField as typeof planSortField.value)
    ? (nextSortField as typeof planSortField.value)
    : 'sort_order'
  planSortOrder.value = event.sortOrder === 1 ? 'asc' : 'desc'
  fetchPlans()
}

function onFeatureSort(event: DataTableSortEvent) {
  featureSortField.value = typeof event.sortField === 'string' ? event.sortField : 'sort_order'
  featureSortOrder.value = event.sortOrder === 1 ? 'asc' : 'desc'
  fetchFeatures()
}

function formatMoney(amount: number, currency: string) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: amount % 1 === 0 ? 0 : 2 }).format(amount)
}

function formatDate(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function featureTypeLabel(type: Feature['type']) {
  return {
    text_to_image: 'Text→Image',
    image_to_image: 'Image→Image',
    inpainting: 'Inpainting',
    upscale: 'Upscale',
    video_generation: 'Video',
    text_to_video: 'Text→Video',
    image_to_video: 'Image→Video',
    chat: 'Chat',
    styled_chat: 'Styled Chat',
    multimodal: 'Multimodal',
    other: 'Other',
  }[type] || type
}

function featureTypeColor(type: Feature['type']) {
  return {
    text_to_image: '#8b5cf6',
    image_to_image: '#0ea5e9',
    inpainting: '#10b981',
    upscale: '#f59e0b',
    video_generation: '#ef4444',
    text_to_video: '#ef4444',
    image_to_video: '#ef4444',
    chat: '#6366f1',
    styled_chat: '#6366f1',
    multimodal: '#14b8a6',
    other: '#6b7280',
  }[type] || '#6366f1'
}

function planStateSeverity(plan: Plan) {
  if (!plan.is_active) return 'secondary'
  if (plan.is_free) return 'info'
  if (plan.is_featured) return 'warn'
  return 'success'
}

onMounted(() => {
  fetchPlans()
  fetchFeatures()
})

let planSearchTimeout: ReturnType<typeof setTimeout>
watch(planSearch, () => {
  clearTimeout(planSearchTimeout)
  planSearchTimeout = setTimeout(fetchPlans, 350)
})

let featureSearchTimeout: ReturnType<typeof setTimeout>
watch(featureSearch, () => {
  clearTimeout(featureSearchTimeout)
  featureSearchTimeout = setTimeout(fetchFeatures, 350)
})

watch(planFilter, fetchPlans)
watch(featureTypeFilter, fetchFeatures)
watch(featureStatusFilter, fetchFeatures)
</script>

<template>
  <div class="plans-page">
    <div class="page-toolbar">
      <h1 class="page-title">{{ t('plans.title') }}</h1>
      <div class="toolbar-actions">
        <Button v-if="activeTab === 'plans'" icon="pi pi-plus" :label="t('plans.addPlan')" size="small" @click="openCreatePlan" />
        <Button v-if="activeTab === 'features'" icon="pi pi-plus" :label="t('plans.addFeature')" size="small" @click="openCreateFeature" />
      </div>
    </div>

    <Tabs v-model:value="activeTab" class="plans-tabs">
      <TabList>
        <Tab value="plans">
          <i class="pi pi-box" style="font-size: 0.72rem" />
          <span>{{ t('plans.plansTab') }}</span>
        </Tab>
        <Tab value="features">
          <i class="pi pi-sliders-h" style="font-size: 0.72rem" />
          <span>{{ t('plans.featuresTab') }}</span>
        </Tab>
        <Tab value="comparison">
          <i class="pi pi-table" style="font-size: 0.72rem" />
          <span>{{ t('plans.comparisonTab') }}</span>
        </Tab>
      </TabList>

      <TabPanels>
        <TabPanel value="plans">
          <div class="summary-grid">
            <article v-for="item in planStats" :key="item.label" class="summary-card">
              <span class="summary-icon" :style="{ color: item.tone, background: `${item.tone}12` }">
                <i :class="item.icon" />
              </span>
              <div class="summary-copy">
                <span class="summary-label">{{ item.label }}</span>
                <strong class="summary-value">{{ item.value }}</strong>
              </div>
            </article>
          </div>

          <div class="filters-bar">
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText v-model="planSearch" :placeholder="t('plans.searchPlans')" size="small" class="filter-input" />
            </span>
            <Select v-model="planFilter" :options="planFilterOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <span class="filter-count">{{ plans.length }} {{ t('plans.plans').toLowerCase() }}</span>
          </div>

          <div class="plan-grid d-mobile">
            <article v-for="plan in plans" :key="plan.id" class="plan-card" @click="openPlanDetail(plan)">
              <div class="pc-head">
                <div>
                  <div class="pc-title-row">
                    <h3 class="pc-name">{{ plan.name }}</h3>
                    <Tag :value="plan.is_active ? t('plans.active') : t('common.inactive')" :severity="planStateSeverity(plan)" class="pc-tag" />
                  </div>
                  <p class="pc-slug">{{ plan.slug }}</p>
                </div>
                <Tag v-if="plan.is_featured" :value="t('plans.featured')" severity="warn" class="pc-tag" />
              </div>

              <p class="pc-desc">{{ plan.description || t('plans.noDescription') }}</p>

              <div class="pc-price-row">
                <div>
                  <span class="pc-k">{{ t('plans.monthly') }}</span>
                  <strong>{{ formatMoney(plan.price_monthly, plan.currency) }}</strong>
                </div>
                <div>
                  <span class="pc-k">{{ t('plans.yearly') }}</span>
                  <strong>{{ formatMoney(plan.price_yearly, plan.currency) }}</strong>
                </div>
                <div>
                  <span class="pc-k">{{ t('plans.credits') }}</span>
                  <strong>{{ plan.credits_monthly }}</strong>
                </div>
              </div>

              <div class="pc-progress-row">
                <div class="pc-progress-copy">
                  <span>{{ t('plans.subscribers') }}</span>
                  <span>{{ plan.active_subscriptions_count ?? 0 }}/{{ plan.subscriptions_count ?? 0 }}</span>
                </div>
                <ProgressBar :value="Math.round(((plan.active_subscriptions_count ?? 0) / Math.max(plan.subscriptions_count ?? 1, 1)) * 100)" :showValue="false" style="height: 4px" />
              </div>

              <div class="pc-meta">
                <span><i class="pi pi-bolt" /> {{ plan.features_count ?? 0 }} {{ t('plans.features').toLowerCase() }}</span>
                <span><i class="pi pi-calendar" /> {{ formatDate(plan.created_at) }}</span>
              </div>

              <div class="pc-actions" @click.stop>
                <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openPlanDetail(plan)" v-tooltip.top="'View'" />
                <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" @click="openEditPlan(plan)" v-tooltip.top="'Edit'" />
                <Button icon="pi pi-copy" severity="secondary" text rounded size="small" @click="handleDuplicatePlan(plan)" v-tooltip.top="'Duplicate'" />
                <Button :icon="plan.is_active ? 'pi pi-pause' : 'pi pi-play'" severity="secondary" text rounded size="small" @click="handleTogglePlan(plan)" v-tooltip.top="plan.is_active ? 'Deactivate' : 'Activate'" />
                <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete('plan', plan.id, plan.name)" v-tooltip.top="'Delete'" />
              </div>
            </article>
          </div>

          <div class="table-card d-desktop">
            <DataTable
              :value="plans"
              :loading="plansLoading"
              sortMode="single"
              :sortField="planSortField"
              :sortOrder="planSortOrder === 'asc' ? 1 : -1"
              @sort="onPlanSort"
              stripedRows
              size="small"
              scrollable
              class="entity-table"
              dataKey="id"
            >
              <Column field="name" header="Plan" sortable style="min-width: 220px">
                <template #body="{ data }">
                  <div class="name-cell" @click="openPlanDetail(data)">
                    <div class="name-icon plan-icon">
                      <i class="pi pi-box" />
                    </div>
                    <div class="name-copy">
                      <span class="name-title">{{ data.name }}</span>
                      <span class="name-sub">{{ data.slug }}</span>
                    </div>
                    <Tag v-if="data.is_featured" :value="t('plans.featured')" severity="warn" class="mini-tag" />
                  </div>
                </template>
              </Column>

              <Column field="price_monthly" header="Monthly" sortable style="min-width: 110px">
                <template #body="{ data }">
                  <span class="metric-strong">{{ formatMoney(data.price_monthly, data.currency) }}</span>
                </template>
              </Column>

              <Column field="price_yearly" header="Yearly" sortable style="min-width: 110px">
                <template #body="{ data }">
                  <span class="metric-strong">{{ formatMoney(data.price_yearly, data.currency) }}</span>
                </template>
              </Column>

              <Column field="credits_monthly" header="Credits" style="min-width: 100px">
                <template #body="{ data }">
                  <span class="metric-strong">{{ data.credits_monthly }}</span>
                </template>
              </Column>

              <Column field="features_count" header="Features" style="min-width: 90px">
                <template #body="{ data }">
                  <span class="metric-chip"><i class="pi pi-bolt" /> {{ data.features_count ?? 0 }}</span>
                </template>
              </Column>

              <Column field="subscriptions_count" header="Subs" sortable style="min-width: 120px">
                <template #body="{ data }">
                  <div class="sub-cell">
                    <span class="metric-strong">{{ data.active_subscriptions_count ?? 0 }}/{{ data.subscriptions_count ?? 0 }}</span>
                    <ProgressBar :value="Math.round(((data.active_subscriptions_count ?? 0) / Math.max(data.subscriptions_count ?? 1, 1)) * 100)" :showValue="false" style="height: 4px; width: 70px" />
                  </div>
                </template>
              </Column>

              <Column header="State" style="min-width: 120px">
                <template #body="{ data }">
                  <div class="state-stack">
                    <Tag :value="data.is_active ? t('plans.active') : t('common.inactive')" :severity="planStateSeverity(data)" class="mini-tag" />
                    <Tag v-if="data.is_free" :value="t('plans.free')" severity="info" class="mini-tag" />
                  </div>
                </template>
              </Column>

              <Column header="" style="min-width: 150px; text-align: right" frozen alignFrozen="right">
                <template #body="{ data }">
                  <div class="row-actions">
                    <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openPlanDetail(data)" v-tooltip.left="'View'" />
                    <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" @click="openEditPlan(data)" v-tooltip.left="'Edit'" />
                    <Button icon="pi pi-copy" severity="secondary" text rounded size="small" @click="handleDuplicatePlan(data)" v-tooltip.left="'Duplicate'" />
                    <Button :icon="data.is_active ? 'pi pi-pause' : 'pi pi-play'" severity="secondary" text rounded size="small" @click="handleTogglePlan(data)" v-tooltip.left="data.is_active ? 'Deactivate' : 'Activate'" />
                    <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete('plan', data.id, data.name)" v-tooltip.left="'Delete'" />
                  </div>
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>

        <TabPanel value="features">
          <div class="summary-grid">
            <article v-for="item in featureStats" :key="item.label" class="summary-card">
              <span class="summary-icon" :style="{ color: item.tone, background: `${item.tone}12` }">
                <i :class="item.icon" />
              </span>
              <div class="summary-copy">
                <span class="summary-label">{{ item.label }}</span>
                <strong class="summary-value">{{ item.value }}</strong>
              </div>
            </article>
          </div>

          <div class="filters-bar">
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText v-model="featureSearch" :placeholder="t('plans.searchFeatures')" size="small" class="filter-input" />
            </span>
            <Select v-model="featureTypeFilter" :options="featureTypeOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <Select v-model="featureStatusFilter" :options="featureStatusOptions" optionLabel="label" optionValue="value" class="filter-select" size="small" />
            <span class="filter-count">{{ features.length }} {{ t('plans.features').toLowerCase() }}</span>
          </div>

          <div class="feature-grid d-mobile">
            <article v-for="feature in features" :key="feature.id" class="feature-card" @click="openFeatureDetail(feature)">
              <div class="fc-head">
                <div class="fc-title-group">
                  <div class="name-icon" :style="{ background: `${featureTypeColor(feature.type)}18`, color: featureTypeColor(feature.type) }">
                    <i class="pi pi-sliders-h" />
                  </div>
                  <div>
                    <h3 class="fc-title">{{ feature.name }}</h3>
                    <p class="fc-slug">{{ feature.slug }}</p>
                  </div>
                </div>
                <Tag :value="feature.is_active ? t('plans.active') : t('common.inactive')" :severity="feature.is_active ? 'success' : 'secondary'" class="mini-tag" />
              </div>
              <p class="fc-desc">{{ feature.description || t('plans.noDescription') }}</p>
              <div class="fc-meta-row">
                <Tag :value="featureTypeLabel(feature.type)" severity="info" class="mini-tag" />
                <span class="metric-chip"><i class="pi pi-box" /> {{ feature.plans_count ?? 0 }} plans</span>
                <span class="metric-chip"><i class="pi pi-sort-numeric-down" /> {{ feature.sort_order }}</span>
              </div>
              <div class="pc-actions" @click.stop>
                <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openFeatureDetail(feature)" v-tooltip.top="'View'" />
                <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" @click="openEditFeature(feature)" v-tooltip.top="'Edit'" />
                <Button :icon="feature.is_active ? 'pi pi-pause' : 'pi pi-play'" severity="secondary" text rounded size="small" @click="handleToggleFeature(feature)" v-tooltip.top="feature.is_active ? 'Deactivate' : 'Activate'" />
                <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete('feature', feature.id, feature.name)" v-tooltip.top="'Delete'" />
              </div>
            </article>
          </div>

          <div class="table-card d-desktop">
            <DataTable
              :value="features"
              :loading="featuresLoading"
              sortMode="single"
              :sortField="featureSortField"
              :sortOrder="featureSortOrder === 'asc' ? 1 : -1"
              @sort="onFeatureSort"
              stripedRows
              size="small"
              scrollable
              class="entity-table"
              dataKey="id"
            >
              <Column field="name" header="Feature" sortable style="min-width: 220px">
                <template #body="{ data }">
                  <div class="name-cell" @click="openFeatureDetail(data)">
                    <div class="name-icon" :style="{ background: `${featureTypeColor(data.type)}18`, color: featureTypeColor(data.type) }">
                      <i class="pi pi-sliders-h" />
                    </div>
                    <div class="name-copy">
                      <span class="name-title">{{ data.name }}</span>
                      <span class="name-sub">{{ data.slug }}</span>
                    </div>
                  </div>
                </template>
              </Column>

              <Column field="type" header="Type" sortable style="min-width: 130px">
                <template #body="{ data }">
                  <Tag :value="featureTypeLabel(data.type)" severity="info" class="mini-tag" />
                </template>
              </Column>

              <Column field="plans_count" header="Plans" style="min-width: 90px">
                <template #body="{ data }">
                  <span class="metric-chip"><i class="pi pi-box" /> {{ data.plans_count ?? 0 }}</span>
                </template>
              </Column>

              <Column field="sort_order" header="Sort" sortable style="min-width: 80px">
                <template #body="{ data }">
                  <span class="metric-strong">{{ data.sort_order }}</span>
                </template>
              </Column>

              <Column header="State" style="min-width: 100px">
                <template #body="{ data }">
                  <Tag :value="data.is_active ? t('plans.active') : t('common.inactive')" :severity="data.is_active ? 'success' : 'secondary'" class="mini-tag" />
                </template>
              </Column>

              <Column field="updated_at" header="Updated" sortable style="min-width: 110px">
                <template #body="{ data }">
                  <span class="muted-text">{{ formatDate(data.updated_at) }}</span>
                </template>
              </Column>

              <Column header="" style="min-width: 120px; text-align: right" frozen alignFrozen="right">
                <template #body="{ data }">
                  <div class="row-actions">
                    <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openFeatureDetail(data)" v-tooltip.left="'View'" />
                    <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" @click="openEditFeature(data)" v-tooltip.left="'Edit'" />
                    <Button :icon="data.is_active ? 'pi pi-pause' : 'pi pi-play'" severity="secondary" text rounded size="small" @click="handleToggleFeature(data)" v-tooltip.left="data.is_active ? 'Deactivate' : 'Activate'" />
                    <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete('feature', data.id, data.name)" v-tooltip.left="'Delete'" />
                  </div>
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>

        <TabPanel value="comparison">
          <PlansComparisonView />
        </TabPanel>
      </TabPanels>
    </Tabs>

    <PlanFormDialog v-model:visible="showPlanForm" :plan="editPlan" @saved="onPlanSaved" />
    <PlanDetailDrawer v-model:visible="showPlanDetail" :planId="detailPlanId" @updated="fetchPlans" />

    <FeatureFormDialog v-model:visible="showFeatureForm" :feature="editFeature" @saved="onFeatureSaved" />
    <FeatureDetailDrawer v-model:visible="showFeatureDetail" :featureId="detailFeatureId" @updated="fetchFeatures" />

    <Dialog v-model:visible="showDeleteConfirm" :header="t('common.delete')" :modal="true" :style="{ width: '360px' }">
      <div class="confirm-body">
        <i class="pi pi-exclamation-triangle confirm-icon" />
        <p>{{ t('plans.deleteConfirm', { name: deleteTarget?.name }) }}</p>
        <p class="confirm-sub">{{ t('plans.noUndo') }}</p>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text size="small" @click="showDeleteConfirm = false" />
        <Button :label="t('common.delete')" severity="danger" size="small" :loading="actionLoading" @click="handleDelete" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.plans-page { display: flex; flex-direction: column; gap: 10px; }
.page-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.page-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.toolbar-actions { display: flex; align-items: center; gap: 6px; }

:deep(.plans-tabs .p-tablist) { background: transparent; }
:deep(.plans-tabs .p-tab) {
  font-size: 0.72rem !important;
  padding: 7px 12px !important;
  color: var(--text-muted) !important;
  background: transparent !important;
  border: none !important;
  display: flex;
  align-items: center;
  gap: 5px;
}
:deep(.plans-tabs .p-tab-active) { color: var(--active-color) !important; }
:deep(.plans-tabs .p-tabpanels) { background: transparent; padding: 8px 0 !important; }

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

.filters-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.filter-search { position: relative; flex: 1; min-width: 170px; max-width: 320px; }
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
.filter-count { font-size: 0.7rem; color: var(--text-muted); margin-left: auto; }

.d-mobile { display: grid; }
.d-desktop { display: none; }
@media (min-width: 768px) {
  .d-mobile { display: none; }
  .d-desktop { display: block; }
}

.plan-grid,
.feature-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}
@media (min-width: 560px) {
  .plan-grid,
  .feature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

.plan-card,
.feature-card {
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
.plan-card:hover,
.feature-card:hover {
  border-color: var(--active-color);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--active-color) 20%, transparent);
}
.pc-head,
.fc-head { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; }
.pc-title-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pc-name,
.fc-title { font-size: 0.84rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.pc-slug,
.fc-slug { font-size: 0.62rem; color: var(--text-muted); margin: 2px 0 0; }
.pc-desc,
.fc-desc { font-size: 0.68rem; line-height: 1.35; color: var(--text-muted); margin: 0; }

.pc-price-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.pc-price-row div {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 7px 8px;
  border-radius: 8px;
  background: var(--hover-bg);
}
.pc-k { font-size: 0.58rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.04em; }
.pc-price-row strong { font-size: 0.76rem; color: var(--text-primary); }

.pc-progress-row { display: flex; flex-direction: column; gap: 4px; }
.pc-progress-copy { display: flex; justify-content: space-between; font-size: 0.64rem; color: var(--text-muted); }

.pc-meta,
.fc-meta-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pc-meta span,
.metric-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.64rem;
  color: var(--text-secondary);
}
.pc-actions,
.row-actions { display: flex; align-items: center; gap: 0; }

.fc-title-group { display: flex; align-items: center; gap: 8px; }

.table-card {
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  overflow: hidden;
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
:deep(.entity-table .p-datatable-tbody > tr) {
  background: var(--card-bg);
  color: var(--text-primary);
  transition: background 0.12s;
}
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

.name-cell { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.name-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.72rem;
  flex-shrink: 0;
}
.plan-icon { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.name-copy { display: flex; flex-direction: column; min-width: 0; }
.name-title { font-size: 0.78rem; font-weight: 600; color: var(--text-primary); }
.name-sub { font-size: 0.62rem; color: var(--text-muted); }
.mini-tag { font-size: 0.56rem !important; padding: 1px 6px !important; }
.metric-strong { font-size: 0.75rem; font-weight: 600; color: var(--text-primary); }
.sub-cell { display: flex; flex-direction: column; gap: 4px; }
.state-stack { display: flex; gap: 4px; flex-wrap: wrap; }
.muted-text { font-size: 0.68rem; color: var(--text-muted); }

.confirm-body { text-align: center; padding: 6px 0; }
.confirm-icon { font-size: 2rem; color: #ef4444; margin-bottom: 8px; }
.confirm-body p { font-size: 0.82rem; color: var(--text-primary); margin: 4px 0; }
.confirm-sub { font-size: 0.7rem; color: var(--text-muted); }
</style>