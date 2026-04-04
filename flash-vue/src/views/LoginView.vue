<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { login as loginApi } from '@/services/authService'
import { useAuthStore } from '@/stores/auth'
import { getAuthenticatedHome } from '@/utils/auth'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'

const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const email = ref('')
const password = ref('')
const remember = ref(false)
const loading = ref(false)
const errorMsg = ref('')
const showPassword = ref(false)

const isFormValid = computed(() => email.value.trim() !== '' && password.value.length >= 1)

function extractApiErrorMessage(err: unknown): string | null {
  const responseData = (err as any)?.response?.data
  const validationErrors = Object.values(responseData?.errors ?? {}).flat()
  const firstValidationError = validationErrors[0]

  if (typeof responseData?.message === 'string' && responseData.message.trim() !== '') {
    return responseData.message
  }

  return typeof firstValidationError === 'string' ? firstValidationError : null
}

async function handleLogin() {
  if (!isFormValid.value) return
  loading.value = true
  errorMsg.value = ''

  try {
    const res = await loginApi({ email: email.value.trim(), password: password.value })
    if (res.success && res.data) {
      localStorage.setItem('auth_token', res.data.token)
      auth.setUser(res.data.user)
      await router.replace(getAuthenticatedHome(res.data.user))
    } else {
      errorMsg.value = res.message || t('login.loginFailed')
    }
  } catch (err: any) {
    const msg = extractApiErrorMessage(err)
    if (msg) {
      errorMsg.value = msg
    } else if (err?.code === 'ERR_NETWORK') {
      errorMsg.value = t('login.networkError')
    } else {
      errorMsg.value = t('login.unexpectedError')
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <!-- Background decoration -->
    <div class="login-bg">
      <div class="bg-gradient" />
      <div class="bg-grid" />
    </div>

    <!-- Login card -->
    <div class="login-container">
      <div class="login-card">
        <!-- Brand -->
        <div class="login-brand">
          <img class="brand-logo-img" src="/klek-ai-mark.svg" alt="Klek AI" />
          <h1 class="brand-title">{{ t('login.title') }}</h1>
          <p class="brand-sub">{{ t('login.subtitle') }}</p>
        </div>

        <!-- Error -->
        <div v-if="errorMsg" class="login-error">
          <i class="pi pi-exclamation-circle" />
          <span>{{ errorMsg }}</span>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleLogin" class="login-form">
          <div class="form-field">
            <label class="field-label" for="login-email">{{ t('login.email') }}</label>
            <span class="p-input-icon-left field-input-wrap">
              <i class="pi pi-envelope" />
              <InputText
                id="login-email"
                v-model="email"
                placeholder="you@example.com"
                type="email"
                autocomplete="email"
                size="small"
                class="field-input"
                :disabled="loading"
              />
            </span>
          </div>

          <div class="form-field">
            <label class="field-label" for="login-password">{{ t('login.password') }}</label>
            <span class="p-input-icon-left p-input-icon-right field-input-wrap">
              <i class="pi pi-lock" />
              <InputText
                id="login-password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
                autocomplete="current-password"
                size="small"
                class="field-input"
                :disabled="loading"
                @keyup.enter="handleLogin"
              />
              <i
                :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"
                class="toggle-pw"
                @click="showPassword = !showPassword"
              />
            </span>
          </div>

          <div class="form-options">
            <label class="remember-label">
              <Checkbox v-model="remember" :binary="true" :disabled="loading" />
              <span>{{ t('login.rememberMe') }}</span>
            </label>
          </div>

          <Button
            type="submit"
            :label="t('login.signIn')"
            icon="pi pi-sign-in"
            :loading="loading"
            :disabled="!isFormValid"
            class="login-btn"
          />
        </form>

        <!-- Footer -->
        <div class="login-footer">
          <span>{{ t('login.noAccount') }}</span>
          <router-link :to="{ name: 'register' }" class="footer-link">{{ t('login.createAccount') }}</router-link>
        </div>
      </div>

      <!-- Login info banner -->
      <div class="login-info">
        <i class="pi pi-shield" />
        <span>{{ t('login.secureConnection') }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  position: relative;
  overflow: hidden;
  background: var(--layout-bg, #f8fafc);
}

/* ── Background ──────────────────────────────── */
.login-bg {
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 0;
}
.bg-gradient {
  position: absolute;
  width: 600px;
  height: 600px;
  border-radius: 50%;
  top: -200px;
  right: -150px;
  background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 70%);
}
.bg-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(99, 102, 241, 0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(99, 102, 241, 0.03) 1px, transparent 1px);
  background-size: 40px 40px;
}

/* ── Container ───────────────────────────────── */
.login-container {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 380px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* ── Card ────────────────────────────────────── */
.login-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--card-border, #e2e8f0);
  border-radius: 14px;
  padding: 28px 24px 22px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 6px 24px rgba(0, 0, 0, 0.03);
}

/* ── Brand ───────────────────────────────────── */
.login-brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}
.brand-logo-img {
  width: 56px;
  height: 56px;
  display: block;
  margin-bottom: 4px;
  filter: drop-shadow(0 0 18px rgba(129, 140, 248, 0.5)) drop-shadow(0 0 6px rgba(167, 139, 250, 0.4));
  animation: logo-pulse 3s ease-in-out infinite;
}
@keyframes logo-pulse {
  0%, 100% { filter: drop-shadow(0 0 18px rgba(129, 140, 248, 0.5)) drop-shadow(0 0 6px rgba(167, 139, 250, 0.4)); }
  50% { filter: drop-shadow(0 0 28px rgba(129, 140, 248, 0.7)) drop-shadow(0 0 12px rgba(167, 139, 250, 0.6)); }
}
.brand-title {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--text-primary, #0f172a);
  margin: 0;
  letter-spacing: -0.02em;
}
.brand-sub {
  font-size: 0.68rem;
  color: var(--text-muted, #94a3b8);
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-weight: 600;
}

/* ── Error ───────────────────────────────────── */
.login-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 12px;
  border-radius: 8px;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.15);
  color: #dc2626;
  font-size: 0.72rem;
  font-weight: 500;
  line-height: 1.3;
}
.login-error i {
  font-size: 0.82rem;
  flex-shrink: 0;
}

/* ── Form ────────────────────────────────────── */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.form-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.field-label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-secondary, #64748b);
  letter-spacing: 0.02em;
}
.field-input-wrap {
  position: relative;
  width: 100%;
}
.field-input-wrap > i:first-child {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.78rem;
  color: var(--text-muted, #94a3b8);
  z-index: 1;
  pointer-events: none;
}
.field-input {
  width: 100%;
  padding-left: 34px !important;
  font-size: 0.8rem !important;
  height: 38px;
}
.toggle-pw {
  position: absolute;
  right: 11px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.82rem;
  color: var(--text-muted, #94a3b8);
  cursor: pointer;
  z-index: 1;
  transition: color 0.15s;
}
.toggle-pw:hover {
  color: var(--text-secondary, #64748b);
}

/* ── Options ─────────────────────────────────── */
.form-options {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.remember-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  color: var(--text-secondary, #64748b);
  cursor: pointer;
}

/* ── Button ──────────────────────────────────── */
.login-btn {
  width: 100%;
  height: 38px;
  font-size: 0.8rem !important;
  font-weight: 600 !important;
  border-radius: 9px !important;
  margin-top: 2px;
}

/* ── Footer ──────────────────────────────────── */
.login-footer {
  text-align: center;
  padding-top: 4px;
  border-top: 1px solid var(--card-border, #e2e8f0);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
.login-footer span {
  font-size: 0.72rem;
  color: var(--text-muted, #94a3b8);
}
.login-footer .footer-link {
  font-size: 0.72rem;
  color: var(--active-color);
  font-weight: 600;
  text-decoration: none;
}
.login-footer .footer-link:hover {
  text-decoration: underline;
}

/* ── Info banner ────────────────────────────── */
.login-info {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 0.64rem;
  color: var(--text-muted, #94a3b8);
}
.login-info i {
  font-size: 0.7rem;
  color: #10b981;
}

/* ── Mobile ──────────────────────────────────── */
@media (max-width: 440px) {
  .login-card {
    padding: 22px 18px 18px;
  }
  .login-container {
    max-width: 100%;
  }
}

/* ── Dark mode adjustments ───────────────────── */
:root.dark .login-error {
  background: rgba(239, 68, 68, 0.12);
  border-color: rgba(239, 68, 68, 0.2);
  color: #f87171;
}
:root.dark .brand-icon {
  box-shadow: 0 0 20px rgba(99, 102, 241, 0.25);
}
</style>
