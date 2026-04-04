import './assets/main.css'
import 'primeicons/primeicons.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import Aura from '@primeuix/themes/aura'
import PrimeVue from 'primevue/config'
import Tooltip from 'primevue/tooltip'
import router from './router'
import i18n from './i18n'
import App from './App.vue'
import { useAuthStore } from '@/stores/auth'
import { getStoredAuthUser } from '@/utils/auth'

async function bootstrap() {
	const app = createApp(App)
	const pinia = createPinia()

	app.use(pinia)

	const auth = useAuthStore(pinia)
	const hasToken = !!localStorage.getItem('auth_token')
	const hasStoredUser = !!getStoredAuthUser()

	if (hasToken && !hasStoredUser) {
		await auth.fetchUser()
	}

	app.use(router)
	app.use(i18n)
	app.use(PrimeVue, {
		ripple: true,
		theme: {
			preset: Aura,
		},
	})

	app.directive('tooltip', Tooltip)

	app.mount('#app')
}

void bootstrap()
