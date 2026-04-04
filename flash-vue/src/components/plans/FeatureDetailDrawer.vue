<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { assignFeatureToPlans, getFeature, toggleFeatureActive } from '@/services/featureService'
import { getPlans } from '@/services/planService'
import type { Feature, FeaturePlanItem, Plan } from '@/types/plans'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Select from 'primevue/select'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'

type LimitPeriod = 'day' | 'week' | 'month' | 'year' | 'lifetime' | null

interface PlanDraft {
  plan_id: number
  name: string
  slug: string
  price_monthly: number
  is_active: boolean
  selected: boolean
  is_enabled: boolean
  usage_limit: number | null
  limit_period: LimitPeriod
  credits_per_use: number
}

const props = defineProps<{
  visible: boolean
  featureId: number | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void
  (e: 'updated'): void
}>()

const { t } = useI18n()
const loading = ref(false)
const savingAssignments = ref(false)
const actionLoading = ref(false)
const detail = ref<Feature | null>(null)
const availablePlans = ref<Plan[]>([])
const assignments = ref<PlanDraft[]>([])

const periodOptions = computed(() => [
  { label: t('planDetail.lifetime'), value: 'lifetime' },
  { label: t('planDetail.day'), value: 'day' },
  { label: t('planDetail.week'), value: 'week' },
  { label: t('planDetail.month'), value: 'month' },
  { label: t('planDetail.year'), value: 'year' },
] as Array<{ label: string; value: Exclude<LimitPeriod, null> }>)

watch(
  () => props.visible,
  visible => {
    if (!visible || !props.featureId) {
      detail.value = null
      assignments.value = []
      return
    }
    loadFeature()
  },
)

async function ensurePlansLoaded() {
  if (availablePlans.value.length) return

  try {
    const res = await getPlans({ sort_by: 'sort_order', sort_dir: 'asc' })
    availablePlans.value = [...res.data].sort((left, right) => left.sort_order - right.sort_order)
  } catch {
    availablePlans.value = [
      { id: 1, name: 'Free', slug: 'free', description: null, price_monthly: 0, price_yearly: 0, currency: 'USD', credits_monthly: 120, credits_yearly: 1200, is_free: true, is_active: true, is_featured: false, sort_order: 1, trial_days: 0, metadata: null, created_at: null, updated_at: null, deleted_at: null, features_count: 8, subscriptions_count: 420, active_subscriptions_count: 402 },
      { id: 2, name: 'Starter', slug: 'starter', description: null, price_monthly: 19, price_yearly: 190, currency: 'USD', credits_monthly: 900, credits_yearly: 10800, is_free: false, is_active: true, is_featured: true, sort_order: 2, trial_days: 7, metadata: null, created_at: null, updated_at: null, deleted_at: null, features_count: 14, subscriptions_count: 310, active_subscriptions_count: 298 },
      { id: 3, name: 'Pro', slug: 'pro', description: null, price_monthly: 49, price_yearly: 490, currency: 'USD', credits_monthly: 2800, credits_yearly: 33600, is_free: false, is_active: true, is_featured: true, sort_order: 3, trial_days: 14, metadata: null, created_at: null, updated_at: null, deleted_at: null, features_count: 20, subscriptions_count: 188, active_subscriptions_count: 180 },
      { id: 4, name: 'Enterprise', slug: 'enterprise', description: null, price_monthly: 199, price_yearly: 1990, currency: 'USD', credits_monthly: 12000, credits_yearly: 144000, is_free: false, is_active: false, is_featured: false, sort_order: 4, trial_days: 30, metadata: null, created_at: null, updated_at: null, deleted_at: null, features_count: 28, subscriptions_count: 42, active_subscriptions_count: 39 },
    ]
  }
}

function hydrateAssignments(plans: Plan[], featurePlans: FeaturePlanItem[] = []) {
  assignments.value = plans.map(plan => {
    const linked = featurePlans.find(item => item.id === plan.id)

    return {
      plan_id: plan.id,
      name: plan.name,
      slug: plan.slug,
      price_monthly: plan.price_monthly,
      is_active: plan.is_active,
      selected: !!linked,
      is_enabled: linked?.is_enabled ?? true,
      usage_limit: linked?.usage_limit ?? null,
      limit_period: (linked?.limit_period as LimitPeriod) ?? 'lifetime',
      credits_per_use: linked?.credits_per_use ?? 0,
    }
  })
}

async function loadFeature() {
  if (!props.featureId) return

  loading.value = true
  await ensurePlansLoaded()

  try {
    const res = await getFeature(props.featureId)
    detail.value = res.data
    hydrateAssignments(availablePlans.value, res.data.plans || [])
  } catch {
    detail.value = buildMockFeature(props.featureId)
    hydrateAssignments(availablePlans.value, detail.value.plans || [])
  } finally {
    loading.value = false
  }
}

async function saveAssignments() {
  if (!detail.value) return

  savingAssignments.value = true
  try {
    await assignFeatureToPlans(detail.value.id, {
      plans: assignments.value
        .filter(item => item.selected)
        .map(item => ({
          plan_id: item.plan_id,
          is_enabled: item.is_enabled,
          usage_limit: item.usage_limit,
          limit_period: item.limit_period ?? 'lifetime',
          credits_per_use: item.credits_per_use,
        })),
    })
    await loadFeature()
    emit('updated')
  } catch {
    // noop
  } finally {
    savingAssignments.value = false
  }
}

async function handleToggleFeature() {
  if (!detail.value) return

  actionLoading.value = true
  try {
    await toggleFeatureActive(detail.value.id)
    await loadFeature()
    emit('updated')
  } catch {
    detail.value.is_active = !detail.value.is_active
  } finally {
    actionLoading.value = false
  }
}

function close() {
  emit('update:visible', false)
}

function buildMockFeature(featureId: number): Feature {
  return {
    id: featureId,
    name: 'Text to Image',
    slug: 'text-to-image',
    description: 'Prompt-driven image generation with queue and limit controls.',
    type: 'text_to_image',
    is_active: true,
    sort_order: 1,
    metadata: null,
    created_at: '2026-01-04T10:00:00Z',
    updated_at: '2026-03-01T10:00:00Z',
    plans_count: 3,
    plans: [
      { id: 1, name: 'Free', slug: 'free', is_active: true, is_enabled: true, usage_limit: 20, limit_period: 'month', credits_per_use: 1 },
      { id: 2, name: 'Starter', slug: 'starter', is_active: true, is_enabled: true, usage_limit: 500, limit_period: 'month', credits_per_use: 1 },
      { id: 3, name: 'Pro', slug: 'pro', is_active: true, is_enabled: true, usage_limit: null, limit_period: 'lifetime', credits_per_use: 1 },
    ],
  }
}

function featureTypeLabel(type: Feature['type']) {
  return {
    text_to_image: 'Text→Image',
    image_to_image: 'Image→Image',
    inpainting: 'Inpainting',
    upscale: 'Upscale',
    other: 'Other',
  }[type] || type
}

function featureTypeColor(type: Feature['type']) {
  return {
    text_to_image: '#8b5cf6',
    image_to_image: '#0ea5e9',
    inpainting: '#10b981',
    upscale: '#f59e0b',
    other: '#6b7280',
  }[type] || '#6366f1'
}

function formatDate(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatMoney(amount: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: amount % 1 === 0 ? 0 : 2 }).format(amount)
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="close"
    :header="t('featureDetail.featureDetail')"
    :modal="true"
    position="right"
    :style="{ width: '620px', maxWidth: '95vw', height: '100vh', margin: 0, borderRadius: 0 }"
    :draggable="false"
    class="feature-detail-drawer"
  >
    <div v-if="loading" class="drawer-loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <div v-else-if="detail" class="drawer-content">
      <div class="hero-card">
        <div class="hero-row">
          <div>
            <div class="hero-title-row">
              <h2>{{ detail.name }}</h2>
              <Tag :value="detail.is_active ? t('common.active') : t('common.inactive')" :severity="detail.is_active ? 'success' : 'secondary'" class="mini-tag" />
              <Tag :value="featureTypeLabel(detail.type)" class="mini-tag" :style="{ background: `${featureTypeColor(detail.type)}16`, color: featureTypeColor(detail.type), borderColor: `${featureTypeColor(detail.type)}24` }" />
            </div>
            <p class="hero-slug">{{ detail.slug }}</p>
            <p class="hero-desc">{{ detail.description || t('featureDetail.noDescription') }}</p>
          </div>
          <Button
            :icon="detail.is_active ? 'pi pi-pause' : 'pi pi-play'"
            severity="secondary"
            text
            rounded
            size="small"
            :loading="actionLoading"
            @click="handleToggleFeature"
          />
        </div>

        <div class="stat-grid">
          <article class="stat-card">
            <span class="stat-k">{{ t('featureDetail.plans') }}</span>
            <strong>{{ detail.plans_count ?? detail.plans?.length ?? 0 }}</strong>
            <small>{{ t('featureDetail.assignedPlans') }}</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">{{ t('featureDetail.activePlans') }}</span>
            <strong>{{ assignments.filter(item => item.selected && item.is_active).length }}</strong>
            <small>{{ t('featureDetail.currentActiveCoverage') }}</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">{{ t('featureDetail.sort') }}</span>
            <strong>{{ detail.sort_order }}</strong>
            <small>{{ t('featureDetail.orderIndex') }}</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">{{ t('featureDetail.updated') }}</span>
            <strong>{{ formatDate(detail.updated_at) }}</strong>
            <small>{{ t('featureDetail.lastChange') }}</small>
          </article>
        </div>
      </div>

      <Tabs value="overview" class="drawer-tabs">
        <TabList>
          <Tab value="overview">{{ t('featureDetail.overview') }}</Tab>
          <Tab value="assignments">{{ t('featureDetail.assignments') }}</Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="overview">
            <div class="plan-list">
              <article v-for="plan in detail.plans || []" :key="plan.id" class="plan-row">
                <div class="plan-copy">
                  <span class="plan-name">{{ plan.name }}</span>
                  <span class="plan-slug">{{ plan.slug }}</span>
                </div>
                <div class="plan-meta">
                  <Tag :value="plan.is_enabled ? t('featureDetail.enabled') : t('featureDetail.disabled')" :severity="plan.is_enabled ? 'success' : 'secondary'" class="mini-tag" />
                  <span class="plan-note">
                    <template v-if="plan.usage_limit !== null">{{ plan.usage_limit }}/{{ plan.limit_period || t('planDetail.lifetime') }}</template>
                    <template v-else>{{ t('featureDetail.unlimited') }}</template>
                    <template v-if="plan.credits_per_use"> · {{ plan.credits_per_use }} cr/use</template>
                  </span>
                </div>
              </article>
            </div>
          </TabPanel>

          <TabPanel value="assignments">
            <div class="assign-head">
              <div>
                <h3 class="section-title">{{ t('featureDetail.planAssignments') }}</h3>
                <p class="section-copy">{{ t('featureDetail.planAssignmentsDesc') }}</p>
              </div>
              <Button :label="t('featureDetail.saveAll')" size="small" :loading="savingAssignments" @click="saveAssignments" />
            </div>

            <div class="assignment-grid">
              <article v-for="draft in assignments" :key="draft.plan_id" class="assignment-card" :class="{ selected: draft.selected }">
                <div class="assignment-head">
                  <label class="inline-toggle">
                    <Checkbox v-model="draft.selected" :binary="true" />
                    <span>{{ draft.name }}</span>
                  </label>
                  <span class="plan-price">{{ formatMoney(draft.price_monthly) }}</span>
                </div>

                <p class="assignment-slug">{{ draft.slug }}</p>

                <div v-if="draft.selected" class="assignment-body">
                  <label class="inline-toggle compact">
                    <Checkbox v-model="draft.is_enabled" :binary="true" />
                    <span>{{ t('featureDetail.enabledInPlan') }}</span>
                  </label>

                  <div class="edit-grid">
                    <div>
                      <span class="mini-label">{{ t('planDetail.usageLimit') }}</span>
                      <input v-model.number="draft.usage_limit" type="number" min="0" class="native-input" :placeholder="t('featureDetail.unlimited')" />
                    </div>
                    <div>
                      <span class="mini-label">{{ t('planDetail.period') }}</span>
                      <Select v-model="draft.limit_period" :options="periodOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
                    </div>
                    <div>
                      <span class="mini-label">{{ t('planDetail.creditsPerUse') }}</span>
                      <input v-model.number="draft.credits_per_use" type="number" min="0" class="native-input" />
                    </div>
                  </div>
                </div>
              </article>
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

.hero-slug {
  margin: 2px 0 0;
  font-size: 0.64rem;
  color: var(--text-muted);
}

.hero-desc {
  margin: 8px 0 0;
  font-size: 0.72rem;
  line-height: 1.45;
  color: var(--text-muted);
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

@media (min-width: 640px) {
  .stat-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
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

.stat-card strong {
  font-size: 0.85rem;
  color: var(--text-primary);
}

.stat-card small {
  font-size: 0.62rem;
  color: var(--text-muted);
}

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

.plan-list,
.assignment-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}

@media (min-width: 640px) {
  .assignment-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

.plan-row,
.assignment-card {
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
  padding: 10px;
}

.assignment-card.selected {
  border-color: var(--active-color);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--active-color) 18%, transparent);
}

.plan-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
}

.plan-copy,
.plan-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.plan-name {
  font-size: 0.74rem;
  font-weight: 600;
  color: var(--text-primary);
}

.plan-slug,
.plan-note,
.assignment-slug,
.section-copy {
  font-size: 0.62rem;
  color: var(--text-muted);
}

.assignment-head,
.assign-head {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: flex-start;
}

.assign-head { margin-bottom: 8px; }

.assignment-body {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.inline-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.7rem;
  color: var(--text-primary);
}

.compact { font-size: 0.66rem; }

.edit-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}

@media (min-width: 480px) {
  .edit-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

.mini-label {
  display: block;
  margin-bottom: 4px;
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted);
}

.native-input {
  width: 100%;
  min-height: 34px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  color: var(--text-primary);
  font-size: 0.76rem;
  outline: none;
}

.native-input:focus { border-color: var(--active-color); }
.w-full { width: 100%; }

.section-title {
  margin: 0;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-secondary);
}

.plan-price {
  font-size: 0.68rem;
  font-weight: 600;
  color: var(--text-primary);
}

.mini-tag {
  font-size: 0.56rem !important;
  padding: 2px 7px !important;
}

:deep(.feature-detail-drawer) { margin: 0 !important; border-radius: 0 !important; }
:deep(.feature-detail-drawer .p-dialog-header) {
  background: var(--card-bg);
  border-color: var(--card-border);
  color: var(--text-primary);
  padding: 10px 16px;
}
:deep(.feature-detail-drawer .p-dialog-content) {
  background: var(--card-bg);
  color: var(--text-primary);
  padding: 12px 16px;
  overflow-y: auto;
}
</style>
