<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps<{
  src: string
}>()

const canvasRef = ref<HTMLCanvasElement | null>(null)
const fallbackSrc = ref<string | null>(null)
let activeImage: HTMLImageElement | null = null

function showFallback(src: string) {
  fallbackSrc.value = src

  const canvas = canvasRef.value
  const context = canvas?.getContext('2d')
  if (!canvas || !context) return

  context.clearRect(0, 0, canvas.width, canvas.height)
}

function paintHighlight(maskImage: HTMLImageElement) {
  const canvas = canvasRef.value
  if (!canvas || !maskImage.naturalWidth || !maskImage.naturalHeight) return

  const sourceCanvas = document.createElement('canvas')
  sourceCanvas.width = maskImage.naturalWidth
  sourceCanvas.height = maskImage.naturalHeight

  const sourceContext = sourceCanvas.getContext('2d', { willReadFrequently: true })
  const targetContext = canvas.getContext('2d')
  if (!sourceContext || !targetContext) return

  canvas.width = sourceCanvas.width
  canvas.height = sourceCanvas.height
  sourceContext.drawImage(maskImage, 0, 0)

  try {
    const sourceData = sourceContext.getImageData(0, 0, sourceCanvas.width, sourceCanvas.height)
    const highlightData = targetContext.createImageData(sourceCanvas.width, sourceCanvas.height)

    for (let index = 0; index < sourceData.data.length; index += 4) {
      const red = sourceData.data[index] ?? 0
      const green = sourceData.data[index + 1] ?? 0
      const blue = sourceData.data[index + 2] ?? 0
      const alpha = sourceData.data[index + 3] ?? 0
      const luminance = ((red * 0.2126) + (green * 0.7152) + (blue * 0.0722)) * (alpha / 255)
      const highlightAlpha = luminance > 14 ? Math.min(210, Math.round(luminance * 0.82)) : 0

      highlightData.data[index] = 56
      highlightData.data[index + 1] = 189
      highlightData.data[index + 2] = 248
      highlightData.data[index + 3] = highlightAlpha
    }

    fallbackSrc.value = null
    targetContext.clearRect(0, 0, canvas.width, canvas.height)
    targetContext.putImageData(highlightData, 0, 0)
  } catch {
    showFallback(props.src)
  }
}

function loadMask(src: string) {
  fallbackSrc.value = null

  const image = new Image()
  activeImage = image
  image.onload = () => {
    if (activeImage === image) {
      paintHighlight(image)
    }
  }
  image.onerror = () => {
    if (activeImage === image) {
      showFallback(src)
    }
  }
  image.src = src
}

watch(
  () => props.src,
  (src) => {
    if (src) loadMask(src)
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  activeImage = null
})
</script>

<template>
  <canvas v-show="!fallbackSrc" ref="canvasRef" class="mask-highlight" aria-hidden="true" />
  <img v-if="fallbackSrc" :src="fallbackSrc" class="mask-highlight mask-highlight-fallback" aria-hidden="true" />
</template>

<style scoped>
.mask-highlight {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border-radius: 10px;
  pointer-events: none;
}

.mask-highlight-fallback {
  object-fit: cover;
  opacity: 0.55;
  mix-blend-mode: screen;
  filter: brightness(0) saturate(100%) invert(72%) sepia(31%) saturate(5641%) hue-rotate(164deg) brightness(102%) contrast(95%);
}
</style>