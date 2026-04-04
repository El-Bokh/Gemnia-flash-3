import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import router from '@/router'
import { logout as logoutApi, getMe } from '@/services/authService'
import type { AuthUser } from '@/services/authService'
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

  function setUser(data: AuthUser) {
    user.value = data
    storeAuthUser(data)
    isLoaded.value = true
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
      }
    } catch {
      // Token invalid — clear and redirect
      clearStoredAuth()
      user.value = emptyAuthUser()
      isLoaded.value = false
      void router.replace({ name: 'login' })
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

  return { user, isAuthenticated, isLoaded, setUser, fetchUser, logout }
})
