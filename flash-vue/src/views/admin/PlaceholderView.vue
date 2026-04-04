<script setup lang="ts">
import { useRoute } from 'vue-router'

const route = useRoute()
const title = route.meta.title as string || 'Page'
</script>

<template>
  <div class="p-5">
    <div class="mb-5">
      <h1 class="text-xl font-semibold text-[var(--text-primary,#0f172a)]">{{ title }}</h1>
      <p class="mt-1 text-sm text-[var(--text-muted,#94a3b8)]">This page is under construction.</p>
    </div>

    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-[var(--card-border,#cbd5e1)] bg-[var(--card-bg,#ffffff)] py-20">
      <i class="pi pi-wrench mb-3 text-[var(--text-muted,#94a3b8)]" style="font-size: 2rem" />
      <p class="text-sm font-medium text-[var(--text-secondary,#64748b)]">{{ title }} — Coming Soon</p>
    </div>
  </div>
</template>
