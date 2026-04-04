import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import router from '@/router'
import { logout as logoutApi, getMe } from '@/services/authService'
import type { AuthUser } from '@/services/authService'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser>({
    id: 0,
    name: '',
    email: '',
    avatar: null,
    roles: [],
  })

  const isAuthenticated = computed(() => !!localStorage.getItem('auth_token'))
  const isLoaded = ref(false)

  function setUser(data: AuthUser) {
    user.value = data
    isLoaded.value = true
  }

  async function fetchUser() {
    try {
      const res = await getMe()
      if (res.success && res.data) {
        user.value = {
          id: res.data.id,
          name: res.data.name,
          email: res.data.email,
          avatar: res.data.avatar,
          roles: res.data.roles,
        }
        isLoaded.value = true
      }
    } catch {
      // Token invalid — clear and redirect
      localStorage.removeItem('auth_token')
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
    localStorage.removeItem('auth_token')
    user.value = { id: 0, name: '', email: '', avatar: null, roles: [] }
    isLoaded.value = false
    void router.replace({ name: 'login' })
  }

  return { user, isAuthenticated, isLoaded, setUser, fetchUser, logout }
})
