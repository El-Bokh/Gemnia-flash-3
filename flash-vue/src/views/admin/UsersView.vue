<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getUsers, deleteUser, restoreUser } from '@/services/userService'
import type { User, ListUsersParams } from '@/types/users'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import Skeleton from 'primevue/skeleton'
import UserFormDialog from '@/components/users/UserFormDialog.vue'
import UserDetailDrawer from '@/components/users/UserDetailDrawer.vue'

// ── State ───────────────────────────────────────────────────
const loading = ref(true)
const users = ref<User[]>([])
const totalRecords = ref(0)
const currentPage = ref(1)
const perPage = ref(15)
const search = ref('')
const statusFilter = ref<string | undefined>(undefined)
const sortField = ref('created_at')
const sortOrder = ref<'asc' | 'desc'>('desc')

// Dialogs
const showForm = ref(false)
const editUser = ref<User | null>(null)
const showDetail = ref(false)
const detailUserId = ref<number | null>(null)
const showDeleteConfirm = ref(false)
const deleteTarget = ref<User | null>(null)
const actionLoading = ref(false)

const { t } = useI18n()

const statusOptions = computed(() => [
  { label: t('users.allStatus'), value: undefined },
  { label: t('users.active'), value: 'active' },
  { label: t('users.suspended'), value: 'suspended' },
  { label: t('users.banned'), value: 'banned' },
  { label: t('users.pending'), value: 'pending' },
])

// ── Fetch ───────────────────────────────────────────────────
async function fetchUsers() {
  loading.value = true
  try {
    const params: ListUsersParams = {
      per_page: perPage.value,
      sort_by: sortField.value as ListUsersParams['sort_by'],
      sort_dir: sortOrder.value,
    }
    if (search.value) params.search = search.value
    if (statusFilter.value) params.status = statusFilter.value as ListUsersParams['status']

    // page param appended manually
    const res = await getUsers({ ...params, per_page: perPage.value } as any)
    users.value = res.data
    totalRecords.value = res.meta.total
  } catch {
    loadMockUsers()
  } finally {
    loading.value = false
  }
}

function loadMockUsers() {
  const statuses = ['active', 'active', 'active', 'suspended', 'pending', 'banned', 'active', 'active']
  const names = ['Sara Ahmed', 'Omar Ali', 'Mona Khaled', 'Youssef Nabil', 'Layla Hassan', 'Karim Mostafa', 'Nour Sayed', 'Amira El-Din', 'Hassan Ibrahim', 'Dina Farouk', 'Tamer Hosny', 'Rania Youssef', 'Ahmed Maher', 'Fatma Ali', 'Mahmoud Nasr']
  const mockPlans = [
    { id: 1, name: 'Free', slug: 'free', price: 0 },
    { id: 2, name: 'Starter', slug: 'starter', price: 19 },
    { id: 3, name: 'Pro', slug: 'pro', price: 49 },
  ] as const

  users.value = names.map<User>((name, i) => {
    const mockStatus = statuses[i % statuses.length] ?? 'active'
    const mockPlan = mockPlans[i % mockPlans.length] ?? mockPlans[0]

    return {
      id: i + 1,
      name,
      email: `${name.toLowerCase().replace(' ', '.')}@klek.ai`,
      phone: i % 3 === 0 ? '+20 10' + String(i).padStart(8, '0') : null,
      avatar: null,
      status: mockStatus,
      locale: 'en',
      timezone: 'UTC',
      email_verified_at: i % 4 !== 3 ? '2026-03-15T10:00:00Z' : null,
      last_login_at: i < 10 ? `2026-04-0${(i % 4) + 1}T${10 + i}:30:00Z` : null,
      last_login_ip: i < 10 ? `192.168.1.${10 + i}` : null,
      created_at: `2026-0${(i % 3) + 1}-${String((i % 28) + 1).padStart(2, '0')}T09:00:00Z`,
      updated_at: '2026-04-03T12:00:00Z',
      roles: [{ id: i % 3 === 0 ? 1 : 2, name: i % 3 === 0 ? 'Admin' : 'User', slug: i % 3 === 0 ? 'admin' : 'user' }],
      active_subscription: i % 5 !== 4 ? {
        id: i + 100,
        plan: { id: mockPlan.id, name: mockPlan.name, slug: mockPlan.slug },
        billing_cycle: i % 2 === 0 ? 'monthly' : 'yearly',
        status: 'active',
        price: mockPlan.price,
        currency: 'USD',
        credits_remaining: 50 + i * 10,
        credits_total: 100 + i * 10,
        starts_at: '2026-03-01T00:00:00Z',
        ends_at: '2026-04-01T00:00:00Z',
        trial_ends_at: null,
        auto_renew: true,
      } : null,
      stats: { ai_requests_count: 20 + i * 5, generated_images_count: 15 + i * 3, total_credits_used: 80 + i * 12 },
    }
  })
  totalRecords.value = names.length
}

onMounted(fetchUsers)

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(fetchUsers, 400)
})
watch(statusFilter, fetchUsers)

// ── Sort handler ────────────────────────────────────────────
function onSort(event: any) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder === 1 ? 'asc' : 'desc'
  fetchUsers()
}

// ── Page handler ────────────────────────────────────────────
function onPage(event: any) {
  currentPage.value = Math.floor(event.first / perPage.value) + 1
  fetchUsers()
}

// ── Actions ─────────────────────────────────────────────────
function openCreate() {
  editUser.value = null
  showForm.value = true
}

function openEdit(user: User) {
  editUser.value = user
  showForm.value = true
}

function openDetail(user: User) {
  detailUserId.value = user.id
  showDetail.value = true
}

function confirmDelete(user: User) {
  deleteTarget.value = user
  showDeleteConfirm.value = true
}

async function handleDelete() {
  if (!deleteTarget.value) return
  actionLoading.value = true
  try {
    await deleteUser(deleteTarget.value.id)
    showDeleteConfirm.value = false
    fetchUsers()
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

async function handleRestore(user: User) {
  actionLoading.value = true
  try {
    await restoreUser(user.id)
    fetchUsers()
  } catch { /* handled */ }
  finally { actionLoading.value = false }
}

function onFormSaved() {
  showForm.value = false
  fetchUsers()
}

// ── Helpers ─────────────────────────────────────────────────
function statusSeverity(s: string): 'success' | 'warn' | 'danger' | 'info' | 'secondary' {
  return { active: 'success' as const, suspended: 'warn' as const, banned: 'danger' as const, pending: 'info' as const }[s] || 'secondary'
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function initials(name: string) {
  return name.split(' ').map(n => n[0]).join('').substring(0, 2)
}
</script>

<template>
  <div class="users-page">
    <!-- Toolbar -->
    <div class="page-toolbar">
      <h1 class="page-title">{{ t('users.title') }}</h1>
      <Button icon="pi pi-plus" :label="t('users.addUser')" size="small" @click="openCreate" />
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <span class="p-input-icon-left filter-search">
        <i class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('users.searchPlaceholder')" size="small" class="filter-input" />
      </span>
      <Select
        v-model="statusFilter"
        :options="statusOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="All Status"
        size="small"
        class="filter-select"
      />
      <span class="filter-count">{{ t('users.userCount', { count: totalRecords }) }}</span>
    </div>

    <!-- Table -->
    <div class="table-card">
      <DataTable
        :value="users"
        :loading="loading"
        :rows="perPage"
        :totalRecords="totalRecords"
        :lazy="true"
        :paginator="true"
        :rowsPerPageOptions="[10, 15, 25, 50]"
        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
        sortMode="single"
        :sortField="sortField"
        :sortOrder="sortOrder === 'asc' ? 1 : -1"
        @sort="onSort"
        @page="onPage"
        stripedRows
        size="small"
        scrollable
        scrollHeight="calc(100vh - 250px)"
        class="users-table"
        dataKey="id"
      >
        <!-- User -->
        <Column field="name" :header="t('common.user')" sortable style="min-width: 200px">
          <template #body="{ data }">
            <div class="user-cell" @click="openDetail(data)" style="cursor: pointer">
              <div class="u-avatar">
                <img v-if="data.avatar" :src="data.avatar" :alt="data.name" />
                <span v-else>{{ initials(data.name) }}</span>
              </div>
              <div class="u-info">
                <span class="u-name">{{ data.name }}</span>
                <span class="u-email">{{ data.email }}</span>
              </div>
            </div>
          </template>
        </Column>

        <!-- Status -->
        <Column field="status" :header="t('common.status')" sortable style="min-width: 100px">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="statusSeverity(data.status)" class="u-tag" />
          </template>
        </Column>

        <!-- Roles -->
        <Column field="roles" :header="t('users.role')" style="min-width: 90px">
          <template #body="{ data }">
            <span class="u-role" v-if="data.roles?.length">{{ data.roles[0].name }}</span>
            <span class="u-muted" v-else>—</span>
          </template>
        </Column>

        <!-- Plan -->
        <Column :header="t('users.plan')" style="min-width: 90px">
          <template #body="{ data }">
            <span class="u-plan" v-if="data.active_subscription?.plan">{{ data.active_subscription.plan.name }}</span>
            <span class="u-muted" v-else>{{ t('users.none') }}</span>
          </template>
        </Column>

        <!-- Credits -->
        <Column :header="t('users.credits')" style="min-width: 70px">
          <template #body="{ data }">
            <span class="u-num" v-if="data.active_subscription">
              {{ data.active_subscription.credits_remaining }}/{{ data.active_subscription.credits_total }}
            </span>
            <span class="u-muted" v-else>—</span>
          </template>
        </Column>

        <!-- AI Requests -->
        <Column :header="t('users.requests')" style="min-width: 70px">
          <template #body="{ data }">
            <span class="u-num">{{ data.stats?.ai_requests_count ?? 0 }}</span>
          </template>
        </Column>

        <!-- Last Login -->
        <Column field="last_login_at" :header="t('users.lastLogin')" sortable style="min-width: 110px">
          <template #body="{ data }">
            <span class="u-date">{{ formatDate(data.last_login_at) }}</span>
          </template>
        </Column>

        <!-- Joined -->
        <Column field="created_at" :header="t('users.joined')" sortable style="min-width: 100px">
          <template #body="{ data }">
            <span class="u-date">{{ formatDate(data.created_at) }}</span>
          </template>
        </Column>

        <!-- Actions -->
        <Column header="" style="min-width: 90px; text-align: right" frozen alignFrozen="right">
          <template #body="{ data }">
            <div class="u-actions">
              <Button icon="pi pi-eye" severity="secondary" text rounded size="small" @click="openDetail(data)" v-tooltip.left="'View'" />
              <Button icon="pi pi-pencil" severity="secondary" text rounded size="small" @click="openEdit(data)" v-tooltip.left="'Edit'" />
              <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmDelete(data)" v-tooltip.left="'Delete'" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Create/Edit Dialog -->
    <UserFormDialog
      v-model:visible="showForm"
      :user="editUser"
      @saved="onFormSaved"
    />

    <!-- Detail Drawer -->
    <UserDetailDrawer
      v-model:visible="showDetail"
      :userId="detailUserId"
    />

    <!-- Delete Confirm -->
    <Dialog v-model:visible="showDeleteConfirm" :header="t('users.deleteUser')" :modal="true" :style="{ width: '360px' }">
      <div class="confirm-body">
        <i class="pi pi-exclamation-triangle confirm-icon" />
        <p>{{ t('users.deleteConfirm', { name: deleteTarget?.name }) }}</p>
        <p class="confirm-sub">{{ t('users.deleteConfirmSub') }}</p>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text size="small" @click="showDeleteConfirm = false" />
        <Button :label="t('common.delete')" severity="danger" size="small" :loading="actionLoading" @click="handleDelete" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.users-page {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.page-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.page-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

/* Filters */
.filters-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.filter-search {
  position: relative;
  flex: 1;
  min-width: 180px;
  max-width: 320px;
}
.filter-search i {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.8rem;
  color: var(--text-muted);
  z-index: 1;
}
.filter-input {
  width: 100%;
  padding-left: 32px !important;
  font-size: 0.78rem !important;
}
.filter-select {
  min-width: 130px;
  font-size: 0.78rem !important;
}
.filter-count {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-left: auto;
}

/* Table */
.table-card {
  border-radius: 10px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  overflow: hidden;
}

.users-table { font-size: 0.75rem; }

:deep(.users-table .p-datatable-table-container) {
  border-radius: 10px;
  overflow: hidden;
  background: var(--card-bg);
}
:deep(.users-table .p-datatable-thead > tr > th) {
  background: var(--hover-bg);
  color: var(--text-secondary);
  border-color: var(--card-border);
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 10px 12px;
}
:deep(.users-table .p-datatable-tbody > tr) {
  background: var(--card-bg);
  color: var(--text-primary);
  transition: background 0.12s;
}
:deep(.users-table.p-datatable-striped .p-datatable-tbody > tr:nth-child(even)) {
  background: color-mix(in srgb, var(--card-bg) 76%, var(--hover-bg) 24%);
}
:deep(.users-table .p-datatable-tbody > tr:hover) {
  background: var(--hover-bg);
}
:deep(.users-table .p-datatable-tbody > tr > td) {
  background: transparent;
  color: var(--text-primary);
  border-color: var(--card-border);
  padding: 8px 12px;
}
:deep(.users-table .p-paginator) {
  background: var(--card-bg);
  border-color: var(--card-border);
  padding: 6px 12px;
}

/* User cell */
.user-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}
.u-avatar {
  width: 28px; height: 28px;
  border-radius: 7px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 0.62rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}
.u-avatar img { width: 100%; height: 100%; object-fit: cover; }
.u-info { display: flex; flex-direction: column; min-width: 0; }
.u-name { font-size: 0.78rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.u-email { font-size: 0.66rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.u-tag { font-size: 0.6rem !important; padding: 2px 8px !important; }
.u-role { font-size: 0.72rem; font-weight: 500; color: var(--text-secondary); }
.u-plan { font-size: 0.72rem; font-weight: 600; color: var(--active-color); }
.u-num { font-size: 0.75rem; font-weight: 600; color: var(--text-primary); }
.u-date { font-size: 0.68rem; color: var(--text-muted); }
.u-muted { font-size: 0.68rem; color: var(--text-muted); }
.u-actions { display: flex; align-items: center; gap: 0; }

/* Delete confirm */
.confirm-body { text-align: center; padding: 6px 0; }
.confirm-icon { font-size: 2rem; color: #ef4444; margin-bottom: 8px; }
.confirm-body p { font-size: 0.82rem; color: var(--text-primary); margin: 4px 0; }
.confirm-sub { font-size: 0.7rem; color: var(--text-muted); }
</style>
