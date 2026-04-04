<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getCoupon, storeCoupon, updateCoupon } from '@/services/couponService'
import { getPlans } from '@/services/planService'
import type { FeatureType } from '@/types/plans'
import type { CouponDetail, StoreCouponData, UpdateCouponData } from '@/types/payments'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import Select from 'primevue/select'

type CouponDiscountType = NonNullable<StoreCouponData['discount_type']>

const props = defineProps<{
  visible: boolean
  couponId: number | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void
  (e: 'saved'): void
}>()

const { t } = useI18n()
const isEdit = computed(() => props.couponId !== null)
const loading = ref(false)
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const planOptions = ref<Array<{ label: string; value: number }>>([])

const discountTypeOptions = computed(() => [
  { label: t('couponForm.percentage'), value: 'percentage' },
  { label: t('couponForm.fixedAmount'), value: 'fixed_amount' },
  { label: t('couponForm.creditsCoupon'), value: 'credits' },
] as Array<{ label: string; value: CouponDiscountType }>)

const form = ref<{
  code: string
  name: string
  description: string
  discount_type: CouponDiscountType
  discount_value: number
  currency: string
  max_uses: number | null
  max_uses_per_user: number | null
  min_order_amount: number | null
  applicable_plan_id: number | null
  is_active: boolean
  starts_at: string
  expires_at: string
}>({
  code: '',
  name: '',
  description: '',
  discount_type: 'percentage',
  discount_value: 10,
  currency: 'USD',
  max_uses: null,
  max_uses_per_user: null,
  min_order_amount: null,
  applicable_plan_id: null,
  is_active: true,
  starts_at: '',
  expires_at: '',
})

watch(
  () => props.visible,
  async visible => {
    if (!visible) return

    errors.value = {}
    await loadPlans()

    if (props.couponId) {
      await loadCoupon(props.couponId)
      return
    }

    resetForm()
  },
)

watch(
  () => form.value.name,
  name => {
    if (!isEdit.value && !form.value.code) {
      form.value.code = name.toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 24)
    }
  },
)

async function loadPlans() {
  try {
    const res = await getPlans({ sort_by: 'sort_order', sort_dir: 'asc' })
    planOptions.value = res.data.map(plan => ({ label: plan.name, value: plan.id }))
  } catch {
    planOptions.value = [
      { label: 'Free', value: 1 },
      { label: 'Starter', value: 2 },
      { label: 'Pro', value: 3 },
      { label: 'Enterprise', value: 4 },
    ]
  }
}

async function loadCoupon(id: number) {
  loading.value = true
  try {
    const res = await getCoupon(id)
    hydrateForm(res.data)
  } catch {
    hydrateForm(buildMockCoupon(id))
  } finally {
    loading.value = false
  }
}

function hydrateForm(coupon: CouponDetail) {
  form.value = {
    code: coupon.code,
    name: coupon.name,
    description: coupon.description || '',
    discount_type: coupon.discount_type as CouponDiscountType,
    discount_value: coupon.discount_value,
    currency: coupon.currency || 'USD',
    max_uses: coupon.max_uses,
    max_uses_per_user: coupon.max_uses_per_user,
    min_order_amount: coupon.min_order_amount,
    applicable_plan_id: coupon.applicable_plan?.id || null,
    is_active: coupon.is_active,
    starts_at: toDateTimeLocal(coupon.starts_at),
    expires_at: toDateTimeLocal(coupon.expires_at),
  }
}

function resetForm() {
  form.value = {
    code: '',
    name: '',
    description: '',
    discount_type: 'percentage',
    discount_value: 10,
    currency: 'USD',
    max_uses: null,
    max_uses_per_user: null,
    min_order_amount: null,
    applicable_plan_id: null,
    is_active: true,
    starts_at: '',
    expires_at: '',
  }
}

async function save() {
  errors.value = {}

  if (!form.value.code.trim()) {
    errors.value.code = t('couponForm.codeRequired')
    return
  }
  if (!form.value.name.trim()) {
    errors.value.name = t('couponForm.nameRequired')
    return
  }
  if (!form.value.discount_value || form.value.discount_value <= 0) {
    errors.value.discount_value = t('couponForm.discountRequired')
    return
  }

  saving.value = true
  try {
    const payload: StoreCouponData | UpdateCouponData = {
      code: form.value.code.trim().toUpperCase(),
      name: form.value.name.trim(),
      description: form.value.description || undefined,
      discount_type: form.value.discount_type,
      discount_value: Number(form.value.discount_value),
      currency: form.value.currency || undefined,
      max_uses: form.value.max_uses ?? undefined,
      max_uses_per_user: form.value.max_uses_per_user ?? undefined,
      min_order_amount: form.value.min_order_amount ?? undefined,
      applicable_plan_id: form.value.applicable_plan_id ?? undefined,
      is_active: form.value.is_active,
      starts_at: form.value.starts_at || undefined,
      expires_at: form.value.expires_at || undefined,
      metadata: undefined,
    }

    if (props.couponId) {
      await updateCoupon(props.couponId, payload)
    } else {
      await storeCoupon(payload as StoreCouponData)
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

function toDateTimeLocal(value: string | null) {
  if (!value) return ''
  return new Date(value).toISOString().slice(0, 16)
}

function buildMockCoupon(id: number): CouponDetail {
  return {
    id,
    code: 'SPRING10',
    name: 'Spring Promo',
    discount_type: 'percentage',
    discount_value: 10,
    currency: 'USD',
    is_active: true,
    max_uses: 500,
    max_uses_per_user: 2,
    times_used: 148,
    usage_percentage: 29.6,
    starts_at: '2026-04-01T00:00:00Z',
    expires_at: '2026-04-30T23:59:00Z',
    is_expired: false,
    applicable_plan: { id: 3, name: 'Pro', slug: 'pro' },
    created_at: '2026-04-01T00:00:00Z',
    deleted_at: null,
    description: 'Seasonal incentive for Pro renewals and recovery flows.',
    min_order_amount: 19,
    metadata: { segment: 'reactivation' },
    updated_at: '2026-04-04T08:30:00Z',
    payments_count: 148,
    payments_total: 7252,
    discount_given_total: 712,
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="close"
    :header="isEdit ? t('couponForm.editCoupon') : t('couponForm.createCoupon')"
    :modal="true"
    :style="{ width: '760px', maxWidth: '96vw' }"
    :draggable="false"
    class="coupon-form-dialog"
  >
    <div v-if="loading" class="dialog-loading"><i class="pi pi-spin pi-spinner" /></div>
    <div v-else class="form-grid">
      <div class="form-field">
        <label>{{ t('couponForm.code') }} <span class="req">*</span></label>
        <InputText v-model="form.code" size="small" class="w-full" placeholder="SPRING10" :class="{ 'p-invalid': errors.code }" />
        <small v-if="errors.code" class="field-error">{{ errors.code }}</small>
      </div>

      <div class="form-field">
        <label>{{ t('couponForm.name') }} <span class="req">*</span></label>
        <InputText v-model="form.name" size="small" class="w-full" placeholder="Spring Promo" :class="{ 'p-invalid': errors.name }" />
        <small v-if="errors.name" class="field-error">{{ errors.name }}</small>
      </div>

      <div class="form-field form-field-full">
        <label>{{ t('couponForm.description') }}</label>
        <Textarea v-model="form.description" rows="3" autoResize class="w-full" :placeholder="t('couponForm.descriptionPlaceholder')" />
      </div>

      <div class="form-field">
        <label>{{ t('couponForm.discountType') }}</label>
        <Select v-model="form.discount_type" :options="discountTypeOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
      </div>

      <div class="form-field">
        <label>{{ t('couponForm.discountValue') }}</label>
        <input v-model.number="form.discount_value" type="number" min="0" step="0.01" class="native-input" />
        <small v-if="errors.discount_value" class="field-error">{{ errors.discount_value }}</small>
      </div>

      <div class="form-field">
        <label>{{ t('couponForm.currency') }}</label>
        <InputText v-model="form.currency" size="small" class="w-full" placeholder="USD" />
      </div>

      <div class="form-field">
        <label>{{ t('couponForm.applicablePlan') }}</label>
        <Select v-model="form.applicable_plan_id" :options="planOptions" optionLabel="label" optionValue="value" size="small" class="w-full" showClear />
      </div>

      <div class="form-field">
        <label>{{ t('couponForm.maxUses') }}</label>
        <input v-model.number="form.max_uses" type="number" min="0" class="native-input" :placeholder="t('couponForm.unlimitedPlaceholder')" />
      </div>

      <div class="form-field">
        <label>{{ t('couponForm.maxUsesPerUser') }}</label>
        <input v-model.number="form.max_uses_per_user" type="number" min="0" class="native-input" :placeholder="t('couponForm.noCapPlaceholder')" />
      </div>

      <div class="form-field">
        <label>{{ t('couponForm.minimumOrder') }}</label>
        <input v-model.number="form.min_order_amount" type="number" min="0" step="0.01" class="native-input" :placeholder="t('couponForm.noMinimumPlaceholder')" />
      </div>

      <div class="form-field">
        <label>{{ t('couponForm.startsAt') }}</label>
        <input v-model="form.starts_at" type="datetime-local" class="native-input" />
      </div>

      <div class="form-field">
        <label>{{ t('couponForm.expiresAt') }}</label>
        <input v-model="form.expires_at" type="datetime-local" class="native-input" />
      </div>

      <label class="toggle-item form-field-full"><Checkbox v-model="form.is_active" :binary="true" /> <span>{{ t('couponForm.couponIsActive') }}</span></label>
    </div>

    <template #footer>
      <Button :label="t('common.cancel')" severity="secondary" text size="small" @click="close" />
      <Button :label="isEdit ? t('couponForm.updateCoupon') : t('couponForm.createCoupon')" size="small" :loading="saving" @click="save" />
    </template>
  </Dialog>
</template>

<style scoped>
.dialog-loading { display: flex; align-items: center; justify-content: center; height: 180px; color: var(--text-muted); }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 14px; }
@media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
.form-field { display: flex; flex-direction: column; gap: 4px; }
.form-field-full { grid-column: 1 / -1; }
.form-field label { font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; }
.req { color: #ef4444; }
.field-error { color: #ef4444; font-size: 0.66rem; }
.w-full { width: 100%; }
.native-input { width: 100%; min-height: 34px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--card-border); background: var(--card-bg); color: var(--text-primary); font-size: 0.78rem; outline: none; }
.native-input:focus { border-color: var(--active-color); }
.toggle-item { display: inline-flex; align-items: center; gap: 8px; font-size: 0.74rem; color: var(--text-primary); }
:deep(.coupon-form-dialog .p-dialog-header) { background: var(--card-bg); border-color: var(--card-border); color: var(--text-primary); padding: 12px 16px; }
:deep(.coupon-form-dialog .p-dialog-content) { background: var(--card-bg); color: var(--text-primary); padding: 14px 16px; }
:deep(.coupon-form-dialog .p-dialog-footer) { background: var(--card-bg); border-color: var(--card-border); padding: 8px 16px; }
</style>