<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { register as registerApi, getGoogleRedirectUrl } from '@/services/authService'
import { useAuthStore } from '@/stores/auth'
import { getAuthenticatedHome } from '@/utils/auth'
import { useSeo } from '@/composables/useSeo'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'

const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

useSeo({
  title: computed(() => t('seo.registerTitle')),
  description: computed(() => t('seo.registerDescription')),
  path: '/register',
  noindex: true,
})

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const agreeTerms = ref(false)
const loading = ref(false)
const errorMsg = ref('')
const showPassword = ref(false)
const showConfirm = ref(false)
const googleLoading = ref(false)

const passwordsMatch = computed(() => password.value === passwordConfirm.value)
const isFormValid = computed(() =>
  name.value.trim().length >= 2 &&
  email.value.trim() !== '' &&
  password.value.length >= 8 &&
  passwordsMatch.value &&
  agreeTerms.value,
)

function extractApiErrorMessage(err: unknown): string | null {
  const responseData = (err as any)?.response?.data
  const validationErrors = Object.values(responseData?.errors ?? {}).flat()
  const firstValidationError = validationErrors[0]

  if (typeof responseData?.message === 'string' && responseData.message.trim() !== '') {
    return responseData.message
  }

  return typeof firstValidationError === 'string' ? firstValidationError : null
}

async function handleRegister() {
  if (!isFormValid.value) return
  loading.value = true
  errorMsg.value = ''

  try {
    const res = await registerApi({
      name: name.value.trim(),
      email: email.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirm.value,
    })

    if (res.success && res.data) {
      localStorage.setItem('auth_token', res.data.token)
      auth.setUser(res.data.user)
      await router.replace(getAuthenticatedHome(res.data.user))
    } else {
      errorMsg.value = res.message || t('register.unexpectedError')
    }
  } catch (err: any) {
    const msg = extractApiErrorMessage(err)
    if (msg) {
      errorMsg.value = msg
    } else if (err?.code === 'ERR_NETWORK') {
      errorMsg.value = t('register.networkError')
    } else {
      errorMsg.value = t('register.unexpectedError')
    }
  } finally {
    loading.value = false
  }
}

function handleGoogleLogin() {
  googleLoading.value = true
  window.location.href = getGoogleRedirectUrl()
}
</script>

<template>
  <div class="register-page">
    <div class="register-bg">
      <div class="bg-gradient" />
      <div class="bg-grid" />
    </div>

    <div class="register-container">
      <div class="register-card">
        <!-- Brand -->
        <div class="register-brand">
          <div class="brand-mark">
            <i class="pi pi-sparkles" />
          </div>
          <h1 class="brand-title">{{ t('register.title') }}</h1>
          <p class="brand-sub">{{ t('register.subtitle') }}</p>
        </div>

        <!-- Error -->
        <div v-if="errorMsg" class="register-error">
          <i class="pi pi-exclamation-circle" />
          <span>{{ errorMsg }}</span>
        </div>

        <!-- Form -->
        <form class="register-form" @submit.prevent="handleRegister">
          <div class="form-field">
            <label class="field-label" for="reg-name">{{ t('register.name') }}</label>
            <span class="field-input-wrap">
              <i class="pi pi-user" />
              <InputText
                id="reg-name"
                v-model="name"
                :placeholder="t('register.namePlaceholder')"
                autocomplete="name"
                size="small"
                class="field-input"
                :disabled="loading"
              />
            </span>
          </div>

          <div class="form-field">
            <label class="field-label" for="reg-email">{{ t('register.email') }}</label>
            <span class="field-input-wrap">
              <i class="pi pi-envelope" />
              <InputText
                id="reg-email"
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
            <label class="field-label" for="reg-password">{{ t('register.password') }}</label>
            <span class="field-input-wrap has-toggle">
              <i class="pi pi-lock" />
              <InputText
                id="reg-password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                :placeholder="t('register.passwordPlaceholder')"
                autocomplete="new-password"
                size="small"
                class="field-input"
                :disabled="loading"
              />
              <i
                :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"
                class="toggle-pw"
                @click="showPassword = !showPassword"
              />
            </span>
            <small v-if="password.length > 0 && password.length < 8" class="field-hint warn">
              {{ t('register.passwordMin') }}
            </small>
          </div>

          <div class="form-field">
            <label class="field-label" for="reg-confirm">{{ t('register.confirmPassword') }}</label>
            <span class="field-input-wrap has-toggle">
              <i class="pi pi-lock" />
              <InputText
                id="reg-confirm"
                v-model="passwordConfirm"
                :type="showConfirm ? 'text' : 'password'"
                :placeholder="t('register.confirmPlaceholder')"
                autocomplete="new-password"
                size="small"
                class="field-input"
                :disabled="loading"
              />
              <i
                :class="showConfirm ? 'pi pi-eye-slash' : 'pi pi-eye'"
                class="toggle-pw"
                @click="showConfirm = !showConfirm"
              />
            </span>
            <small v-if="passwordConfirm.length > 0 && !passwordsMatch" class="field-hint warn">
              {{ t('register.passwordMismatch') }}
            </small>
          </div>

          <label class="agree-label">
            <Checkbox v-model="agreeTerms" :binary="true" :disabled="loading" />
            <span>{{ t('register.agreeTerms') }}</span>
          </label>

          <Button
            type="submit"
            :label="t('register.createAccount')"
            icon="pi pi-user-plus"
            :loading="loading"
            :disabled="!isFormValid"
            class="register-btn"
          />
        </form>

        <!-- Divider -->
        <div class="register-divider">
          <span>{{ t('register.orContinueWith') }}</span>
        </div>

        <!-- Google Login -->
        <button
          type="button"
          class="google-btn"
          :disabled="googleLoading"
          @click="handleGoogleLogin"
        >
          <svg class="google-icon" viewBox="0 0 24 24" width="18" height="18">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18A10.96 10.96 0 0 0 1 12c0 1.77.42 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
          </svg>
          <span>{{ t('register.googleSignUp') }}</span>
          <i v-if="googleLoading" class="pi pi-spin pi-spinner google-spinner" />
        </button>

        <div class="register-footer">
          <span>{{ t('register.haveAccount') }}</span>
          <router-link :to="{ name: 'login' }" class="footer-link">{{ t('register.signIn') }}</router-link>
        </div>
      </div>

      <div class="register-info">
        <i class="pi pi-shield" />
        <span>{{ t('register.secureConnection') }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.register-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  position: relative;
  overflow: hidden;
  background: var(--layout-bg, #f8fafc);
}

.register-bg {
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
  left: -150px;
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

.register-container {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 400px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.register-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 14px;
  padding: 28px 24px 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 6px 24px rgba(0, 0, 0, 0.03);
}

.register-brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.brand-mark {
  width: 50px;
  height: 50px;
  border-radius: 14px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  margin-bottom: 4px;
}

.brand-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
}

.brand-sub {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin: 0;
}

.register-error {
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
}
.register-error i { font-size: 0.82rem; flex-shrink: 0; }

.register-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.field-label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-secondary);
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
  color: var(--text-muted);
  z-index: 1;
  pointer-events: none;
}
.field-input {
  width: 100%;
  padding-left: 34px !important;
  font-size: 0.8rem !important;
  height: 38px;
}
.has-toggle .field-input {
  padding-right: 36px !important;
}
.toggle-pw {
  position: absolute;
  right: 11px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.82rem;
  color: var(--text-muted);
  cursor: pointer;
  z-index: 1;
  transition: color 0.15s;
}
.toggle-pw:hover { color: var(--text-secondary); }

.field-hint {
  font-size: 0.64rem;
  color: var(--text-muted);
}
.field-hint.warn {
  color: #f59e0b;
}

.agree-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.72rem;
  color: var(--text-secondary);
  cursor: pointer;
}

.register-btn {
  width: 100%;
  height: 38px;
  font-size: 0.8rem !important;
  font-weight: 600 !important;
  border-radius: 9px !important;
  margin-top: 2px;
}

/* ── Divider ─────────────────────────────────── */
.register-divider {
  display: flex;
  align-items: center;
  gap: 12px;
}
.register-divider::before,
.register-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--card-border, #e2e8f0);
}
.register-divider span {
  font-size: 0.64rem;
  color: var(--text-muted, #94a3b8);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
  white-space: nowrap;
}

/* ── Google Button ───────────────────────────── */
.google-btn {
  width: 100%;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1px solid var(--card-border, #e2e8f0);
  border-radius: 9px;
  background: var(--card-bg, #ffffff);
  color: var(--text-primary, #0f172a);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}
.google-btn:hover:not(:disabled) {
  background: var(--hover-bg, #f1f5f9);
  border-color: var(--text-muted, #94a3b8);
}
.google-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.google-icon {
  flex-shrink: 0;
}
.google-spinner {
  font-size: 0.75rem;
}

.register-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 0.72rem;
  color: var(--text-muted);
}

.footer-link {
  color: var(--active-color);
  font-weight: 600;
  text-decoration: none;
}
.footer-link:hover {
  text-decoration: underline;
}

.register-info {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 0.66rem;
  color: var(--text-muted);
  opacity: 0.7;
}
</style>
