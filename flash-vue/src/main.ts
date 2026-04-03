import './assets/main.css'

import { createApp } from 'vue'
import Aura from '@primeuix/themes/aura'
import PrimeVue from 'primevue/config'
import App from './App.vue'

const app = createApp(App)

app.use(PrimeVue, {
	ripple: true,
	theme: {
		preset: Aura,
	},
})

app.mount('#app')
