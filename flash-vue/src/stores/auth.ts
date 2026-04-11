import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import router from '@/router'
import { logout as logoutApi, getMe, getSubscription } from '@/services/authService'
import type { AuthUser, QuotaInfo } from '@/services/authService'
import { clearStoredAuth, getStoredAuthUser, storeAuthUser } from '@/utils/auth'

function emptyAuthUser(): AuthUser {
  return {
    id: 0,
    name: '',
    email: '',
    avatar: null,
    roles: [],
  }
}

export const useAuthStore = defineStore('auth', () => {
  const storedUser = getStoredAuthUser()
  const user = ref<AuthUser>(storedUser ?? emptyAuthUser())

  const isAuthenticated = computed(() => !!localStorage.getItem('auth_token'))
  const isLoaded = ref(!!storedUser)

  // ── Quota ──
  const quota = ref<QuotaInfo>({
    has_subscription: false,
    plan_name: null,
    plan_slug: null,
    plan_is_free: true,
    credits_remaining: 0,
    credits_total: 0,
    credits_used: 0,
    usage_percentage: 100,
    period_start: null,
    period_end: null,
    status: '',
    requests_today: 0,
    requests_this_month: 0,
    warning_level: 'depleted',
  })

  const hasQuota = computed(() => quota.value.has_subscription && quota.value.credits_remaining > 0)
  const quotaDepleted = computed(() => quota.value.has_subscription && quota.value.credits_remaining <= 0)
  const noSubscription = computed(() => !quota.value.has_subscription)

  function setUser(data: AuthUser) {
    user.value = data
    storeAuthUser(data)
    isLoaded.value = true
  }

  function setQuota(data: QuotaInfo) {
    quota.value = data
  }

  /** Deduct credits locally (optimistic) */
  function deductCredit(amount = 1) {
    if (quota.value.credits_remaining > 0) {
      quota.value.credits_remaining = Math.max(0, quota.value.credits_remaining - amount)
      quota.value.credits_used += amount
      quota.value.usage_percentage = quota.value.credits_total > 0
        ? Math.round((quota.value.credits_used / quota.value.credits_total) * 1000) / 10
        : 100
      // Recalc warning level
      if (quota.value.credits_remaining <= 0) quota.value.warning_level = 'depleted'
      else if (quota.value.usage_percentage >= 90) quota.value.warning_level = 'critical'
      else if (quota.value.usage_percentage >= 80) quota.value.warning_level = 'low'
      else quota.value.warning_level = 'none'
    }
  }

  async function fetchUser() {
    try {
      const res = await getMe()
      if (res.success && res.data) {
        setUser({
          id: res.data.id,
          name: res.data.name,
          email: res.data.email,
          avatar: res.data.avatar,
          roles: res.data.roles,
        })
        if (res.data.quota) {
          setQuota(res.data.quota)
        }
      }
    } catch (error) {
      if ((error as any)?.response?.status === 503) {
        isLoaded.value = false
        return
      }

      clearStoredAuth()
      user.value = emptyAuthUser()
      isLoaded.value = false
      void router.replace({ name: 'login' })
    }
  }

  async function refreshQuota() {
    try {
      const res = await getSubscription()
      if (res.success && res.data) {
        setQuota(res.data)
      }
    } catch {
      // Silently fail
    }
  }

  async function logout() {
    try {
      await logoutApi()
    } catch {
      // Even if API fails, proceed with local logout
    }
    clearStoredAuth()
    user.value = emptyAuthUser()
    isLoaded.value = false
    void router.replace({ name: 'login' })
  }

  return { user, isAuthenticated, isLoaded, quota, hasQuota, quotaDepleted, noSubscription, setUser, setQuota, deductCredit, fetchUser, refreshQuota, logout }
})
