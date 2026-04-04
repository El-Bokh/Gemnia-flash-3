import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const user = ref({
    name: 'Admin User',
    email: 'admin@flash.io',
    avatar: '',
    role: 'Super Admin',
  })

  function logout() {
    localStorage.removeItem('auth_token')
    window.location.href = '/login'
  }

  return { user, logout }
})
