<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useLayoutStore } from '@/stores/layout'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'

const router = useRouter()
const layout = useLayoutStore()
const { t } = useI18n()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const agreeTerms = ref(false)
const loading = ref(false)
const errorMsg = ref('')
const showPassword = ref(false)
const showConfirm = ref(false)

const passwordsMatch = computed(() => password.value === passwordConfirm.value)
const isFormValid = computed(() =>
  name.value.trim().length >= 2 &&
  email.value.trim() !== '' &&
  password.value.length >= 8 &&
  passwordsMatch.value &&
  agreeTerms.value,
)

async function handleRegister() {
  if (!isFormValid.value) return
  loading.value = true
  errorMsg.value = ''

  try {
    // Future: call register API
    // const res = await register({ name: name.value.trim(), email: email.value.trim(), password: password.value, password_confirmation: passwordConfirm.value })
    await new Promise(resolve => setTimeout(resolve, 1200))
    await router.replace({ name: 'login' })
  } catch (err: any) {
    const msg = err?.response?.data?.message
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
