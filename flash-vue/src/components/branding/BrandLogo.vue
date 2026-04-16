<script setup lang="ts">
withDefaults(defineProps<{
  alt?: string
}>(), {
  alt: 'Klek AI',
})
</script>

<template>
  <img src="/newlogo.png" :alt="alt" class="brand-logo-root" />
</template>

<style scoped>
.brand-logo-root {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
}
</style>