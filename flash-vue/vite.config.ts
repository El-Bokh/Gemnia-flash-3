import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const backendOrigin = (env.VITE_API_BASE_URL || 'http://localhost:8000/api').replace(/\/api\/?$/, '')

  return {
    plugins: [
      vue(),
      tailwindcss(),
      vueDevTools(),
    ],
    server: {
      proxy: {
        '/api': {
          target: backendOrigin,
          changeOrigin: true,
          secure: false,
        },
        '/sanctum': {
          target: backendOrigin,
          changeOrigin: true,
          secure: false,
        },
        '/storage': {
          target: backendOrigin,
          changeOrigin: true,
          secure: false,
        },
      },
    },
    build: {
      manifest: 'asset-manifest.json',
    },
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
        'vue-i18n': 'vue-i18n/dist/vue-i18n.esm-bundler.js',
      },
    },
  }
})
