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

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
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
