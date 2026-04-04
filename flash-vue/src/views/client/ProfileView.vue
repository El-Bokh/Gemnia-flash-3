<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { getMe, type MeResponse } from '@/services/authService'
import {
  updateProfile,
  uploadProfileAvatar,
  changePassword as changePasswordApi,
  type ProfileData,
} from '@/services/profileService'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Message from 'primevue/message'

const { t } = useI18n()
const auth = useAuthStore()

const activeTab = ref('profile')
const saving = ref(false)
const pwSaving = ref(false)
const successMsg = ref('')
const errorMsg = ref('')
const pwSuccess = ref('')
const pwError = ref('')
const profileLoading = ref(true)
const avatarInputRef = ref<HTMLInputElement | null>(null)
const selectedAvatarFile = ref<File | null>(null)
const avatarPreviewUrl = ref<string | null>(auth.user.avatar)

// ── Profile form — populated from backend ──
const profileForm = ref({
  name: '',
  email: '',
  phone: '',
  timezone: '',
  locale: '',
})

// ── Password form ──
const pwForm = ref({
  current: '',
  password: '',
  confirm: '',
})
const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)

const pwMatch = computed(() => pwForm.value.password === pwForm.value.confirm)
const pwValid = computed(() =>
  pwForm.value.current.length >= 1 &&
  pwForm.value.password.length >= 8 &&
  pwMatch.value,
)

const avatarInitials = computed(() => {
  const parts = profileForm.value.name.trim().split(/\s+/).filter(Boolean)
  const initials = parts.map(part => part[0]).join('').slice(0, 2).toUpperCase()

  return initials || 'U'
})

function syncAuthUser(profile: ProfileData | MeResponse) {
  auth.setUser({
    id: profile.id,
    name: profile.name,
    email: profile.email,
    avatar: profile.avatar,
    roles: profile.roles,
  })
}

function applyProfileData(profile: ProfileData | MeResponse) {
  profileForm.value = {
    name: profile.name ?? '',
    email: profile.email ?? '',
    phone: profile.phone ?? '',
    timezone: profile.timezone ?? '',
    locale: profile.locale ?? '',
  }

  if (!selectedAvatarFile.value) {
    avatarPreviewUrl.value = profile.avatar ?? null
  }
}

function revokeAvatarPreview() {
  if (avatarPreviewUrl.value?.startsWith('blob:')) {
    URL.revokeObjectURL(avatarPreviewUrl.value)
  }
}

function clearAvatarSelection() {
  selectedAvatarFile.value = null
  if (avatarInputRef.value) {
    avatarInputRef.value.value = ''
  }
}

function extractFirstError(err: unknown, fallback: string) {
  const responseData = (err as any)?.response?.data
  const firstValidationError = Object.values(responseData?.errors ?? {}).flat()[0]

  if (typeof firstValidationError === 'string') {
    return firstValidationError
  }

  if (typeof responseData?.message === 'string' && responseData.message.trim() !== '') {
    return responseData.message
  }

  return fallback
}

function openAvatarPicker() {
  avatarInputRef.value?.click()
}

function handleAvatarSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) {
    return
  }

  if (!file.type.startsWith('image/')) {
    errorMsg.value = t('profile.avatarInvalid')
    clearAvatarSelection()
    return
  }

  if (file.size > 4 * 1024 * 1024) {
    errorMsg.value = t('profile.avatarTooLarge')
    clearAvatarSelection()
    return
  }

  errorMsg.value = ''
  successMsg.value = ''
  revokeAvatarPreview()
  selectedAvatarFile.value = file
  avatarPreviewUrl.value = URL.createObjectURL(file)
}

// ── Load real user data on mount ──
onMounted(async () => {
  try {
    const res = await getMe()
    if (res.success && res.data) {
      syncAuthUser(res.data)
      applyProfileData(res.data)
    }
  } catch {
    // Fallback to auth store data
    profileForm.value.name = auth.user.name ?? ''
    profileForm.value.email = auth.user.email ?? ''
    avatarPreviewUrl.value = auth.user.avatar
  } finally {
    profileLoading.value = false
  }
})

onBeforeUnmount(() => {
  revokeAvatarPreview()
})

// ── Handlers ──
async function saveProfile() {
  saving.value = true
  successMsg.value = ''
  errorMsg.value = ''

  try {
    const profileRes = await updateProfile({
      name: profileForm.value.name,
      email: profileForm.value.email,
      phone: profileForm.value.phone || undefined,
      locale: profileForm.value.locale || undefined,
      timezone: profileForm.value.timezone || undefined,
    })

    let savedProfile = profileRes.data

    if (selectedAvatarFile.value) {
      const avatarRes = await uploadProfileAvatar(selectedAvatarFile.value)
      savedProfile = avatarRes.data
      revokeAvatarPreview()
      clearAvatarSelection()
    }

    if (profileRes.success && savedProfile) {
      syncAuthUser(savedProfile)
      applyProfileData(savedProfile)
      successMsg.value = t('profile.profileSaved')
    }
  } catch (err: unknown) {
    errorMsg.value = extractFirstError(err, t('profile.saveFailed'))
  } finally {
    saving.value = false
  }
}

async function changePassword() {
  if (!pwValid.value) return
  pwSaving.value = true
  pwSuccess.value = ''
  pwError.value = ''
  try {
    const res = await changePasswordApi({
      current_password: pwForm.value.current,
      password: pwForm.value.password,
      password_confirmation: pwForm.value.confirm,
    })
    if (res.success) {
      pwSuccess.value = t('profile.passwordChanged')
      pwForm.value = { current: '', password: '', confirm: '' }
    }
  } catch (err: unknown) {
    pwError.value = extractFirstError(err, t('profile.passwordFailed'))
  } finally {
    pwSaving.value = false
  }
}
</script>

<template>
  <div class="profile-page">
    <div class="page-header">
      <h1 class="page-title">{{ t('profile.pageTitle') }}</h1>
      <p class="page-sub">{{ t('profile.pageSub') }}</p>
    </div>

    <Tabs v-model:value="activeTab" class="profile-tabs">
      <TabList>
        <Tab value="profile"><i class="pi pi-user" /> <span>{{ t('profile.tabProfile') }}</span></Tab>
        <Tab value="security"><i class="pi pi-shield" /> <span>{{ t('profile.tabSecurity') }}</span></Tab>
        <Tab value="subscription"><i class="pi pi-credit-card" /> <span>{{ t('profile.tabSubscription') }}</span></Tab>
        <Tab value="usage"><i class="pi pi-chart-bar" /> <span>{{ t('profile.tabUsage') }}</span></Tab>
      </TabList>

      <TabPanels>
        <!-- ═══ PROFILE TAB ═══ -->
        <TabPanel value="profile">
          <div class="tab-content">
            <section class="section-card">
              <div class="section-head">
                <h2 class="section-title">{{ t('profile.personalInfo') }}</h2>
                <p class="section-desc">{{ t('profile.personalInfoDesc') }}</p>
              </div>

              <Message v-if="successMsg" severity="success" :closable="true" @close="successMsg = ''">{{ successMsg }}</Message>
              <Message v-if="errorMsg" severity="error" :closable="true" @close="errorMsg = ''">{{ errorMsg }}</Message>

              <form class="profile-form" @submit.prevent="saveProfile">
                <!-- Avatar -->
                <div class="avatar-row">
                  <div class="avatar-circle">
                    <img v-if="avatarPreviewUrl" :src="avatarPreviewUrl" :alt="profileForm.name || 'Avatar'" />
                    <span v-else class="avatar-initials">{{ avatarInitials }}</span>
                  </div>
                  <div class="avatar-info">
                    <span class="avatar-name">{{ profileForm.name }}</span>
                    <span class="avatar-email">{{ profileForm.email }}</span>
                    <span v-if="selectedAvatarFile" class="avatar-helper">{{ selectedAvatarFile.name }}</span>
                  </div>
                  <input
                    ref="avatarInputRef"
                    type="file"
                    accept="image/*"
                    class="avatar-input-hidden"
                    @change="handleAvatarSelected"
                  />
                  <Button
                    type="button"
                    :label="t('profile.changeAvatar')"
                    icon="pi pi-camera"
                    severity="secondary"
                    outlined
                    size="small"
                    class="avatar-btn"
                    :disabled="saving"
                    @click="openAvatarPicker"
                  />
                </div>

                <div class="form-grid">
                  <div class="form-field">
                    <label class="field-label">{{ t('profile.fullName') }}</label>
                    <InputText v-model="profileForm.name" size="small" class="field-input" :disabled="profileLoading || saving" />
                  </div>
                  <div class="form-field">
                    <label class="field-label">{{ t('profile.emailAddress') }}</label>
                    <InputText v-model="profileForm.email" type="email" size="small" class="field-input" :disabled="profileLoading || saving" />
                  </div>
                  <div class="form-field">
                    <label class="field-label">{{ t('profile.phone') }}</label>
                    <InputText v-model="profileForm.phone" size="small" class="field-input" :disabled="profileLoading || saving" />
                  </div>
                  <div class="form-field">
                    <label class="field-label">{{ t('profile.timezone') }}</label>
                    <InputText v-model="profileForm.timezone" size="small" class="field-input" disabled />
                  </div>
                </div>

                <div class="form-actions">
                  <Button type="submit" :label="t('profile.saveChanges')" icon="pi pi-check" :loading="saving" :disabled="profileLoading" size="small" />
                </div>
              </form>
            </section>
          </div>
        </TabPanel>

        <!-- ═══ SECURITY TAB ═══ -->
        <TabPanel value="security">
          <div class="tab-content">
            <section class="section-card">
              <div class="section-head">
                <h2 class="section-title">{{ t('profile.changePassword') }}</h2>
                <p class="section-desc">{{ t('profile.changePasswordDesc') }}</p>
              </div>

              <Message v-if="pwSuccess" severity="success" :closable="true" @close="pwSuccess = ''">{{ pwSuccess }}</Message>
              <Message v-if="pwError" severity="error" :closable="true" @close="pwError = ''">{{ pwError }}</Message>

              <form class="pw-form" @submit.prevent="changePassword">
                <div class="form-field">
                  <label class="field-label">{{ t('profile.currentPassword') }}</label>
                  <span class="field-input-wrap has-toggle">
                    <i class="pi pi-lock" />
                    <InputText
                      v-model="pwForm.current"
                      :type="showCurrent ? 'text' : 'password'"
                      :placeholder="t('profile.currentPasswordPlaceholder')"
                      autocomplete="current-password"
                      size="small"
                      class="field-input with-icon"
                    />
                    <i :class="showCurrent ? 'pi pi-eye-slash' : 'pi pi-eye'" class="toggle-pw" @click="showCurrent = !showCurrent" />
                  </span>
                </div>

                <div class="form-field">
                  <label class="field-label">{{ t('profile.newPassword') }}</label>
                  <span class="field-input-wrap has-toggle">
                    <i class="pi pi-lock" />
                    <InputText
                      v-model="pwForm.password"
                      :type="showNew ? 'text' : 'password'"
                      :placeholder="t('profile.newPasswordPlaceholder')"
                      autocomplete="new-password"
                      size="small"
                      class="field-input with-icon"
                    />
                    <i :class="showNew ? 'pi pi-eye-slash' : 'pi pi-eye'" class="toggle-pw" @click="showNew = !showNew" />
                  </span>
                  <small v-if="pwForm.password.length > 0 && pwForm.password.length < 8" class="field-hint warn">{{ t('profile.minChars') }}</small>
                </div>

                <div class="form-field">
                  <label class="field-label">{{ t('profile.confirmNewPassword') }}</label>
                  <span class="field-input-wrap has-toggle">
                    <i class="pi pi-lock" />
                    <InputText
                      v-model="pwForm.confirm"
                      :type="showConfirm ? 'text' : 'password'"
                      :placeholder="t('profile.confirmNewPlaceholder')"
                      autocomplete="new-password"
                      size="small"
                      class="field-input with-icon"
                    />
                    <i :class="showConfirm ? 'pi pi-eye-slash' : 'pi pi-eye'" class="toggle-pw" @click="showConfirm = !showConfirm" />
                  </span>
                  <small v-if="pwForm.confirm.length > 0 && !pwMatch" class="field-hint warn">{{ t('profile.passwordMismatch') }}</small>
                </div>

                <div class="form-actions">
                  <Button type="submit" :label="t('profile.updatePassword')" icon="pi pi-shield" :loading="pwSaving" :disabled="!pwValid" size="small" />
                </div>
              </form>
            </section>

            <!-- Sessions — real session info not available yet -->
            <section class="section-card">
              <div class="section-head">
                <h2 class="section-title">{{ t('profile.activeSessions') }}</h2>
                <p class="section-desc">{{ t('profile.activeSessionsDesc') }}</p>
              </div>
              <div class="sessions-list">
                <div class="session-item current">
                  <div class="session-icon"><i class="pi pi-desktop" /></div>
                  <div class="session-info">
                    <span class="session-device">{{ t('profile.currentSession') }}</span>
                  </div>
                  <Tag :value="t('profile.thisDevice')" severity="success" class="mini-tag" />
                </div>
              </div>
            </section>
          </div>
        </TabPanel>

        <!-- ═══ SUBSCRIPTION TAB ═══ -->
        <TabPanel value="subscription">
          <div class="tab-content">
            <section class="section-card" style="text-align: center; padding: 40px 20px;">
              <i class="pi pi-credit-card" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 12px;" />
              <h2 class="section-title" style="margin-bottom: 4px;">{{ t('profile.noSubscription') }}</h2>
              <p class="section-desc">{{ t('profile.noSubscriptionDesc') }}</p>
            </section>
          </div>
        </TabPanel>

        <!-- ═══ USAGE TAB ═══ -->
        <TabPanel value="usage">
          <div class="tab-content">
            <section class="section-card" style="text-align: center; padding: 40px 20px;">
              <i class="pi pi-chart-bar" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 12px;" />
              <h2 class="section-title" style="margin-bottom: 4px;">{{ t('profile.noUsageData') }}</h2>
              <p class="section-desc">{{ t('profile.noUsageDataDesc') }}</p>
            </section>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.profile-page {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 20px;
  max-width: 900px;
  margin: 0 auto;
  min-width: 0;
  overflow: hidden;
}

.page-header { margin-bottom: 4px; }
.page-title { font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.page-sub { font-size: 0.76rem; color: var(--text-muted); margin: 4px 0 0; }

/* ── Tabs ── */
:deep(.profile-tabs .p-tablist) { background: transparent; }
:deep(.profile-tabs .p-tab) {
  font-size: 0.74rem !important;
  padding: 8px 14px !important;
  color: var(--text-muted) !important;
  background: transparent !important;
  border: none !important;
  display: flex;
  align-items: center;
  gap: 6px;
}
:deep(.profile-tabs .p-tab-active) { color: var(--active-color) !important; }
:deep(.profile-tabs .p-tabpanels) { background: transparent; padding: 10px 0 0 !important; }

.tab-content {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* ── Section Card ── */
.section-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 12px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section-head { display: flex; flex-direction: column; gap: 2px; }
.section-title { margin: 0; font-size: 0.88rem; font-weight: 700; color: var(--text-primary); }
.section-desc { margin: 0; font-size: 0.7rem; color: var(--text-muted); }

/* ── Avatar row ── */
.avatar-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
  border-bottom: 1px solid var(--card-border);
}

.avatar-circle {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  font-weight: 700;
  flex-shrink: 0;
  overflow: hidden;
}

.avatar-circle img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.avatar-name { font-size: 0.88rem; font-weight: 600; color: var(--text-primary); }
.avatar-email { font-size: 0.7rem; color: var(--text-muted); }
.avatar-helper { font-size: 0.66rem; color: var(--active-color); margin-top: 2px; }
.avatar-btn { flex-shrink: 0; }
.avatar-input-hidden { display: none; }

/* ── Form grid ── */
.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}
@media (min-width: 640px) {
  .form-grid { grid-template-columns: 1fr 1fr; }
}

.form-field { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); }
.field-input { width: 100%; font-size: 0.8rem !important; }
.field-input-wrap { position: relative; width: 100%; }
.field-input-wrap > i:first-child {
  position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
  font-size: 0.78rem; color: var(--text-muted); z-index: 1; pointer-events: none;
}
.with-icon { padding-left: 34px !important; }
.has-toggle .field-input { padding-right: 36px !important; }
.toggle-pw {
  position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
  font-size: 0.82rem; color: var(--text-muted); cursor: pointer; z-index: 1;
}
.toggle-pw:hover { color: var(--text-secondary); }
.field-hint { font-size: 0.64rem; color: var(--text-muted); }
.field-hint.warn { color: #f59e0b; }

.form-actions { display: flex; gap: 8px; padding-top: 4px; }

.profile-form, .pw-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* ── Sessions ── */
.sessions-list { display: flex; flex-direction: column; gap: 8px; }

.session-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--hover-bg);
}

.session-icon {
  width: 34px; height: 34px; border-radius: 8px;
  background: var(--card-bg); display: flex; align-items: center; justify-content: center;
  color: var(--text-muted); font-size: 0.9rem; flex-shrink: 0;
}

.session-info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.session-device { font-size: 0.78rem; font-weight: 600; color: var(--text-primary); }
.session-meta { font-size: 0.64rem; color: var(--text-muted); }

/* ── Subscription hero ── */
.plan-hero { background: linear-gradient(135deg, var(--card-bg) 0%, color-mix(in srgb, var(--active-color) 5%, var(--card-bg)) 100%); }

.plan-hero-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.plan-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 8px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 0.72rem;
  font-weight: 700;
}

.plan-price-big {
  font-size: 2rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 8px 0 0;
  line-height: 1;
}
.plan-cycle { font-size: 0.82rem; font-weight: 500; color: var(--text-muted); }

.plan-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 8px;
}
.plan-period { font-size: 0.68rem; color: var(--text-muted); }

.plan-hero-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
  flex-wrap: wrap;
}

/* ── Usage summary grid ── */
.usage-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
@media (min-width: 768px) {
  .usage-summary-grid { grid-template-columns: repeat(4, 1fr); }
}

.usage-mini-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 10px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.usage-mini-icon {
  width: 30px; height: 30px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem;
}

.usage-mini-body { display: flex; flex-direction: column; }
.usage-mini-value { font-size: 0.88rem; font-weight: 700; color: var(--text-primary); }
.usage-mini-label { font-size: 0.62rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }

.usage-mini-bar { height: 4px !important; border-radius: 2px; }

/* ── Invoices ── */
.invoices-list { display: flex; flex-direction: column; gap: 6px; }

.invoice-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--hover-bg);
}

.invoice-info { display: flex; flex-direction: column; }
.invoice-number { font-size: 0.78rem; font-weight: 600; color: var(--text-primary); }
.invoice-date { font-size: 0.64rem; color: var(--text-muted); }
.invoice-end { display: flex; align-items: center; gap: 8px; }
.invoice-amount { font-size: 0.82rem; font-weight: 700; color: var(--text-primary); }

/* ── Meters ── */
.meters-grid { display: flex; flex-direction: column; gap: 14px; }

.meter-item { display: flex; flex-direction: column; gap: 6px; }
.meter-head { display: flex; align-items: center; gap: 10px; }
.meter-icon {
  width: 30px; height: 30px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.78rem; flex-shrink: 0;
}
.meter-label-wrap { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.meter-label { font-size: 0.74rem; font-weight: 600; color: var(--text-primary); }
.meter-count { font-size: 0.62rem; color: var(--text-muted); }
.meter-percent { font-size: 0.78rem; font-weight: 700; flex-shrink: 0; }
.meter-bar { height: 6px !important; border-radius: 3px; }

/* ── Chart ── */
.chart-shell { height: 240px; min-width: 0; overflow: hidden; position: relative; }

/* ── Activity ── */
.activity-list { display: flex; flex-direction: column; gap: 8px; }

.activity-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--hover-bg);
}

.activity-icon {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.78rem; flex-shrink: 0;
}

.activity-info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.activity-desc { font-size: 0.78rem; font-weight: 600; color: var(--text-primary); }
.activity-date { font-size: 0.62rem; color: var(--text-muted); }

.activity-credits {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-secondary);
  flex-shrink: 0;
}

.mini-tag { font-size: 0.58rem !important; padding: 2px 8px !important; }
</style>
