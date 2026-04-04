import { createI18n } from 'vue-i18n'
import en from './en'
import ar from './ar'

const savedLocale = (localStorage.getItem('flash-locale') as 'en' | 'ar') || 'en'

// Apply dir and lang on load
document.documentElement.dir = savedLocale === 'ar' ? 'rtl' : 'ltr'
document.documentElement.lang = savedLocale

const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'en',
  messages: { en, ar },
})

export default i18n
