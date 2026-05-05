<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { createFeature, updateFeature } from '@/services/featureService'
import type { Feature, FeatureType, StoreFeaturePayload, UpdateFeaturePayload } from '@/types/plans'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import Select from 'primevue/select'

const props = defineProps<{
  visible: boolean
  feature: Feature | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', value: boolean): void
  (e: 'saved'): void
}>()

const { t } = useI18n()
const isEdit = computed(() => !!props.feature)
const saving = ref(false)
const errors = ref<Record<string, string>>({})

const typeOptions = computed(() => [
  { label: t('featureForm.textToImage'), value: 'text_to_image' },
  { label: t('featureForm.imageToImage'), value: 'image_to_image' },
  { label: t('featureForm.inpainting'), value: 'inpainting' },
  { label: t('featureForm.upscale'), value: 'upscale' },
  { label: t('featureForm.videoGeneration'), value: 'video_generation' },
  { label: t('featureForm.other'), value: 'other' },
] as Array<{ label: string; value: FeatureType }>)

const form = ref<{
  name: string
  slug: string
  description: string
  type: FeatureType
  is_active: boolean
  sort_order: number
}>({
  name: '',
  slug: '',
  description: '',
  type: 'other',
  is_active: true,
  sort_order: 1,
})

watch(
  () => props.visible,
  visible => {
    if (!visible) return

    errors.value = {}
    if (props.feature) {
      form.value = {
        name: props.feature.name,
        slug: props.feature.slug,
        description: props.feature.description || '',
        type: props.feature.type as FeatureType,
        is_active: props.feature.is_active,
        sort_order: props.feature.sort_order,
      }
      return
    }

    form.value = {
      name: '',
      slug: '',
      description: '',
      type: 'other',
      is_active: true,
      sort_order: 1,
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

async function save() {
  errors.value = {}
  if (!form.value.name.trim()) {
    errors.value.name = t('featureForm.nameRequired')
    return
  }
  if (!form.value.slug.trim()) {
    errors.value.slug = t('featureForm.slugRequired')
    return
  }

  saving.value = true
  try {
    const payload: StoreFeaturePayload | UpdateFeaturePayload = {
      name: form.value.name,
      slug: form.value.slug,
      description: form.value.description || null,
      type: form.value.type,
      is_active: form.value.is_active,
      sort_order: Number(form.value.sort_order),
      metadata: null,
    }

    if (props.feature) {
      await updateFeature(props.feature.id, payload)
    } else {
      await createFeature(payload as StoreFeaturePayload)
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
  <Dialog :visible="visible" @update:visible="close" :header="isEdit ? t('featureForm.editFeature') : t('featureForm.createFeature')" :modal="true" :style="{ width: '520px', maxWidth: '95vw' }" :draggable="false" class="feature-form-dialog">
    <div class="form-grid">
      <div class="form-field">
        <label>{{ t('common.name') }} <span class="req">*</span></label>
        <InputText v-model="form.name" size="small" :placeholder="t('featureForm.featureName')" class="w-full" :class="{ 'p-invalid': errors.name }" />
        <small v-if="errors.name" class="field-error">{{ errors.name }}</small>
      </div>

      <div class="form-field">
        <label>{{ t('featureForm.slug') }} <span class="req">*</span></label>
        <InputText v-model="form.slug" size="small" :placeholder="t('featureForm.featureSlug')" class="w-full" :class="{ 'p-invalid': errors.slug }" />
        <small v-if="errors.slug" class="field-error">{{ errors.slug }}</small>
      </div>

      <div class="form-field form-field-full">
        <label>{{ t('featureForm.description') }}</label>
        <Textarea v-model="form.description" rows="3" autoResize class="w-full" :placeholder="t('featureForm.descriptionPlaceholder')" />
      </div>

      <div class="form-field">
        <label>{{ t('featureForm.type') }}</label>
        <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
      </div>

      <div class="form-field">
        <label>{{ t('featureForm.sortOrder') }}</label>
        <input v-model.number="form.sort_order" type="number" min="0" class="native-input" />
      </div>

      <label class="toggle-item form-field-full"><Checkbox v-model="form.is_active" :binary="true" /> <span>{{ t('featureForm.featureIsActive') }}</span></label>
    </div>

    <template #footer>
      <Button :label="t('common.cancel')" severity="secondary" text size="small" @click="close" />
      <Button :label="isEdit ? t('featureForm.updateFeature') : t('featureForm.createFeature')" size="small" :loading="saving" @click="save" />
    </template>
  </Dialog>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 14px;
}
@media (max-width: 560px) {
  .form-grid { grid-template-columns: 1fr; }
}
.form-field { display: flex; flex-direction: column; gap: 4px; }
.form-field-full { grid-column: 1 / -1; }
.form-field label {
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
.toggle-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.74rem;
  color: var(--text-primary);
}

:deep(.feature-form-dialog .p-dialog-header) {
  background: var(--card-bg);
  border-color: var(--card-border);
  color: var(--text-primary);
  padding: 12px 16px;
}
:deep(.feature-form-dialog .p-dialog-content) {
  background: var(--card-bg);
  color: var(--text-primary);
  padding: 14px 16px;
}
:deep(.feature-form-dialog .p-dialog-footer) {
  background: var(--card-bg);
  border-color: var(--card-border);
  padding: 8px 16px;
}
</style>