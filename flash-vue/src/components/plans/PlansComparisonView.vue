<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { getPlansComparison } from '@/services/planService'
import type { ComparisonFeatureItem, ComparisonFeatureRef, ComparisonPlan, ComparisonResponse } from '@/types/plans'
import Tag from 'primevue/tag'

const loading = ref(true)
const comparison = ref<ComparisonResponse | null>(null)

onMounted(loadComparison)

async function loadComparison() {
  loading.value = true
  try {
    const res = await getPlansComparison()
    comparison.value = res.data
  } catch {
    comparison.value = buildMockComparison()
  } finally {
    loading.value = false
  }
}

const featureGroups = computed(() => {
  const map = new Map<string, ComparisonFeatureRef[]>()

  for (const feature of comparison.value?.features || []) {
    if (!map.has(feature.type)) map.set(feature.type, [])
    map.get(feature.type)?.push(feature)
  }

  return Array.from(map.entries()).map(([type, features]) => ({ type, features }))
})

const summaryCards = computed(() => {
  const plans = comparison.value?.plans || []
  const features = comparison.value?.features || []
  return [
    { label: 'Plans', value: plans.length },
    { label: 'Features', value: features.length },
    { label: 'Featured', value: plans.filter(item => item.plan.is_featured).length },
    { label: 'Free', value: plans.filter(item => item.plan.is_free).length },
  ]
})

function buildMockComparison(): ComparisonResponse {
  const features: ComparisonFeatureRef[] = [
    { id: 1, name: 'Text to Image', slug: 'text-to-image', type: 'text_to_image' },
    { id: 2, name: 'Image to Image', slug: 'image-to-image', type: 'image_to_image' },
    { id: 3, name: 'Inpainting', slug: 'inpainting', type: 'inpainting' },
    { id: 4, name: 'Upscale', slug: 'upscale', type: 'upscale' },
    { id: 5, name: 'Priority Queue', slug: 'priority-queue', type: 'other' },
    { id: 6, name: 'Private Mode', slug: 'private-mode', type: 'other' },
  ]

  const createFeature = (id: number, included: boolean, usageLimit: number | null, limitPeriod: ComparisonFeatureItem['limit_period'], creditsPerUse: number): ComparisonFeatureItem => ({
    id,
    name: features.find(feature => feature.id === id)?.name || 'Unknown',
    slug: features.find(feature => feature.id === id)?.slug || 'unknown',
    type: features.find(feature => feature.id === id)?.type || 'other',
    included,
    is_enabled: included,
    usage_limit: usageLimit,
    limit_period: limitPeriod,
    credits_per_use: creditsPerUse,
  })

  return {
    features,
    plans: [
      {
        plan: { id: 1, name: 'Free', slug: 'free', price_monthly: 0, price_yearly: 0, credits_monthly: 120, is_free: true, is_featured: false, trial_days: 0, active_subscribers: 402 },
        features: [createFeature(1, true, 20, 'month', 1), createFeature(2, false, null, null, 0), createFeature(3, false, null, null, 0), createFeature(4, false, null, null, 0), createFeature(5, false, null, null, 0), createFeature(6, false, null, null, 0)],
      },
      {
        plan: { id: 2, name: 'Starter', slug: 'starter', price_monthly: 19, price_yearly: 190, credits_monthly: 900, is_free: false, is_featured: true, trial_days: 7, active_subscribers: 298 },
        features: [createFeature(1, true, 500, 'month', 1), createFeature(2, true, 150, 'month', 2), createFeature(3, true, 60, 'month', 2), createFeature(4, false, null, null, 0), createFeature(5, true, null, 'lifetime', 0), createFeature(6, false, null, null, 0)],
      },
      {
        plan: { id: 3, name: 'Pro', slug: 'pro', price_monthly: 49, price_yearly: 490, credits_monthly: 2800, is_free: false, is_featured: true, trial_days: 14, active_subscribers: 180 },
        features: [createFeature(1, true, null, 'lifetime', 1), createFeature(2, true, null, 'lifetime', 1), createFeature(3, true, 200, 'month', 1), createFeature(4, true, 120, 'month', 3), createFeature(5, true, null, 'lifetime', 0), createFeature(6, true, null, 'lifetime', 0)],
      },
      {
        plan: { id: 4, name: 'Enterprise', slug: 'enterprise', price_monthly: 199, price_yearly: 1990, credits_monthly: 12000, is_free: false, is_featured: false, trial_days: 30, active_subscribers: 39 },
        features: [createFeature(1, true, null, 'lifetime', 0), createFeature(2, true, null, 'lifetime', 0), createFeature(3, true, null, 'lifetime', 0), createFeature(4, true, null, 'lifetime', 0), createFeature(5, true, null, 'lifetime', 0), createFeature(6, true, null, 'lifetime', 0)],
      },
    ],
  }
}

function findFeature(plan: ComparisonPlan, featureId: number) {
  return plan.features.find(feature => feature.id === featureId) || null
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

function typeColor(type: string) {
  return {
    text_to_image: '#8b5cf6',
    image_to_image: '#0ea5e9',
    inpainting: '#10b981',
    upscale: '#f59e0b',
    other: '#6b7280',
  }[type] || '#6366f1'
}

function formatMoney(amount: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: amount % 1 === 0 ? 0 : 2 }).format(amount)
}

function featureUsageLabel(feature: ComparisonFeatureItem | null) {
  if (!feature || !feature.included) return '—'
  if (feature.usage_limit === null) return 'Unlimited'
  return `${feature.usage_limit}/${feature.limit_period || 'lifetime'}`
}

function includedCount(plan: ComparisonPlan) {
  return plan.features.filter(feature => feature.included).length
}
</script>

<template>
  <div class="comparison-view">
    <div v-if="loading" class="comparison-loading">
      <i class="pi pi-spin pi-spinner" /> Loading comparison matrix…
    </div>

    <template v-else-if="comparison">
      <div class="summary-grid">
        <article v-for="card in summaryCards" :key="card.label" class="summary-card">
          <span class="summary-label">{{ card.label }}</span>
          <strong class="summary-value">{{ card.value }}</strong>
        </article>
      </div>

      <div class="mobile-cards d-mobile">
        <article v-for="item in comparison.plans" :key="item.plan.id" class="plan-card">
          <div class="card-head">
            <div>
              <div class="card-title-row">
                <h3>{{ item.plan.name }}</h3>
                <Tag v-if="item.plan.is_featured" value="Featured" severity="warn" class="mini-tag" />
                <Tag v-if="item.plan.is_free" value="Free" severity="info" class="mini-tag" />
              </div>
              <p>{{ item.plan.slug }}</p>
            </div>
            <div class="card-prices">
              <strong>{{ formatMoney(item.plan.price_monthly) }}</strong>
              <span>{{ item.plan.credits_monthly }} credits</span>
            </div>
          </div>

          <div class="card-meta">
            <span><i class="pi pi-users" /> {{ item.plan.active_subscribers }} active</span>
            <span><i class="pi pi-bolt" /> {{ includedCount(item) }} included</span>
            <span><i class="pi pi-clock" /> {{ item.plan.trial_days }}d trial</span>
          </div>

          <div class="card-feature-list">
            <div v-for="feature in item.features.filter(entry => entry.included)" :key="feature.id" class="card-feature-row">
              <span>{{ feature.name }}</span>
              <small>{{ featureUsageLabel(feature) }}</small>
            </div>
          </div>
        </article>
      </div>

      <div class="matrix-wrap d-desktop">
        <table class="matrix-table">
          <thead>
            <tr>
              <th class="sticky-col corner-col">Features</th>
              <th v-for="item in comparison.plans" :key="item.plan.id" class="plan-col">
                <div class="head-plan-name">{{ item.plan.name }}</div>
                <div class="head-plan-price">{{ formatMoney(item.plan.price_monthly) }}</div>
                <div class="head-plan-meta">{{ item.plan.credits_monthly }} cr · {{ item.plan.active_subscribers }} users</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in featureGroups" :key="group.type">
              <tr class="group-row">
                <td class="group-cell" :colspan="comparison.plans.length + 1">
                  <span class="group-dot" :style="{ background: typeColor(group.type) }" />
                  {{ typeLabel(group.type) }}
                </td>
              </tr>
              <tr v-for="feature in group.features" :key="feature.id" class="feature-row">
                <td class="sticky-col feature-label-cell">
                  <span class="feature-name">{{ feature.name }}</span>
                  <span class="feature-slug">{{ feature.slug }}</span>
                </td>
                <td v-for="item in comparison.plans" :key="item.plan.id" class="feature-value-cell" :class="{ included: findFeature(item, feature.id)?.included }">
                  <template v-if="findFeature(item, feature.id)?.included">
                    <i class="pi pi-check-circle included-icon" />
                    <span class="value-primary">{{ featureUsageLabel(findFeature(item, feature.id)) }}</span>
                    <span v-if="(findFeature(item, feature.id)?.credits_per_use || 0) > 0" class="value-sub">
                      {{ findFeature(item, feature.id)?.credits_per_use }} cr/use
                    </span>
                  </template>
                  <template v-else>
                    <span class="value-empty">—</span>
                  </template>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped>
.comparison-view { display: flex; flex-direction: column; gap: 10px; }

.comparison-loading {
  padding: 24px 0;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.78rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

@media (min-width: 768px) {
  .summary-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

.summary-card {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.summary-label {
  font-size: 0.62rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted);
}

.summary-value {
  font-size: 0.94rem;
  color: var(--text-primary);
}

.mobile-cards {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}

.plan-card {
  padding: 12px;
  border-radius: 12px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.card-head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}

.card-title-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.card-title-row h3 {
  margin: 0;
  font-size: 0.86rem;
  color: var(--text-primary);
}

.card-head p {
  margin: 2px 0 0;
  font-size: 0.62rem;
  color: var(--text-muted);
}

.card-prices {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.card-prices strong {
  font-size: 0.84rem;
  color: var(--text-primary);
}

.card-prices span,
.card-meta,
.card-feature-row small {
  font-size: 0.62rem;
  color: var(--text-muted);
}

.card-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.card-feature-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.card-feature-row {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 7px 8px;
  border-radius: 8px;
  background: var(--hover-bg);
  font-size: 0.7rem;
  color: var(--text-primary);
}

.matrix-wrap {
  overflow-x: auto;
  border: 1px solid var(--card-border);
  border-radius: 12px;
  background: var(--card-bg);
}

.matrix-table {
  width: 100%;
  min-width: 860px;
  border-collapse: collapse;
}

.sticky-col {
  position: sticky;
  left: 0;
  z-index: 2;
}

.corner-col,
.plan-col {
  background: var(--hover-bg);
  border-bottom: 1px solid var(--card-border);
}

.corner-col {
  min-width: 220px;
  text-align: left;
  padding: 10px 12px;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 0.64rem;
}

.plan-col {
  min-width: 150px;
  padding: 10px 8px;
  text-align: center;
  border-left: 1px solid var(--card-border);
}

.head-plan-name {
  font-size: 0.74rem;
  font-weight: 700;
  color: var(--text-primary);
}

.head-plan-price {
  margin-top: 2px;
  font-size: 0.78rem;
  color: var(--text-primary);
}

.head-plan-meta {
  margin-top: 2px;
  font-size: 0.58rem;
  color: var(--text-muted);
}

.group-cell {
  padding: 6px 12px;
  background: var(--hover-bg);
  color: var(--text-secondary);
  font-size: 0.64rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.group-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  display: inline-block;
  margin-right: 6px;
}

.feature-label-cell {
  background: var(--card-bg);
  padding: 8px 12px;
  border-top: 1px solid var(--card-border);
}

.feature-name {
  display: block;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-primary);
}

.feature-slug {
  display: block;
  font-size: 0.58rem;
  color: var(--text-muted);
}

.feature-value-cell {
  padding: 8px 10px;
  text-align: center;
  border-top: 1px solid var(--card-border);
  border-left: 1px solid var(--card-border);
  background: var(--card-bg);
}

.feature-value-cell.included {
  background: color-mix(in srgb, var(--active-color) 6%, var(--card-bg));
}

.included-icon {
  display: block;
  margin-bottom: 4px;
  color: #10b981;
}

.value-primary {
  display: block;
  font-size: 0.66rem;
  font-weight: 600;
  color: var(--text-primary);
}

.value-sub,
.value-empty {
  display: block;
  font-size: 0.58rem;
  color: var(--text-muted);
}

.mini-tag {
  font-size: 0.56rem !important;
  padding: 2px 7px !important;
}

.d-mobile { display: grid; }
.d-desktop { display: none; }
@media (min-width: 768px) {
  .d-mobile { display: none; }
  .d-desktop { display: block; }
}
</style>
