import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import i18n from '@/i18n'

export const useLayoutStore = defineStore('layout', () => {
  const sidebarCollapsed = ref(false)
  const sidebarMobileOpen = ref(false)
  const darkMode = ref(true)
  const locale = ref<'en' | 'ar'>('en')

  // Initialize dark mode from localStorage (default: true)
  const savedDark = localStorage.getItem('flash-dark-mode')
  if (savedDark === 'false') {
    darkMode.value = false
    document.documentElement.classList.remove('dark')
  } else {
    document.documentElement.classList.add('dark')
  }

  const savedLocale = localStorage.getItem('flash-locale') as 'en' | 'ar' | null
  if (savedLocale) locale.value = savedLocale

  watch(darkMode, (val) => {
    localStorage.setItem('flash-dark-mode', String(val))
    document.documentElement.classList.toggle('dark', val)
  })

  watch(locale, (val) => {
    localStorage.setItem('flash-locale', val)
    document.documentElement.dir = val === 'ar' ? 'rtl' : 'ltr'
    document.documentElement.lang = val
    i18n.global.locale.value = val
  })

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function toggleMobileSidebar() {
    sidebarMobileOpen.value = !sidebarMobileOpen.value
  }

  function closeMobileSidebar() {
    sidebarMobileOpen.value = false
  }

  function toggleDarkMode() {
    darkMode.value = !darkMode.value
  }

  function toggleLocale() {
    locale.value = locale.value === 'en' ? 'ar' : 'en'
  }

  return {
    sidebarCollapsed,
    sidebarMobileOpen,
    darkMode,
    locale,
    toggleSidebar,
    toggleMobileSidebar,
    closeMobileSidebar,
    toggleDarkMode,
    toggleLocale,
  }
})
