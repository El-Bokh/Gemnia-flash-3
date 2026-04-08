<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getAuthenticatedHome } from '@/utils/auth'
import { useI18n } from 'vue-i18n'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const { t } = useI18n()

const errorMsg = ref('')

onMounted(async () => {
  const token = route.query.token as string | undefined
  const userBase64 = route.query.user as string | undefined
  const error = route.query.error as string | undefined

  if (error) {
    errorMsg.value = error === 'account_inactive'
      ? t('login.accountInactive')
      : t('login.googleAuthFailed')

    setTimeout(() => router.replace('/login'), 3000)
    return
  }

  if (!token || !userBase64) {
    errorMsg.value = t('login.googleAuthFailed')
    setTimeout(() => router.replace('/login'), 3000)
    return
  }

  try {
    const userData = JSON.parse(atob(userBase64))
    localStorage.setItem('auth_token', token)
    auth.setUser(userData)
    await router.replace(getAuthenticatedHome(userData))
  } catch {
    errorMsg.value = t('login.googleAuthFailed')
    setTimeout(() => router.replace('/login'), 3000)
  }
})
</script>

<template>
  <div class="callback-page">
    <div class="callback-card">
      <template v-if="errorMsg">
        <i class="pi pi-exclamation-circle error-icon" />
        <p class="error-text">{{ errorMsg }}</p>
        <p class="redirect-text">{{ t('login.redirecting') }}</p>
      </template>
      <template v-else>
        <i class="pi pi-spin pi-spinner loading-icon" />
        <p class="loading-text">{{ t('login.signingIn') }}</p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.callback-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--layout-bg, #f8fafc);
}
.callback-card {
  text-align: center;
  padding: 40px;
}
.loading-icon {
  font-size: 2rem;
  color: var(--active-color, #6366f1);
}
.loading-text {
  margin-top: 12px;
  font-size: 0.85rem;
  color: var(--text-secondary, #64748b);
}
.error-icon {
  font-size: 2rem;
  color: #ef4444;
}
.error-text {
  margin-top: 12px;
  font-size: 0.85rem;
  color: #ef4444;
  font-weight: 500;
}
.redirect-text {
  font-size: 0.72rem;
  color: var(--text-muted, #94a3b8);
  margin-top: 8px;
}
</style>
