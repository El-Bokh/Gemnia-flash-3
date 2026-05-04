<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'

const { t } = useI18n()

const props = defineProps<{
  visible: boolean
  sourceUrl?: string
  sourceFile?: File | null
  initialPrompt?: string
  disabled?: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: [payload: { content: string; image: File; mask: File; renderedImage?: File }]
}>()

const imageRef = ref<HTMLImageElement | null>(null)
const maskCanvasRef = ref<HTMLCanvasElement | null>(null)
const prompt = ref('')
const brushSize = ref(44)
const hasMask = ref(false)
const imageLoaded = ref(false)
const isDrawing = ref(false)
const lastPoint = ref<{ x: number; y: number } | null>(null)
const localObjectUrl = ref<string | null>(null)
const editorId = `inpaint-prompt-${Math.random().toString(36).slice(2)}`
// Preserve source quality so the inpainting model has enough fidelity to work with.
// Both the source image and the binary mask are exported at the same max dimension
// so they share an identical pixel grid when sent to the backend.
const SOURCE_MAX_DIMENSION = 1536
const SOURCE_JPEG_QUALITY = 0.92
const MASK_MAX_DIMENSION = 1536
const MASK_BINARY_THRESHOLD = 20

const sourceSrc = computed(() => localObjectUrl.value || props.sourceUrl || '')
const canSubmit = computed(() => Boolean(prompt.value.trim() && hasMask.value && imageLoaded.value && !props.disabled))

function revokeLocalObjectUrl() {
  if (localObjectUrl.value) {
    URL.revokeObjectURL(localObjectUrl.value)
    localObjectUrl.value = null
  }
}

function resetEditor() {
  revokeLocalObjectUrl()
  prompt.value = props.initialPrompt ?? ''
  hasMask.value = false
  imageLoaded.value = false
  lastPoint.value = null

  if (props.sourceFile) {
    localObjectUrl.value = URL.createObjectURL(props.sourceFile)
  }

  nextTick(() => clearMask())
}

watch(
  () => [props.visible, props.sourceUrl, props.sourceFile, props.initialPrompt] as const,
  () => {
    if (props.visible) {
      resetEditor()
    } else {
      revokeLocalObjectUrl()
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => revokeLocalObjectUrl())

function handleImageLoad() {
  const image = imageRef.value
  const canvas = maskCanvasRef.value
  if (!image || !canvas) return

  canvas.width = image.naturalWidth || image.width
  canvas.height = image.naturalHeight || image.height
  imageLoaded.value = true
  clearMask()
}

function clearMask() {
  const canvas = maskCanvasRef.value
  const context = canvas?.getContext('2d')
  if (!canvas || !context) return

  context.clearRect(0, 0, canvas.width, canvas.height)
  hasMask.value = false
  lastPoint.value = null
}

function pointerToCanvas(event: PointerEvent) {
  const canvas = maskCanvasRef.value
  if (!canvas) return null

  const rect = canvas.getBoundingClientRect()
  const x = ((event.clientX - rect.left) / rect.width) * canvas.width
  const y = ((event.clientY - rect.top) / rect.height) * canvas.height

  return { x, y }
}

function paintAt(event: PointerEvent) {
  const canvas = maskCanvasRef.value
  const context = canvas?.getContext('2d')
  const point = pointerToCanvas(event)
  if (!canvas || !context || !point) return

  const scaledBrushSize = Math.max(4, brushSize.value * (canvas.width / Math.max(1, canvas.getBoundingClientRect().width)))

  context.globalCompositeOperation = 'source-over'
  context.strokeStyle = '#ffffff'
  context.fillStyle = '#ffffff'
  context.lineWidth = scaledBrushSize
  context.lineCap = 'round'
  context.lineJoin = 'round'

  if (lastPoint.value) {
    context.beginPath()
    context.moveTo(lastPoint.value.x, lastPoint.value.y)
    context.lineTo(point.x, point.y)
    context.stroke()
  } else {
    context.beginPath()
    context.arc(point.x, point.y, scaledBrushSize / 2, 0, Math.PI * 2)
    context.fill()
  }

  lastPoint.value = point
  hasMask.value = true
}

function startPaint(event: PointerEvent) {
  if (!imageLoaded.value || props.disabled) return
  event.preventDefault()
  isDrawing.value = true
  lastPoint.value = null
  ;(event.currentTarget as HTMLCanvasElement).setPointerCapture(event.pointerId)
  paintAt(event)
}

function continuePaint(event: PointerEvent) {
  if (!isDrawing.value) return
  event.preventDefault()
  paintAt(event)
}

function stopPaint(event: PointerEvent) {
  if (!isDrawing.value) return
  isDrawing.value = false
  lastPoint.value = null
  ;(event.currentTarget as HTMLCanvasElement).releasePointerCapture(event.pointerId)
}

function canvasToBlob(canvas: HTMLCanvasElement, type: string, quality?: number): Promise<Blob> {
  return new Promise((resolve, reject) => {
    canvas.toBlob(blob => {
      if (blob) resolve(blob)
      else reject(new Error('Failed to export mask'))
    }, type, quality)
  })
}

function resizedDimensions(width: number, height: number, maxDimension: number) {
  const longestSide = Math.max(width, height)
  const scale = Math.min(1, maxDimension / Math.max(1, longestSide))

  return {
    width: Math.max(1, Math.round(width * scale)),
    height: Math.max(1, Math.round(height * scale)),
  }
}

function hardenMask(canvas: HTMLCanvasElement) {
  const context = canvas.getContext('2d', { willReadFrequently: true })
  if (!context) throw new Error('Mask export is not supported')

  const imageData = context.getImageData(0, 0, canvas.width, canvas.height)
  const pixels = imageData.data

  for (let index = 0; index < pixels.length; index += 4) {
    const red = pixels[index] ?? 0
    const green = pixels[index + 1] ?? 0
    const blue = pixels[index + 2] ?? 0
    const alpha = pixels[index + 3] ?? 0
    const luminance = ((red * 0.2126) + (green * 0.7152) + (blue * 0.0722)) * (alpha / 255)
    const masked = luminance >= MASK_BINARY_THRESHOLD
    const value = masked ? 255 : 0

    pixels[index] = value
    pixels[index + 1] = value
    pixels[index + 2] = value
    pixels[index + 3] = 255
  }

  context.putImageData(imageData, 0, 0)
}

async function createCompressedSourceFile() {
  const image = imageRef.value
  if (!image || !image.naturalWidth || !image.naturalHeight) return null

  const dimensions = resizedDimensions(image.naturalWidth, image.naturalHeight, SOURCE_MAX_DIMENSION)
  const canvas = document.createElement('canvas')
  canvas.width = dimensions.width
  canvas.height = dimensions.height
  const context = canvas.getContext('2d')
  if (!context) return null

  context.fillStyle = '#ffffff'
  context.fillRect(0, 0, canvas.width, canvas.height)
  context.imageSmoothingEnabled = true
  context.imageSmoothingQuality = 'high'
  context.drawImage(image, 0, 0, canvas.width, canvas.height)

  const blob = await canvasToBlob(canvas, 'image/jpeg', SOURCE_JPEG_QUALITY)
  return new File([blob], 'inpaint-source.jpg', { type: 'image/jpeg' })
}

async function createMaskFile() {
  const image = imageRef.value
  const maskCanvas = maskCanvasRef.value
  if (!maskCanvas) throw new Error('Mask canvas is not ready')

  // Use the SAME target dimensions as the exported source so the mask aligns
  // pixel-for-pixel with what the model actually sees. The backend will also
  // resize the mask to match the source bytes as a safety net.
  const baseWidth = image?.naturalWidth || maskCanvas.width
  const baseHeight = image?.naturalHeight || maskCanvas.height
  const dimensions = resizedDimensions(baseWidth, baseHeight, MASK_MAX_DIMENSION)

  const exportCanvas = document.createElement('canvas')
  exportCanvas.width = dimensions.width
  exportCanvas.height = dimensions.height
  const context = exportCanvas.getContext('2d')
  if (!context) throw new Error('Mask export is not supported')

  // Black background = "keep". White strokes = "edit".
  context.fillStyle = '#000000'
  context.fillRect(0, 0, exportCanvas.width, exportCanvas.height)
  // Disable smoothing so brush edges remain near-binary before hardening.
  context.imageSmoothingEnabled = false
  context.drawImage(maskCanvas, 0, 0, exportCanvas.width, exportCanvas.height)
  hardenMask(exportCanvas)

  const blob = await canvasToBlob(exportCanvas, 'image/png')
  return new File([blob], 'inpaint-mask.png', { type: 'image/png' })
}

async function createSourceFile() {
  const compressedSource = await createCompressedSourceFile().catch(() => null)
  if (compressedSource) return compressedSource

  if (props.sourceFile) return props.sourceFile
  if (!props.sourceUrl) throw new Error('Source image is missing')

  const response = await fetch(props.sourceUrl, { credentials: 'include' })
  if (!response.ok) throw new Error('Failed to load source image')
  const blob = await response.blob()
  const type = blob.type || 'image/png'
  const extension = type.includes('jpeg') || type.includes('jpg') ? 'jpg' : 'png'

  return new File([blob], `inpaint-source.${extension}`, { type })
}

async function handleSubmit() {
  if (!canSubmit.value) return

  const image = await createSourceFile()
  const mask = await createMaskFile()

  emit('submit', {
    content: prompt.value.trim(),
    image,
    mask,
  })
  emit('close')
}

function handleClose() {
  if (props.disabled) return
  emit('close')
}
</script>


<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="visible" class="inpaint-backdrop" @click.self="handleClose">
        <section class="inpaint-dialog" role="dialog" aria-modal="true" :aria-labelledby="`${editorId}-title`">
          <header class="inpaint-header">
            <div class="inpaint-title-wrap">
              <i class="pi pi-pencil" />
              <h2 :id="`${editorId}-title`">{{ t('chat.inpaintTitle') }}</h2>
            </div>
            <Button icon="pi pi-times" severity="secondary" text rounded :disabled="disabled" @click="handleClose" />
          </header>

          <div class="inpaint-body">
            <div class="canvas-shell">
              <div class="canvas-stage">
                <img
                  v-if="sourceSrc"
                  ref="imageRef"
                  :src="sourceSrc"
                  class="source-image"
                  alt=""
                  draggable="false"
                  @load="handleImageLoad"
                />
                <canvas
                  ref="maskCanvasRef"
                  class="mask-canvas"
                  :class="{ drawing: isDrawing }"
                  @pointerdown="startPaint"
                  @pointermove="continuePaint"
                  @pointerup="stopPaint"
                  @pointercancel="stopPaint"
                />
              </div>
            </div>

            <aside class="inpaint-controls">
              <label class="control-label" :for="editorId">{{ t('chat.inpaintPrompt') }}</label>
              <textarea
                :id="editorId"
                v-model="prompt"
                class="inpaint-prompt"
                rows="5"
                :placeholder="t('chat.inpaintPromptPlaceholder')"
                :disabled="disabled"
              />

              <div class="brush-control">
                <div class="brush-heading">
                  <span>{{ t('chat.brushSize') }}</span>
                  <strong>{{ brushSize }}px</strong>
                </div>
                <input v-model.number="brushSize" type="range" min="12" max="96" step="2" :disabled="disabled" />
              </div>

              <div class="control-actions">
                <Button
                  icon="pi pi-eraser"
                  severity="secondary"
                  outlined
                  :label="t('chat.clearMask')"
                  :disabled="disabled || !hasMask"
                  @click="clearMask"
                />
                <Button
                  icon="pi pi-sparkles"
                  :label="t('chat.applyInpaint')"
                  :disabled="!canSubmit"
                  @click="handleSubmit"
                />
              </div>
            </aside>
          </div>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.inpaint-backdrop {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
  background: rgba(15, 23, 42, 0.72);
  backdrop-filter: blur(8px);
}

.inpaint-dialog {
  width: min(980px, 100%);
  max-height: min(820px, 94vh);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--card-border);
  border-radius: 8px;
  background: var(--card-bg);
  color: var(--text-primary);
  box-shadow: 0 24px 80px rgba(15, 23, 42, 0.32);
}

.inpaint-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--card-border);
}

.inpaint-title-wrap {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
}

.inpaint-title-wrap i {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: rgba(14, 165, 233, 0.12);
  color: #0284c7;
  flex-shrink: 0;
}

.inpaint-title-wrap h2 {
  margin: 0;
  font-size: 0.98rem;
  line-height: 1.2;
  font-weight: 700;
}

.inpaint-body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 290px;
  gap: 14px;
  min-height: 0;
  padding: 14px;
  overflow: auto;
}

.canvas-shell {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 0;
  min-height: 340px;
  border: 1px solid var(--card-border);
  border-radius: 8px;
  background: repeating-conic-gradient(rgba(148, 163, 184, 0.16) 0% 25%, transparent 0% 50%) 50% / 22px 22px;
  overflow: hidden;
}

.canvas-stage {
  position: relative;
  display: inline-block;
  max-width: 100%;
  max-height: min(66vh, 640px);
}

.source-image {
  display: block;
  max-width: 100%;
  max-height: min(66vh, 640px);
  user-select: none;
  object-fit: contain;
}

.mask-canvas {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  touch-action: none;
  cursor: crosshair;
  opacity: 0.72;
  filter: drop-shadow(0 0 2px rgba(2, 132, 199, 0.9));
}

.mask-canvas.drawing {
  cursor: none;
}

.inpaint-controls {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
}

.control-label,
.brush-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: var(--text-secondary);
  font-size: 0.76rem;
  font-weight: 700;
}

.inpaint-prompt {
  width: 100%;
  min-height: 124px;
  resize: vertical;
  border: 1px solid var(--card-border);
  border-radius: 8px;
  background: var(--hover-bg);
  color: var(--text-primary);
  padding: 10px;
  font: inherit;
  font-size: 0.84rem;
  line-height: 1.48;
  outline: none;
}

.inpaint-prompt:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
}

.brush-control {
  display: grid;
  gap: 8px;
}

.brush-control input[type='range'] {
  width: 100%;
  accent-color: #0284c7;
}

.brush-heading strong {
  color: var(--text-primary);
  font-size: 0.74rem;
}

.control-actions {
  display: flex;
  gap: 8px;
  margin-top: auto;
}

.control-actions :deep(.p-button) {
  flex: 1;
  min-width: 0;
  border-radius: 8px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.18s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 760px) {
  .inpaint-backdrop {
    padding: 8px;
    align-items: stretch;
  }

  .inpaint-dialog {
    max-height: 100%;
  }

  .inpaint-body {
    grid-template-columns: 1fr;
  }

  .canvas-shell {
    min-height: 260px;
  }

  .canvas-stage,
  .source-image {
    max-height: 54vh;
  }

  .control-actions {
    flex-direction: column;
  }
}
</style>
