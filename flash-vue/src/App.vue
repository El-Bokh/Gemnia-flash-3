<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'

const projectName = ref('Flash Vue')

const featureItems = [
  { label: 'PrimeVue', value: '4.5.4' },
  { label: 'Tailwind', value: '4.2.2' },
  { label: 'Build', value: 'Vite 8' },
]

const setupItems = [
  'PrimeVue is enabled in src/main.ts with the Aura preset.',
  'Tailwind CSS 4 is loaded through the Vite plugin and the main stylesheet.',
  'This screen mixes PrimeVue components with Tailwind utility classes.',
]

const message = computed(() => `${projectName.value} is ready with PrimeVue and Tailwind CSS.`)
</script>

<template>
  <div
    class="relative min-h-screen overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.18),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(16,185,129,0.16),_transparent_28%),linear-gradient(180deg,_#f8fafc_0%,_#eef2ff_100%)]"
  >
    <div class="mx-auto flex min-h-screen max-w-6xl flex-col gap-6 px-6 py-10 lg:px-10">
      <header
        class="flex flex-wrap items-center justify-between gap-4 rounded-[28px] border border-white/70 bg-white/80 p-5 shadow-[0_20px_70px_rgba(15,23,42,0.08)] backdrop-blur"
      >
        <div class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">
            Flash frontend
          </p>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
            PrimeVue and Tailwind are connected.
          </h1>
          <p class="max-w-2xl text-sm leading-6 text-slate-600">
            The project is now configured with PrimeVue 4 styled mode and Tailwind CSS 4 via the
            Vite plugin.
          </p>
        </div>

        <Tag value="Ready" severity="success" rounded />
      </header>

      <main class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.9fr)]">
        <Card class="border border-white/70 bg-white/85 shadow-[0_30px_90px_rgba(15,23,42,0.08)] backdrop-blur">
          <template #title>Live setup check</template>
          <template #subtitle>
            PrimeVue components are rendering with Tailwind layout utilities.
          </template>

          <template #content>
            <div class="space-y-6">
              <div class="grid gap-4 sm:grid-cols-3">
                <div
                  v-for="item in featureItems"
                  :key="item.label"
                  class="rounded-2xl border border-slate-200/70 bg-slate-50/80 p-4"
                >
                  <p class="text-sm font-semibold text-slate-900">{{ item.value }}</p>
                  <p class="mt-1 text-xs uppercase tracking-[0.28em] text-slate-500">
                    {{ item.label }}
                  </p>
                </div>
              </div>

              <div class="space-y-3">
                <label for="project-name" class="text-sm font-medium text-slate-700">
                  Project label
                </label>
                <InputText
                  id="project-name"
                  v-model="projectName"
                  class="w-full"
                  placeholder="Flash Vue"
                />
              </div>

              <div class="rounded-2xl bg-slate-950 px-5 py-4 text-sm text-slate-100">
                {{ message }}
              </div>

              <div class="flex flex-wrap gap-3">
                <Button label="Primary action" />
                <Button label="Secondary action" severity="secondary" variant="outlined" />
              </div>
            </div>
          </template>
        </Card>

        <div class="space-y-6">
          <Card class="border border-white/70 bg-white/85 shadow-[0_30px_90px_rgba(15,23,42,0.08)] backdrop-blur">
            <template #title>What was added</template>

            <template #content>
              <ul class="space-y-3 text-sm leading-6 text-slate-600">
                <li v-for="item in setupItems" :key="item" class="flex items-start gap-3">
                  <span class="mt-2 size-2 rounded-full bg-emerald-500"></span>
                  <span>{{ item }}</span>
                </li>
              </ul>
            </template>
          </Card>

          <Card class="border border-slate-900/90 bg-slate-950 text-white shadow-[0_30px_90px_rgba(15,23,42,0.12)]">
            <template #title>
              <span class="text-white">Updated entry points</span>
            </template>

            <template #content>
              <div class="space-y-3 text-sm leading-6 text-slate-300">
                <p>src/main.ts initializes PrimeVue and applies the Aura theme preset.</p>
                <p>vite.config.ts enables Tailwind CSS through the official Vite plugin.</p>
                <p>src/assets/main.css loads Tailwind and keeps the app shell at full height.</p>
              </div>
            </template>
          </Card>
        </div>
      </main>
    </div>
  </div>
</template>
