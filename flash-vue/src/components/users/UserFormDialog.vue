<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import Button from 'primevue/button'
import { createUser, updateUser } from '@/services/userService'
import { getRoles } from '@/services/roleService'
import { getPlans } from '@/services/planService'
import type { User, StoreUserPayload, UpdateUserPayload } from '@/types/users'

const props = defineProps<{
  visible: boolean
  user: User | null
}>()
const emit = defineEmits<{
  (e: 'update:visible', val: boolean): void
  (e: 'saved'): void
}>()

const isEdit = computed(() => !!props.user)
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const { t } = useI18n()

// Form fields
const form = ref({
  name: '',
  email: '',
  password: '',
  phone: '',
  status: 'active' as string,
  locale: 'en',
  timezone: 'UTC',
  roles: [] as number[],
  plan_id: null as number | null,
  billing_cycle: 'monthly' as string,
})

// Options
const roleOptions = ref<{ label: string; value: number }[]>([])
const planOptions = ref<{ label: string; value: number }[]>([])
const statusOptions = computed(() => [
  { label: t('userForm.active'), value: 'active' },
  { label: t('userForm.suspended'), value: 'suspended' },
  { label: t('userForm.banned'), value: 'banned' },
  { label: t('userForm.pending'), value: 'pending' },
])
const billingOptions = computed(() => [
  { label: t('userForm.monthly'), value: 'monthly' },
  { label: t('userForm.yearly'), value: 'yearly' },
])

// Load dropdown data
onMounted(async () => {
  try {
    const rolesRes = await getRoles()
    roleOptions.value = (rolesRes.data || []).map((r: any) => ({ label: r.name, value: r.id }))
  } catch {
    roleOptions.value = [
      { label: 'Admin', value: 1 },
      { label: 'User', value: 2 },
      { label: 'Moderator', value: 3 },
    ]
  }
  try {
    const plansRes = await getPlans()
    planOptions.value = (plansRes.data || []).map((p: any) => ({ label: p.name, value: p.id }))
  } catch {
    planOptions.value = [
      { label: 'Free', value: 1 },
      { label: 'Starter', value: 2 },
      { label: 'Pro', value: 3 },
    ]
  }
})

// Reset / populate form when dialog opens
watch(() => props.visible, (open) => {
  if (!open) return
  errors.value = {}
  if (props.user) {
    form.value = {
      name: props.user.name,
      email: props.user.email,
      password: '',
      phone: props.user.phone || '',
      status: props.user.status,
      locale: props.user.locale || 'en',
      timezone: props.user.timezone || 'UTC',
      roles: props.user.roles?.map(r => r.id) || [],
      plan_id: props.user.active_subscription?.plan?.id ?? null,
      billing_cycle: props.user.active_subscription?.billing_cycle || 'monthly',
    }
  } else {
    form.value = { name: '', email: '', password: '', phone: '', status: 'active', locale: 'en', timezone: 'UTC', roles: [], plan_id: null, billing_cycle: 'monthly' }
  }
})

// Save
async function save() {
  errors.value = {}

  // Basic validation
  if (!form.value.name.trim()) { errors.value.name = t('userForm.nameRequired'); return }
  if (!form.value.email.trim()) { errors.value.email = t('userForm.emailRequired'); return }
  if (!isEdit.value && !form.value.password) { errors.value.password = t('userForm.passwordRequired'); return }

  saving.value = true
  try {
    if (isEdit.value && props.user) {
      const payload: UpdateUserPayload = {
        name: form.value.name,
        email: form.value.email,
        status: form.value.status as any,
        phone: form.value.phone || null,
        locale: form.value.locale || null,
        timezone: form.value.timezone || null,
        roles: form.value.roles.length ? form.value.roles : undefined,
      }
      if (form.value.password) payload.password = form.value.password
      await updateUser(props.user.id, payload)
    } else {
      const payload: StoreUserPayload = {
        name: form.value.name,
        email: form.value.email,
        password: form.value.password,
        status: form.value.status as any,
        phone: form.value.phone || null,
        locale: form.value.locale || null,
        timezone: form.value.timezone || null,
        roles: form.value.roles.length ? form.value.roles : undefined,
        plan_id: form.value.plan_id,
        billing_cycle: form.value.billing_cycle as any,
      }
      await createUser(payload)
    }
    emit('saved')
  } catch (err: any) {
    if (err?.response?.data?.errors) {
      const be = err.response.data.errors
      for (const key in be) errors.value[key] = be[key][0]
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
  <Dialog
    :visible="visible"
    @update:visible="close"
    :header="isEdit ? t('userForm.editUser') : t('userForm.createUser')"
    :modal="true"
    :style="{ width: '500px', maxWidth: '95vw' }"
    :draggable="false"
    class="user-form-dialog"
  >
    <div class="form-grid">
      <!-- Name -->
      <div class="form-field">
        <label>{{ t('common.name') }} <span class="req">*</span></label>
        <InputText v-model="form.name" :placeholder="t('userForm.fullName')" size="small" :class="{ 'p-invalid': errors.name }" class="w-full" />
        <small v-if="errors.name" class="field-error">{{ errors.name }}</small>
      </div>

      <!-- Email -->
      <div class="form-field">
        <label>{{ t('common.email') }} <span class="req">*</span></label>
        <InputText v-model="form.email" type="email" :placeholder="t('userForm.emailPlaceholder')" size="small" :class="{ 'p-invalid': errors.email }" class="w-full" />
        <small v-if="errors.email" class="field-error">{{ errors.email }}</small>
      </div>

      <!-- Password -->
      <div class="form-field">
        <label>{{ t('userForm.password') }} <span v-if="!isEdit" class="req">*</span></label>
        <InputText v-model="form.password" type="password" :placeholder="isEdit ? t('userForm.passwordKeep') : t('userForm.passwordMin')" size="small" :class="{ 'p-invalid': errors.password }" class="w-full" />
        <small v-if="errors.password" class="field-error">{{ errors.password }}</small>
      </div>

      <!-- Phone -->
      <div class="form-field">
        <label>{{ t('userForm.phone') }}</label>
        <InputText v-model="form.phone" :placeholder="t('userForm.phonePlaceholder')" size="small" class="w-full" />
      </div>

      <!-- Status -->
      <div class="form-field">
        <label>{{ t('common.status') }}</label>
        <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
      </div>

      <!-- Roles -->
      <div class="form-field">
        <label>{{ t('userForm.roles') }}</label>
        <MultiSelect v-model="form.roles" :options="roleOptions" optionLabel="label" optionValue="value" :placeholder="t('userForm.selectRoles')" size="small" class="w-full" display="chip" />
      </div>

      <!-- Plan (Create only) -->
      <div class="form-field" v-if="!isEdit">
        <label>{{ t('userForm.initialPlan') }}</label>
        <Select v-model="form.plan_id" :options="planOptions" optionLabel="label" optionValue="value" :placeholder="t('userForm.noPlan')" size="small" class="w-full" showClear />
      </div>

      <!-- Billing Cycle (Create only) -->
      <div class="form-field" v-if="!isEdit && form.plan_id">
        <label>{{ t('userForm.billingCycle') }}</label>
        <Select v-model="form.billing_cycle" :options="billingOptions" optionLabel="label" optionValue="value" size="small" class="w-full" />
      </div>
    </div>

    <template #footer>
      <Button :label="t('common.cancel')" severity="secondary" text size="small" @click="close" />
      <Button :label="isEdit ? t('common.save') : t('common.add')" size="small" :loading="saving" @click="save" />
    </template>
  </Dialog>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 14px;
}
@media (max-width: 500px) {
  .form-grid { grid-template-columns: 1fr; }
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
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

:deep(.user-form-dialog .p-dialog-header) {
  background: var(--card-bg);
  border-color: var(--card-border);
  color: var(--text-primary);
  padding: 12px 16px;
}
:deep(.user-form-dialog .p-dialog-content) {
  background: var(--card-bg);
  color: var(--text-primary);
  padding: 14px 16px;
}
:deep(.user-form-dialog .p-dialog-footer) {
  background: var(--card-bg);
  border-color: var(--card-border);
  padding: 8px 16px;
}
</style>
