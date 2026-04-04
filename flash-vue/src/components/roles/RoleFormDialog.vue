<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import { createRole, updateRole } from '@/services/roleService'
import { getPermissionMatrix } from '@/services/roleService'
import type { Role, StoreRolePayload, UpdateRolePayload } from '@/types/roles'
import type { MatrixPermissionRef } from '@/types/roles'

const props = defineProps<{
  visible: boolean
  role: Role | null
}>()
const emit = defineEmits<{
  (e: 'update:visible', val: boolean): void
  (e: 'saved'): void
}>()

const isEdit = ref(false)
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const { t } = useI18n()

// Form
const form = ref({
  name: '',
  slug: '',
  description: '',
  is_default: false,
  permissions: [] as number[],
})

// Available permissions grouped
interface PermGroup { group: string; items: MatrixPermissionRef[] }
const permGroups = ref<PermGroup[]>([])
const permLoading = ref(false)

onMounted(async () => {
  permLoading.value = true
  try {
    const res = await getPermissionMatrix()
    // Group permissions
    const grouped = new Map<string, MatrixPermissionRef[]>()
    for (const p of res.data.permissions) {
      const g = p.group || 'General'
      if (!grouped.has(g)) grouped.set(g, [])
      grouped.get(g)!.push(p)
    }
    permGroups.value = Array.from(grouped.entries()).map(([group, items]) => ({ group, items }))
  } catch {
    permGroups.value = [
      { group: 'Users', items: [
        { id: 1, name: 'View Users', slug: 'users.view', group: 'Users' },
        { id: 2, name: 'Create Users', slug: 'users.create', group: 'Users' },
        { id: 3, name: 'Edit Users', slug: 'users.edit', group: 'Users' },
        { id: 4, name: 'Delete Users', slug: 'users.delete', group: 'Users' },
      ]},
      { group: 'Roles', items: [
        { id: 5, name: 'View Roles', slug: 'roles.view', group: 'Roles' },
        { id: 6, name: 'Manage Roles', slug: 'roles.manage', group: 'Roles' },
      ]},
      { group: 'AI', items: [
        { id: 7, name: 'View AI Requests', slug: 'ai.view', group: 'AI' },
        { id: 8, name: 'Create AI Requests', slug: 'ai.create', group: 'AI' },
        { id: 9, name: 'Delete AI Requests', slug: 'ai.delete', group: 'AI' },
      ]},
      { group: 'Plans', items: [
        { id: 10, name: 'View Plans', slug: 'plans.view', group: 'Plans' },
        { id: 11, name: 'Manage Plans', slug: 'plans.manage', group: 'Plans' },
      ]},
      { group: 'Payments', items: [
        { id: 12, name: 'View Payments', slug: 'payments.view', group: 'Payments' },
        { id: 13, name: 'Process Refunds', slug: 'payments.refund', group: 'Payments' },
      ]},
      { group: 'Settings', items: [
        { id: 14, name: 'View Settings', slug: 'settings.view', group: 'Settings' },
        { id: 15, name: 'Manage Settings', slug: 'settings.manage', group: 'Settings' },
      ]},
    ]
  } finally {
    permLoading.value = false
  }
})

// Reset / populate form
watch(() => props.visible, (open) => {
  if (!open) return
  errors.value = {}
  if (props.role) {
    isEdit.value = true
    form.value = {
      name: props.role.name,
      slug: props.role.slug,
      description: props.role.description || '',
      is_default: props.role.is_default,
      permissions: props.role.permissions?.map(p => p.id) || [],
    }
  } else {
    isEdit.value = false
    form.value = { name: '', slug: '', description: '', is_default: false, permissions: [] }
  }
})

// Auto-generate slug from name
watch(() => form.value.name, (name) => {
  if (!isEdit.value) {
    form.value.slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
  }
})

// Toggle all permissions in a group
function toggleGroup(group: PermGroup) {
  const ids = group.items.map(i => i.id)
  const allSelected = ids.every(id => form.value.permissions.includes(id))
  if (allSelected) {
    form.value.permissions = form.value.permissions.filter(id => !ids.includes(id))
  } else {
    const newPerms = new Set([...form.value.permissions, ...ids])
    form.value.permissions = Array.from(newPerms)
  }
}

function isGroupAllSelected(group: PermGroup) {
  return group.items.every(i => form.value.permissions.includes(i.id))
}

function isGroupPartial(group: PermGroup) {
  const some = group.items.some(i => form.value.permissions.includes(i.id))
  return some && !isGroupAllSelected(group)
}

// Save
async function save() {
  errors.value = {}
  if (!form.value.name.trim()) { errors.value.name = t('roleForm.nameRequired'); return }
  if (!form.value.slug.trim()) { errors.value.slug = t('roleForm.slugRequired'); return }

  saving.value = true
  try {
    if (isEdit.value && props.role) {
      const payload: UpdateRolePayload = {
        name: form.value.name,
        slug: form.value.slug,
        description: form.value.description || null,
        is_default: form.value.is_default,
        permissions: form.value.permissions,
      }
      await updateRole(props.role.id, payload)
    } else {
      const payload: StoreRolePayload = {
        name: form.value.name,
        slug: form.value.slug,
        description: form.value.description || null,
        is_default: form.value.is_default,
        permissions: form.value.permissions,
      }
      await createRole(payload)
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

function close() { emit('update:visible', false) }
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="close"
    :header="isEdit ? t('roleForm.editRole') : t('roleForm.createRole')"
    :modal="true"
    :style="{ width: '560px', maxWidth: '95vw' }"
    :draggable="false"
    class="role-form-dialog"
  >
    <div class="form-section">
      <div class="form-row">
        <div class="form-field">
          <label>{{ t('common.name') }} <span class="req">*</span></label>
          <InputText v-model="form.name" :placeholder="t('roleForm.namePlaceholder')" size="small" :class="{ 'p-invalid': errors.name }" class="w-full" />
          <small v-if="errors.name" class="field-error">{{ errors.name }}</small>
        </div>
        <div class="form-field">
          <label>{{ t('common.name') }} <span class="req">*</span></label>
          <InputText v-model="form.slug" :placeholder="t('roleForm.slugPlaceholder')" size="small" :class="{ 'p-invalid': errors.slug }" class="w-full" />
          <small v-if="errors.slug" class="field-error">{{ errors.slug }}</small>
        </div>
      </div>

      <div class="form-field">
        <label>{{ t('common.description') }}</label>
        <Textarea v-model="form.description" :placeholder="t('roleForm.descriptionPlaceholder')" rows="2" size="small" class="w-full" autoResize />
      </div>

      <div class="form-field-inline">
        <Checkbox v-model="form.is_default" :binary="true" inputId="isDefault" />
        <label for="isDefault" class="inline-label">{{ t('roleForm.defaultRole') }}</label>
      </div>
    </div>

    <!-- Permissions -->
    <div class="perm-section">
      <div class="perm-header">
        <span class="perm-title">{{ t('roleForm.permissions') }}</span>
        <span class="perm-count">{{ form.permissions.length }} {{ t('roleForm.selected', { count: form.permissions.length }) }}</span>
      </div>

      <div v-if="permLoading" class="perm-loading">
        <i class="pi pi-spin pi-spinner" /> {{ t('roleForm.loadingPermissions') }}
      </div>

      <div v-else class="perm-groups">
        <div v-for="group in permGroups" :key="group.group" class="perm-group">
          <div class="pg-header" @click="toggleGroup(group)">
            <Checkbox
              :modelValue="isGroupAllSelected(group)"
              :binary="true"
              :indeterminate="isGroupPartial(group)"
              @click.stop="toggleGroup(group)"
              class="pg-check"
            />
            <span class="pg-name">{{ group.group }}</span>
            <span class="pg-badge">{{ group.items.filter(i => form.permissions.includes(i.id)).length }}/{{ group.items.length }}</span>
          </div>
          <div class="pg-items">
            <label
              v-for="perm in group.items"
              :key="perm.id"
              class="perm-item"
              :class="{ selected: form.permissions.includes(perm.id) }"
            >
              <Checkbox v-model="form.permissions" :value="perm.id" class="pi-check" />
              <span class="pi-name">{{ perm.name }}</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <Button :label="t('common.cancel')" severity="secondary" text size="small" @click="close" />
      <Button :label="isEdit ? t('common.save') : t('common.add')" size="small" :loading="saving" @click="save" />
    </template>
  </Dialog>
</template>

<style scoped>
.form-section { display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
@media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }

.form-field { display: flex; flex-direction: column; gap: 4px; }
.form-field label, .perm-title {
  font-size: 0.7rem; font-weight: 600; color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.04em;
}
.req { color: #ef4444; }
.field-error { color: #ef4444; font-size: 0.66rem; }
.w-full { width: 100%; }

.form-field-inline { display: flex; align-items: center; gap: 8px; }
.inline-label { font-size: 0.72rem; color: var(--text-primary); cursor: pointer; }

/* Permissions */
.perm-section { border-top: 1px solid var(--card-border); padding-top: 10px; }
.perm-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.perm-count { font-size: 0.66rem; color: var(--text-muted); }
.perm-loading { font-size: 0.72rem; color: var(--text-muted); padding: 12px 0; }

.perm-groups {
  display: flex; flex-direction: column; gap: 6px;
  max-height: 300px; overflow-y: auto;
  scrollbar-width: thin;
}

.perm-group {
  border: 1px solid var(--card-border); border-radius: 8px;
  overflow: hidden;
}
.pg-header {
  display: flex; align-items: center; gap: 8px;
  padding: 7px 10px; background: var(--hover-bg);
  cursor: pointer; user-select: none;
}
.pg-check { flex-shrink: 0; }
.pg-name { font-size: 0.72rem; font-weight: 700; color: var(--text-primary); flex: 1; }
.pg-badge {
  font-size: 0.58rem; font-weight: 600; color: var(--text-muted);
  background: var(--card-bg); border-radius: 10px; padding: 1px 7px;
}

.pg-items {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 0;
}
@media (max-width: 480px) {
  .pg-items { grid-template-columns: 1fr; }
}

.perm-item {
  display: flex; align-items: center; gap: 6px;
  padding: 5px 10px; cursor: pointer;
  font-size: 0.68rem; color: var(--text-primary);
  border-bottom: 1px solid var(--card-border);
  transition: background 0.1s;
}
.perm-item:hover { background: var(--hover-bg); }
.perm-item.selected { background: color-mix(in srgb, var(--active-color) 8%, transparent); }
.perm-item:last-child { border-bottom: none; }
.pi-check { flex-shrink: 0; }
.pi-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Dialog theme override */
:deep(.role-form-dialog .p-dialog-header) {
  background: var(--card-bg); border-color: var(--card-border);
  color: var(--text-primary); padding: 12px 16px;
}
:deep(.role-form-dialog .p-dialog-content) {
  background: var(--card-bg); color: var(--text-primary); padding: 14px 16px;
}
:deep(.role-form-dialog .p-dialog-footer) {
  background: var(--card-bg); border-color: var(--card-border); padding: 8px 16px;
}
</style>
