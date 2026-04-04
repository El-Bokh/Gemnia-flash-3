<script setup lang="ts">
import { ref, watch } from 'vue'
import { getPlan, togglePlanActive, updateFeatureLimit } from '@/services/planService'
import type { PlanDetail, PlanDetailFeatureItem } from '@/types/plans'
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
import ProgressBar from 'primevue/progressbar'

type LimitPeriod = 'day' | 'week' | 'month' | 'year' | 'lifetime' | null

interface FeatureDraft {
  feature_id: number
  name: string
  type: PlanDetailFeatureItem['type']
  is_enabled: boolean
  usage_limit: number | null
  limit_period: LimitPeriod
  credits_per_use: number
}

const props = defineProps<{
  visible: boolean
  planId: number | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void
  (e: 'updated'): void
}>()

const loading = ref(false)
const actionLoading = ref(false)
const savingFeatureId = ref<number | null>(null)
const detail = ref<PlanDetail | null>(null)
const featureDrafts = ref<FeatureDraft[]>([])

const periodOptions = [
  { label: 'Lifetime', value: 'lifetime' },
  { label: 'Day', value: 'day' },
  { label: 'Week', value: 'week' },
  { label: 'Month', value: 'month' },
  { label: 'Year', value: 'year' },
] as Array<{ label: string; value: Exclude<LimitPeriod, null> }>

watch(
  () => props.visible,
  visible => {
    if (!visible || !props.planId) {
      detail.value = null
      featureDrafts.value = []
      return
    }
    loadPlan()
  },
)

function hydrateFeatureDrafts(plan: PlanDetail) {
  featureDrafts.value = plan.features.map(feature => ({
    feature_id: feature.id,
    name: feature.name,
    type: feature.type,
    is_enabled: feature.pivot.is_enabled,
    usage_limit: feature.pivot.usage_limit,
    limit_period: feature.pivot.limit_period as LimitPeriod,
    credits_per_use: feature.pivot.credits_per_use,
  }))
}

async function loadPlan() {
  if (!props.planId) return

  loading.value = true
  try {
    const res = await getPlan(props.planId)
    detail.value = res.data
    hydrateFeatureDrafts(res.data)
  } catch {
    detail.value = buildMockPlan(props.planId)
    hydrateFeatureDrafts(detail.value)
  } finally {
    loading.value = false
  }
}

async function handleTogglePlan() {
  if (!detail.value) return

  actionLoading.value = true
  try {
    await togglePlanActive(detail.value.id)
    await loadPlan()
    emit('updated')
  } catch {
    detail.value.is_active = !detail.value.is_active
  } finally {
    actionLoading.value = false
  }
}

async function saveFeatureDraft(draft: FeatureDraft) {
  if (!detail.value) return

  savingFeatureId.value = draft.feature_id
  try {
    await updateFeatureLimit(detail.value.id, draft.feature_id, {
      is_enabled: draft.is_enabled,
      usage_limit: draft.usage_limit,
      limit_period: draft.limit_period ?? 'lifetime',
      credits_per_use: draft.credits_per_use,
      constraints: null,
    })
    await loadPlan()
    emit('updated')
  } catch {
    // noop
  } finally {
    savingFeatureId.value = null
  }
}

function close() {
  emit('update:visible', false)
}

function buildMockPlan(planId: number): PlanDetail {
  return {
    id: planId,
    name: 'Starter',
    slug: 'starter',
    description: 'Balanced plan for active teams with flexible AI generation controls.',
    price_monthly: 19,
    price_yearly: 190,
    currency: 'USD',
    credits_monthly: 900,
    credits_yearly: 10800,
    is_free: false,
    is_active: true,
    is_featured: true,
    sort_order: 2,
    trial_days: 7,
    metadata: null,
    created_at: '2026-01-06T10:00:00Z',
    updated_at: '2026-04-02T12:00:00Z',
    deleted_at: null,
    features: [
      {
        id: 1,
        name: 'Text to Image',
        slug: 'text-to-image',
        description: 'Prompt-based image generation.',
        type: 'text_to_image',
        is_active: true,
        sort_order: 1,
        pivot: { id: 11, is_enabled: true, usage_limit: 500, limit_period: 'month', credits_per_use: 1, constraints: null },
      },
      {
        id: 2,
        name: 'Image to Image',
        slug: 'image-to-image',
        description: 'Transform uploaded assets into new outputs.',
        type: 'image_to_image',
        is_active: true,
        sort_order: 2,
        pivot: { id: 12, is_enabled: true, usage_limit: 150, limit_period: 'month', credits_per_use: 2, constraints: null },
      },
      {
        id: 3,
        name: 'Upscale',
        slug: 'upscale',
        description: 'High-resolution output enhancement.',
        type: 'upscale',
        is_active: true,
        sort_order: 3,
        pivot: { id: 13, is_enabled: false, usage_limit: 30, limit_period: 'month', credits_per_use: 3, constraints: null },
      },
      {
        id: 4,
        name: 'Priority Queue',
        slug: 'priority-queue',
        description: 'Shorter processing times for premium requests.',
        type: 'other',
        is_active: true,
        sort_order: 4,
        pivot: { id: 14, is_enabled: true, usage_limit: null, limit_period: 'lifetime', credits_per_use: 0, constraints: null },
      },
    ],
    features_by_type: [
      { type: 'text_to_image', features: [{ id: 1, name: 'Text to Image', slug: 'text-to-image', is_enabled: true, usage_limit: 500, limit_period: 'month' }] },
      { type: 'image_to_image', features: [{ id: 2, name: 'Image to Image', slug: 'image-to-image', is_enabled: true, usage_limit: 150, limit_period: 'month' }] },
      { type: 'upscale', features: [{ id: 3, name: 'Upscale', slug: 'upscale', is_enabled: false, usage_limit: 30, limit_period: 'month' }] },
      { type: 'other', features: [{ id: 4, name: 'Priority Queue', slug: 'priority-queue', is_enabled: true, usage_limit: null, limit_period: 'lifetime' }] },
    ],
    stats: {
      total_features: 4,
      enabled_features: 3,
      total_subscriptions: 310,
      active_subscriptions: 298,
    },
    recent_subscribers: [
      {
        id: 1,
        user: { id: 11, name: 'Sara Ahmed', email: 'sara@flash.io', avatar: null },
        billing_cycle: 'monthly',
        status: 'active',
        price: 19,
        starts_at: '2026-03-01T00:00:00Z',
        ends_at: '2026-04-01T00:00:00Z',
        created_at: '2026-03-01T00:00:00Z',
      },
      {
        id: 2,
        user: { id: 12, name: 'Omar Ali', email: 'omar@flash.io', avatar: null },
        billing_cycle: 'yearly',
        status: 'active',
        price: 190,
        starts_at: '2026-02-14T00:00:00Z',
        ends_at: '2027-02-14T00:00:00Z',
        created_at: '2026-02-14T00:00:00Z',
      },
      {
        id: 3,
        user: { id: 13, name: 'Mona Khaled', email: 'mona@flash.io', avatar: null },
        billing_cycle: 'monthly',
        status: 'trialing',
        price: 0,
        starts_at: '2026-04-01T00:00:00Z',
        ends_at: '2026-04-08T00:00:00Z',
        created_at: '2026-04-01T00:00:00Z',
      },
    ],
  }
}

function formatMoney(amount: number, currency: string) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: amount % 1 === 0 ? 0 : 2 }).format(amount)
}

function formatDate(value: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function initials(name: string) {
  return name.split(' ').map(part => part[0]).join('').slice(0, 2)
}

function featureTypeLabel(type: PlanDetailFeatureItem['type']) {
  return {
    text_to_image: 'Text→Image',
    image_to_image: 'Image→Image',
    inpainting: 'Inpainting',
    upscale: 'Upscale',
    other: 'Other',
  }[type] || type
}

function featureTypeColor(type: PlanDetailFeatureItem['type']) {
  return {
    text_to_image: '#8b5cf6',
    image_to_image: '#0ea5e9',
    inpainting: '#10b981',
    upscale: '#f59e0b',
    other: '#6b7280',
  }[type] || '#6366f1'
}

function subscriberStatusSeverity(status: string) {
  return { active: 'success', trialing: 'info', cancelled: 'danger', expired: 'secondary' }[status] || 'secondary'
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="close"
    header="Plan Detail"
    :modal="true"
    position="right"
    :style="{ width: '620px', maxWidth: '95vw', height: '100vh', margin: 0, borderRadius: 0 }"
    :draggable="false"
    class="plan-detail-drawer"
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
              <Tag :value="detail.is_active ? 'Active' : 'Inactive'" :severity="detail.is_active ? 'success' : 'secondary'" class="mini-tag" />
              <Tag v-if="detail.is_featured" value="Featured" severity="warn" class="mini-tag" />
              <Tag v-if="detail.is_free" value="Free" severity="info" class="mini-tag" />
            </div>
            <p class="hero-slug">{{ detail.slug }}</p>
            <p class="hero-desc">{{ detail.description || 'No description provided.' }}</p>
          </div>
          <Button
            :icon="detail.is_active ? 'pi pi-pause' : 'pi pi-play'"
            severity="secondary"
            text
            rounded
            size="small"
            :loading="actionLoading"
            @click="handleTogglePlan"
          />
        </div>

        <div class="stat-grid">
          <article class="stat-card">
            <span class="stat-k">Features</span>
            <strong>{{ detail.stats.total_features }}</strong>
            <small>{{ detail.stats.enabled_features }} enabled</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Subscribers</span>
            <strong>{{ detail.stats.total_subscriptions }}</strong>
            <small>{{ detail.stats.active_subscriptions }} active</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Monthly</span>
            <strong>{{ formatMoney(detail.price_monthly, detail.currency) }}</strong>
            <small>{{ detail.credits_monthly }} credits</small>
          </article>
          <article class="stat-card">
            <span class="stat-k">Yearly</span>
            <strong>{{ formatMoney(detail.price_yearly, detail.currency) }}</strong>
            <small>{{ detail.credits_yearly }} credits</small>
          </article>
        </div>

        <div class="progress-block">
          <div class="progress-copy">
            <span>Subscription Activity</span>
            <span>{{ detail.stats.active_subscriptions }}/{{ detail.stats.total_subscriptions }}</span>
          </div>
          <ProgressBar :value="Math.round((detail.stats.active_subscriptions / Math.max(detail.stats.total_subscriptions, 1)) * 100)" :showValue="false" style="height: 5px" />
        </div>
      </div>

      <Tabs value="overview" class="drawer-tabs">
        <TabList>
          <Tab value="overview">Overview</Tab>
          <Tab value="features">Features</Tab>
          <Tab value="subscribers">Subscribers</Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="overview">
            <div class="section">
              <h3 class="section-title">Feature Coverage</h3>
              <div class="type-grid">
                <article v-for="group in detail.features_by_type" :key="group.type" class="type-card">
                  <div class="type-head">
                    <Tag :value="featureTypeLabel(group.type)" class="mini-tag" :style="{ background: `${featureTypeColor(group.type)}16`, color: featureTypeColor(group.type), borderColor: `${featureTypeColor(group.type)}24` }" />
                    <span class="type-count">{{ group.features.length }}</span>
                  </div>
                  <div class="type-list">
                    <div v-for="feature in group.features" :key="feature.id" class="type-item">
                      <span class="type-item-name">{{ feature.name }}</span>
                      <span class="type-item-meta">
                        {{ feature.is_enabled ? 'Enabled' : 'Disabled' }}
                        <template v-if="feature.usage_limit !== null"> · {{ feature.usage_limit }}/{{ feature.limit_period || 'lifetime' }}</template>
                      </span>
                    </div>
                  </div>
                </article>
              </div>
            </div>

            <div class="meta-section">
              <div class="meta-row"><span>Trial Days</span><span>{{ detail.trial_days }}</span></div>
              <div class="meta-row"><span>Sort Order</span><span>{{ detail.sort_order }}</span></div>
              <div class="meta-row"><span>Created</span><span>{{ formatDate(detail.created_at) }}</span></div>
              <div class="meta-row"><span>Updated</span><span>{{ formatDate(detail.updated_at) }}</span></div>
            </div>
          </TabPanel>

          <TabPanel value="features">
            <div class="feature-editor-grid">
              <article v-for="draft in featureDrafts" :key="draft.feature_id" class="edit-card">
                <div class="edit-head">
                  <div>
                    <div class="edit-title">{{ draft.name }}</div>
                    <Tag :value="featureTypeLabel(draft.type)" class="mini-tag" :style="{ background: `${featureTypeColor(draft.type)}16`, color: featureTypeColor(draft.type), borderColor: `${featureTypeColor(draft.type)}24` }" />
                  </div>
                  <label class="inline-toggle">
                    <Checkbox v-model="draft.is_enabled" :binary="true" />
                    <span>Enabled</span>
                  </label>
                </div>

                <div class="edit-grid">
                  <div>
                    <span class="mini-label">Usage Limit</span>
                    <input v-model.number="draft.usage_limit" type="number" min="0" class="native-input" placeholder="Unlimited" />
                  </div>
                  <div>
                    <span class="mini-label">Period</span>
                    <Select v-model="draft.limit_period" :options="periodOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
                  </div>
                  <div>
                    <span class="mini-label">Credits / Use</span>
                    <input v-model.number="draft.credits_per_use" type="number" min="0" class="native-input" />
                  </div>
                </div>

                <div class="edit-footer">
                  <Button label="Save Limit" size="small" :loading="savingFeatureId === draft.feature_id" @click="saveFeatureDraft(draft)" />
                </div>
              </article>
            </div>
          </TabPanel>

          <TabPanel value="subscribers">
            <div class="subscriber-list">
              <article v-for="subscriber in detail.recent_subscribers" :key="subscriber.id" class="subscriber-row">
                <div class="subscriber-avatar">
                  <img v-if="subscriber.user.avatar" :src="subscriber.user.avatar" :alt="subscriber.user.name" />
                  <span v-else>{{ initials(subscriber.user.name) }}</span>
                </div>
                <div class="subscriber-copy">
                  <span class="subscriber-name">{{ subscriber.user.name }}</span>
                  <span class="subscriber-email">{{ subscriber.user.email }}</span>
                </div>
                <div class="subscriber-meta">
                  <Tag :value="subscriber.status" :severity="subscriberStatusSeverity(subscriber.status)" class="mini-tag" />
                  <span class="subscriber-price">{{ formatMoney(subscriber.price, detail.currency) }} · {{ subscriber.billing_cycle }}</span>
                  <span class="subscriber-date">{{ formatDate(subscriber.created_at) }}</span>
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

.progress-block {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.progress-copy {
  display: flex;
  justify-content: space-between;
  font-size: 0.64rem;
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

.section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.section-title {
  margin: 0;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-secondary);
}

.type-grid,
.feature-editor-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}

@media (min-width: 640px) {
  .feature-editor-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

.type-card,
.edit-card {
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
  padding: 10px;
}

.type-head,
.edit-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
  margin-bottom: 8px;
}

.type-count {
  font-size: 0.68rem;
  color: var(--text-muted);
}

.type-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.type-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 7px 8px;
  border-radius: 8px;
  background: var(--hover-bg);
}

.type-item-name,
.edit-title {
  font-size: 0.74rem;
  font-weight: 600;
  color: var(--text-primary);
}

.type-item-meta {
  font-size: 0.62rem;
  color: var(--text-muted);
}

.inline-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.68rem;
  color: var(--text-primary);
}

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

.edit-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 8px;
}

.subscriber-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.subscriber-row {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 10px;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--card-bg);
}

.subscriber-avatar {
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

.subscriber-avatar img { width: 100%; height: 100%; object-fit: cover; }

.subscriber-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.subscriber-name {
  font-size: 0.74rem;
  font-weight: 600;
  color: var(--text-primary);
}

.subscriber-email,
.subscriber-date,
.subscriber-price {
  font-size: 0.62rem;
  color: var(--text-muted);
}

.subscriber-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.meta-section {
  border: 1px solid var(--card-border);
  border-radius: 10px;
  overflow: hidden;
}

.meta-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 10px;
  border-bottom: 1px solid var(--card-border);
  font-size: 0.68rem;
}

.meta-row:last-child { border-bottom: none; }
.meta-row span:first-child { color: var(--text-muted); }
.meta-row span:last-child { color: var(--text-primary); font-weight: 500; }

.mini-tag {
  font-size: 0.56rem !important;
  padding: 2px 7px !important;
}

:deep(.plan-detail-drawer) { margin: 0 !important; border-radius: 0 !important; }
:deep(.plan-detail-drawer .p-dialog-header) {
  background: var(--card-bg);
  border-color: var(--card-border);
  color: var(--text-primary);
  padding: 10px 16px;
}
:deep(.plan-detail-drawer .p-dialog-content) {
  background: var(--card-bg);
  color: var(--text-primary);
  padding: 12px 16px;
  overflow-y: auto;
}
</style>