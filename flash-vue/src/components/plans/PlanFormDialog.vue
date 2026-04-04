<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { getFeatures } from '@/services/featureService'
import { createPlan, getPlan, syncFeatures, updatePlan } from '@/services/planService'
import type { Feature, Plan, StorePlanFeaturePayload, StorePlanPayload, UpdatePlanPayload } from '@/types/plans'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Tag from 'primevue/tag'

type LimitPeriod = 'day' | 'week' | 'month' | 'year' | 'lifetime' | null

interface EditableFeatureDraft {
  feature_id: number
  name: string
  type: Feature['type']
  selected: boolean
  is_enabled: boolean
  usage_limit: number | null
  limit_period: LimitPeriod
  credits_per_use: number
}

const props = defineProps<{
  visible: boolean
  plan: Plan | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void
  (e: 'saved'): void
}>()

const isEdit = computed(() => !!props.plan)
const saving = ref(false)
const loadingDetail = ref(false)
const errors = ref<Record<string, string>>({})
const availableFeatures = ref<Feature[]>([])
const featureDrafts = ref<EditableFeatureDraft[]>([])

const periodOptions = [
  { label: 'Lifetime', value: 'lifetime' },
  { label: 'Day', value: 'day' },
  { label: 'Week', value: 'week' },
  { label: 'Month', value: 'month' },
  { label: 'Year', value: 'year' },
] as Array<{ label: string; value: Exclude<LimitPeriod, null> }>

const form = ref({
  name: '',
  slug: '',
  description: '',
  price_monthly: 0,
  price_yearly: 0,
  currency: 'USD',
  credits_monthly: 0,
  credits_yearly: 0,
  is_free: false,
  is_active: true,
  is_featured: false,
  sort_order: 1,
  trial_days: 0,
})

function featureTypeColor(type: Feature['type']) {
  return {
    text_to_image: '#8b5cf6',
    image_to_image: '#0ea5e9',
    inpainting: '#10b981',
    upscale: '#f59e0b',
    other: '#6b7280',
  }[type] || '#6366f1'
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

async function ensureFeaturesLoaded() {
  if (availableFeatures.value.length) return
  try {
    const res = await getFeatures({ sort_by: 'sort_order', sort_dir: 'asc' })
    availableFeatures.value = [...res.data].sort((left, right) => left.sort_order - right.sort_order)
  } catch {
    availableFeatures.value = [
      { id: 1, name: 'Text to Image', slug: 'text-to-image', description: 'Prompt-based image generation.', type: 'text_to_image', is_active: true, sort_order: 1, metadata: null, created_at: null, updated_at: null, plans_count: 4 },
      { id: 2, name: 'Image to Image', slug: 'image-to-image', description: 'Transform existing assets.', type: 'image_to_image', is_active: true, sort_order: 2, metadata: null, created_at: null, updated_at: null, plans_count: 3 },
      { id: 3, name: 'Upscale', slug: 'upscale', description: 'Upscale image output.', type: 'upscale', is_active: true, sort_order: 3, metadata: null, created_at: null, updated_at: null, plans_count: 3 },
      { id: 4, name: 'Priority Queue', slug: 'priority-queue', description: 'Priority processing lane.', type: 'other', is_active: true, sort_order: 4, metadata: null, created_at: null, updated_at: null, plans_count: 2 },
    ]
  }
}

function hydrateDrafts() {
  featureDrafts.value = availableFeatures.value.map(feature => ({
    feature_id: feature.id,
    name: feature.name,
    type: feature.type,
    selected: false,
    is_enabled: true,
    usage_limit: null,
    limit_period: 'lifetime',
    credits_per_use: 0,
  }))
}

async function loadPlanDetail() {
  if (!props.plan) return
  loadingDetail.value = true
  try {
    const res = await getPlan(props.plan.id)
    for (const feature of res.data.features) {
      const draft = featureDrafts.value.find(item => item.feature_id === feature.id)
      if (!draft) continue
      draft.selected = true
      draft.is_enabled = feature.pivot.is_enabled
      draft.usage_limit = feature.pivot.usage_limit
      draft.limit_period = feature.pivot.limit_period as LimitPeriod
      draft.credits_per_use = feature.pivot.credits_per_use
    }
  } catch {
    // noop
  } finally {
    loadingDetail.value = false
  }
}

function resetBaseForm() {
  form.value = {
    name: '',
    slug: '',
    description: '',
    price_monthly: 0,
    price_yearly: 0,
    currency: 'USD',
    credits_monthly: 0,
    credits_yearly: 0,
    is_free: false,
    is_active: true,
    is_featured: false,
    sort_order: 1,
    trial_days: 0,
  }
}

watch(
  () => props.visible,
  async visible => {
    if (!visible) return

    errors.value = {}
    await ensureFeaturesLoaded()
    hydrateDrafts()

    if (props.plan) {
      form.value = {
        name: props.plan.name,
        slug: props.plan.slug,
        description: props.plan.description || '',
        price_monthly: props.plan.price_monthly,
        price_yearly: props.plan.price_yearly,
        currency: props.plan.currency,
        credits_monthly: props.plan.credits_monthly,
        credits_yearly: props.plan.credits_yearly,
        is_free: props.plan.is_free,
        is_active: props.plan.is_active,
        is_featured: props.plan.is_featured,
        sort_order: props.plan.sort_order,
        trial_days: props.plan.trial_days,
      }
      await loadPlanDetail()
    } else {
      resetBaseForm()
    }
  },
)

watch(
  () => form.value.name,
  name => {
    if (!isEdit.value || !form.value.slug) {
      form.value.slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
    }
  },
)

onMounted(ensureFeaturesLoaded)

function buildSelectedFeatures(): StorePlanFeaturePayload[] {
  return featureDrafts.value
    .filter(feature => feature.selected)
    .map(feature => ({
      feature_id: feature.feature_id,
      is_enabled: feature.is_enabled,
      usage_limit: feature.usage_limit,
      limit_period: feature.limit_period ?? 'lifetime',
      credits_per_use: feature.credits_per_use,
      constraints: null,
    }))
}

async function save() {
  errors.value = {}

  if (!form.value.name.trim()) {
    errors.value.name = 'Name is required'
    return
  }

  if (!form.value.slug.trim()) {
    errors.value.slug = 'Slug is required'
    return
  }

  saving.value = true
  try {
    const basePayload: StorePlanPayload | UpdatePlanPayload = {
      name: form.value.name,
      slug: form.value.slug,
      description: form.value.description || null,
      price_monthly: Number(form.value.price_monthly),
      price_yearly: Number(form.value.price_yearly),
      currency: form.value.currency || 'USD',
      credits_monthly: Number(form.value.credits_monthly),
      credits_yearly: Number(form.value.credits_yearly),
      is_free: form.value.is_free,
      is_active: form.value.is_active,
      is_featured: form.value.is_featured,
      sort_order: Number(form.value.sort_order),
      trial_days: Number(form.value.trial_days),
      metadata: null,
    }

    const selectedFeatures = buildSelectedFeatures()

    if (props.plan) {
      await updatePlan(props.plan.id, basePayload)
      await syncFeatures(props.plan.id, { features: selectedFeatures })
    } else {
      const created = await createPlan(basePayload as StorePlanPayload)
      if (created.data.id) {
        await syncFeatures(created.data.id, { features: selectedFeatures })
      }
    }

    emit('saved')
  } catch (error: unknown) {
    const typedError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    const backendErrors = typedError.response?.data?.errors
    if (backendErrors) {
      for (const key of Object.keys(backendErrors)) {
        const messages = backendErrors[key]
        if (messages?.[0]) {
          errors.value[key] = messages[0]
        }
      }
    }
  } finally {
    saving.value = false
  }
}

function close() {
  emit('update:visible', false)
}
</script>

<template>
  <Dialog :visible="visible" @update:visible="close" :header="isEdit ? 'Edit Plan' : 'Create Plan'" :modal="true" :style="{ width: '760px', maxWidth: '95vw' }" :draggable="false" class="plan-form-dialog">
    <div class="form-grid">
      <div class="form-field">
        <label>Name <span class="req">*</span></label>
        <InputText v-model="form.name" size="small" placeholder="Plan name" class="w-full" :class="{ 'p-invalid': errors.name }" />
        <small v-if="errors.name" class="field-error">{{ errors.name }}</small>
      </div>

      <div class="form-field">
        <label>Slug <span class="req">*</span></label>
        <InputText v-model="form.slug" size="small" placeholder="plan-slug" class="w-full" :class="{ 'p-invalid': errors.slug }" />
        <small v-if="errors.slug" class="field-error">{{ errors.slug }}</small>
      </div>

      <div class="form-field form-field-full">
        <label>Description</label>
        <Textarea v-model="form.description" rows="2" autoResize class="w-full" placeholder="Compact plan description" />
      </div>

      <div class="form-field">
        <label>Monthly Price</label>
        <input v-model.number="form.price_monthly" type="number" min="0" step="0.01" class="native-input" />
      </div>

      <div class="form-field">
        <label>Yearly Price</label>
        <input v-model.number="form.price_yearly" type="number" min="0" step="0.01" class="native-input" />
      </div>

      <div class="form-field">
        <label>Monthly Credits</label>
        <input v-model.number="form.credits_monthly" type="number" min="0" class="native-input" />
      </div>

      <div class="form-field">
        <label>Yearly Credits</label>
        <input v-model.number="form.credits_yearly" type="number" min="0" class="native-input" />
      </div>

      <div class="form-field">
        <label>Currency</label>
        <InputText v-model="form.currency" size="small" placeholder="USD" class="w-full" />
      </div>

      <div class="form-field">
        <label>Trial Days</label>
        <input v-model.number="form.trial_days" type="number" min="0" class="native-input" />
      </div>

      <div class="form-field">
        <label>Sort Order</label>
        <input v-model.number="form.sort_order" type="number" min="0" class="native-input" />
      </div>

      <div class="toggles-row form-field-full">
        <label class="toggle-item"><Checkbox v-model="form.is_free" :binary="true" /> <span>Free plan</span></label>
        <label class="toggle-item"><Checkbox v-model="form.is_active" :binary="true" /> <span>Active</span></label>
        <label class="toggle-item"><Checkbox v-model="form.is_featured" :binary="true" /> <span>Featured</span></label>
      </div>
    </div>

    <div class="feature-section">
      <div class="section-head">
        <div>
          <h3>Feature Access</h3>
          <p>Assign enabled features and usage limits per plan.</p>
        </div>
        <Tag :value="`${featureDrafts.filter(feature => feature.selected).length} selected`" severity="info" />
      </div>

      <div v-if="loadingDetail" class="section-loading">
        <i class="pi pi-spin pi-spinner" /> Loading plan details…
      </div>

      <div v-else class="feature-grid">
        <article v-for="draft in featureDrafts" :key="draft.feature_id" class="feature-card" :class="{ selected: draft.selected }">
          <div class="feature-card-head">
            <label class="feature-check">
              <Checkbox v-model="draft.selected" :binary="true" />
              <span class="feature-name">{{ draft.name }}</span>
            </label>
            <Tag :value="featureTypeLabel(draft.type)" class="feature-type" :style="{ background: `${featureTypeColor(draft.type)}18`, color: featureTypeColor(draft.type), borderColor: `${featureTypeColor(draft.type)}20` }" />
          </div>

          <div class="feature-config" v-if="draft.selected">
            <label class="toggle-item compact"><Checkbox v-model="draft.is_enabled" :binary="true" /> <span>Enabled in plan</span></label>
            <div class="feature-config-grid">
              <div>
                <span class="mini-label">Usage Limit</span>
                <input v-model.number="draft.usage_limit" type="number" min="0" class="native-input small" placeholder="Unlimited" />
              </div>
              <div>
                <span class="mini-label">Period</span>
                <Select v-model="draft.limit_period" :options="periodOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
              </div>
              <div>
                <span class="mini-label">Credits / Use</span>
                <input v-model.number="draft.credits_per_use" type="number" min="0" class="native-input small" />
              </div>
            </div>
          </div>
        </article>
      </div>
    </div>

    <template #footer>
      <Button label="Cancel" severity="secondary" text size="small" @click="close" />
      <Button :label="isEdit ? 'Update Plan' : 'Create Plan'" size="small" :loading="saving" @click="save" />
    </template>
  </Dialog>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 14px;
}
@media (max-width: 640px) {
  .form-grid { grid-template-columns: 1fr; }
}
.form-field { display: flex; flex-direction: column; gap: 4px; }
.form-field-full { grid-column: 1 / -1; }
.form-field label,
.mini-label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.req { color: #ef4444; }
.field-error { color: #ef4444; font-size: 0.66rem; }
.w-full { width: 100%; }

.native-input {
  width: 100%;
  min-height: 34px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  color: var(--text-primary);
  font-size: 0.78rem;
  outline: none;
}
.native-input:focus { border-color: var(--active-color); }
.native-input.small { min-height: 32px; font-size: 0.72rem; }

.toggles-row { display: flex; gap: 12px; flex-wrap: wrap; }
.toggle-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.74rem;
  color: var(--text-primary);
}
.toggle-item.compact { font-size: 0.68rem; }

.feature-section {
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--card-border);
}
.section-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 10px;
}
.section-head h3 {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}
.section-head p { font-size: 0.68rem; color: var(--text-muted); margin: 2px 0 0; }
.section-loading { font-size: 0.72rem; color: var(--text-muted); padding: 14px 0; }

.feature-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  max-height: 320px;
  overflow: auto;
}
@media (max-width: 640px) {
  .feature-grid { grid-template-columns: 1fr; }
}
.feature-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
}
.feature-card.selected {
  border-color: color-mix(in srgb, var(--active-color) 35%, var(--card-border));
  background: color-mix(in srgb, var(--card-bg) 86%, var(--hover-bg) 14%);
}
.feature-card-head { display: flex; justify-content: space-between; gap: 8px; align-items: center; }
.feature-check { display: flex; align-items: center; gap: 8px; cursor: pointer; min-width: 0; }
.feature-name { font-size: 0.74rem; font-weight: 600; color: var(--text-primary); }
.feature-type { font-size: 0.56rem !important; padding: 1px 6px !important; }

.feature-config { display: flex; flex-direction: column; gap: 8px; }
.feature-config-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
@media (max-width: 640px) {
  .feature-config-grid { grid-template-columns: 1fr; }
}

:deep(.plan-form-dialog .p-dialog-header) {
  background: var(--card-bg);
  border-color: var(--card-border);
  color: var(--text-primary);
  padding: 12px 16px;
}
:deep(.plan-form-dialog .p-dialog-content) {
  background: var(--card-bg);
  color: var(--text-primary);
  padding: 14px 16px;
}
:deep(.plan-form-dialog .p-dialog-footer) {
  background: var(--card-bg);
  border-color: var(--card-border);
  padding: 8px 16px;
}
</style>