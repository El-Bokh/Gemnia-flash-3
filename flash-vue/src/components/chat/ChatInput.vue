<script setup lang="ts">
import { ref, nextTick, watch, onMounted, onUnmounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useChatStore } from '@/stores/chat'
import type { AiMode } from '@/services/chatService'
import AspectRatioSelector from '@/components/chat/AspectRatioSelector.vue'
import InpaintingEditor from '@/components/chat/InpaintingEditor.vue'
import Button from 'primevue/button'

const { t } = useI18n()
const chat = useChatStore()

const props = defineProps<{
  disabled?: boolean
  showStyleSelector?: boolean
}>()

const emit = defineEmits<{
  send: [content: string, image?: File]
  sendProducts: [content: string, images: File[]]
  inpaint: [content: string, image: File, mask: File]
  toggleStyles: []
  toggleProducts: []
  openStyles: []
  openProducts: []
  openUpload: []
}>()

interface SuggestionAction {
  prompt?: string
  mode?: AiMode
  open?: 'upload' | 'styles' | 'products' | 'productGallery' | 'none'
}

interface AttachedFile {
  file: File
  preview: string | null
  type: 'image' | 'file'
}

const IMAGE_MAX_DIMENSION = 1024
const IMAGE_FALLBACK_DIMENSION = 768
const IMAGE_TARGET_BYTES = 200 * 1024
const IMAGE_PRIMARY_QUALITY = 0.78
const IMAGE_FALLBACK_QUALITY = 0.68

const message = ref('')
const textareaRef = ref<HTMLTextAreaElement | null>(null)
const showAddMenu = ref(false)
const showToolsMenu = ref(false)
const isDragOver = ref(false)
const attachedFiles = ref<AttachedFile[]>([])
const imageInputRef = ref<HTMLInputElement | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const showLinkInput = ref(false)
const linkUrl = ref('')
const productMode = ref(false)
const productImages = ref<AttachedFile[]>([])
const productInputRef = ref<HTMLInputElement | null>(null)
const showAspectRatioPopup = ref(false)
const aspectRatioPopupRef = ref<HTMLDivElement | null>(null)
const attachmentTaskCount = ref(0)
const isProcessingAttachments = computed(() => attachmentTaskCount.value > 0)
const showInpaintEditor = ref(false)
const inpaintSource = ref<AttachedFile | null>(null)
const inpaintSourceFile = computed(() => inpaintSource.value?.file ?? null)
const inpaintSourceUrl = computed(() => inpaintSource.value?.preview ?? undefined)
const durationOptions = [4, 6, 8]
const resolutionOptions = ['720p', '1080p'] as const
const currentModeLabel = computed(() => {
  if (chat.aiMode === 'image') return t('chat.modeImage')
  if (chat.aiMode === 'video') return t('chat.modeVideo')
  return t('chat.modeText')
})
const currentModeIcon = computed(() => {
  if (chat.aiMode === 'image') return 'pi pi-image'
  if (chat.aiMode === 'video') return 'pi pi-video'
  return 'pi pi-comments'
})

function replaceExtension(fileName: string, extension: string) {
  return fileName.replace(/\.[^.]+$/, '') + '.' + extension
}

function readFileAsDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result ?? ''))
    reader.onerror = () => reject(reader.error ?? new Error('Failed to read file'))
    reader.readAsDataURL(file)
  })
}

function loadImageElement(file: File): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const objectUrl = URL.createObjectURL(file)
    const image = new Image()
    image.onload = () => {
      URL.revokeObjectURL(objectUrl)
      resolve(image)
    }
    image.onerror = () => {
      URL.revokeObjectURL(objectUrl)
      reject(new Error('Failed to load image'))
    }
    image.src = objectUrl
  })
}

function canvasToBlob(canvas: HTMLCanvasElement, type: string, quality: number): Promise<Blob | null> {
  return new Promise(resolve => {
    canvas.toBlob(blob => resolve(blob), type, quality)
  })
}

async function renderCompressedImage(image: HTMLImageElement, maxDimension: number, quality: number) {
  const longestSide = Math.max(image.naturalWidth, image.naturalHeight)
  const scale = Math.min(1, maxDimension / longestSide)
  const width = Math.max(1, Math.round(image.naturalWidth * scale))
  const height = Math.max(1, Math.round(image.naturalHeight * scale))
  const canvas = document.createElement('canvas')
  const context = canvas.getContext('2d')

  if (!context) return null

  canvas.width = width
  canvas.height = height
  context.fillStyle = '#ffffff'
  context.fillRect(0, 0, width, height)
  context.imageSmoothingEnabled = true
  context.imageSmoothingQuality = 'high'
  context.drawImage(image, 0, 0, width, height)

  return canvasToBlob(canvas, 'image/jpeg', quality)
}

async function compressImageFile(file: File): Promise<File> {
  if (!file.type.startsWith('image/') || file.type === 'image/gif') return file

  try {
    const image = await loadImageElement(file)
    const longestSide = Math.max(image.naturalWidth, image.naturalHeight)
    const attempts = [
      { maxDimension: IMAGE_MAX_DIMENSION, quality: IMAGE_PRIMARY_QUALITY },
      { maxDimension: IMAGE_FALLBACK_DIMENSION, quality: IMAGE_FALLBACK_QUALITY },
    ]

    for (const attempt of attempts) {
      const blob = await renderCompressedImage(image, attempt.maxDimension, attempt.quality)

      if (!blob) continue

      const shouldUseCompressed = blob.size < file.size || longestSide > attempt.maxDimension

      if (!shouldUseCompressed) {
        return file
      }

      if (blob.size <= IMAGE_TARGET_BYTES || attempt === attempts[attempts.length - 1]) {
        return new File([blob], replaceExtension(file.name, 'jpg'), {
          type: 'image/jpeg',
          lastModified: file.lastModified,
        })
      }
    }
  } catch {
    return file
  }

  return file
}

async function createAttachedFile(file: File, type: 'image' | 'file', previewMode: 'dataUrl' | 'objectUrl' = 'dataUrl'): Promise<AttachedFile> {
  const preparedFile = file.type.startsWith('image/')
    ? await compressImageFile(file)
    : file
  const isImage = preparedFile.type.startsWith('image/')

  return {
    file: preparedFile,
    preview: isImage
      ? previewMode === 'objectUrl'
        ? URL.createObjectURL(preparedFile)
        : await readFileAsDataUrl(preparedFile)
      : null,
    type: isImage ? 'image' : type,
  }
}

async function withAttachmentTask<T>(task: Promise<T>) {
  attachmentTaskCount.value += 1
  try {
    return await task
  } finally {
    attachmentTaskCount.value = Math.max(0, attachmentTaskCount.value - 1)
  }
}

function revokePreview(preview: string | null) {
  if (preview?.startsWith('blob:')) {
    URL.revokeObjectURL(preview)
  }
}

function clearProductImages() {
  productImages.value.forEach(image => revokePreview(image.preview))
  productImages.value = []
}

function autoResize() {
  const el = textareaRef.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = Math.min(el.scrollHeight, 200) + 'px'
}

watch(message, () => {
  nextTick(autoResize)
})

function handleSend() {
  const text = message.value.trim()

  // Product mode
  if (productMode.value) {
    if (props.disabled) return
    if (isProcessingAttachments.value) return
    if (productImages.value.length < 2) return
    if (!text) return
    const files = productImages.value.map(af => af.file)
    emit('sendProducts', text, files)
    message.value = ''
    clearProductImages()
    productMode.value = false
    nextTick(autoResize)
    return
  }

  if (!text && attachedFiles.value.length === 0) return
  if (isProcessingAttachments.value) return
  if (props.disabled) return

  // Find the first image attachment (if any)
  const imageFile = attachedFiles.value.find(af => af.type === 'image')?.file
  emit('send', text || '(attached files)', imageFile)

  message.value = ''
  attachedFiles.value = []
  showLinkInput.value = false
  linkUrl.value = ''
  nextTick(autoResize)
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    handleSend()
  }
}

function handleToolClick(tool: string) {
  if (props.disabled) return

  showAddMenu.value = false
  if (tool === 'upload') {
    imageInputRef.value?.click()
  } else if (tool === 'file') {
    fileInputRef.value?.click()
  } else if (tool === 'link') {
    showLinkInput.value = true
    nextTick(() => {
      const el = document.querySelector('.link-input') as HTMLInputElement
      el?.focus()
    })
  } else if (tool === 'styles') {
    emit('toggleStyles')
  } else if (tool === 'productGallery') {
    emit('toggleProducts')
  } else if (tool === 'products') {
    productMode.value = true
    nextTick(() => productInputRef.value?.click())
  }
}

function handleModeToolClick(mode: AiMode) {
  if (props.disabled) return

  setMode(mode)
  showToolsMenu.value = false
}

function toggleAddMenu() {
  if (props.disabled) return

  showToolsMenu.value = false
  showAddMenu.value = !showAddMenu.value
}

function toggleToolsMenu() {
  if (props.disabled) return

  showAddMenu.value = false
  showToolsMenu.value = !showToolsMenu.value
}

async function handleProductSelect(e: Event) {
  if (props.disabled) return

  const input = e.target as HTMLInputElement
  if (!input.files) return
  const files = Array.from(input.files).filter(file => file.type.startsWith('image/'))
  const attachments = await withAttachmentTask(Promise.all(files.map(file => createAttachedFile(file, 'image', 'objectUrl'))))
  productImages.value.push(...attachments)
  input.value = ''
}

function removeProductImage(index: number) {
  revokePreview(productImages.value[index]?.preview ?? null)
  productImages.value.splice(index, 1)
  if (productImages.value.length === 0) {
    productMode.value = false
  }
}

function addMoreProductImages() {
  productInputRef.value?.click()
}

function cancelProductMode() {
  productMode.value = false
  clearProductImages()
}

async function handleImageSelect(e: Event) {
  if (props.disabled) return

  const input = e.target as HTMLInputElement
  if (!input.files) return
  await processFiles(Array.from(input.files), 'image')
  input.value = ''
}

async function handleFileSelect(e: Event) {
  if (props.disabled) return

  const input = e.target as HTMLInputElement
  if (!input.files) return
  await processFiles(Array.from(input.files), 'file')
  input.value = ''
}

async function processFiles(files: File[], type: 'image' | 'file') {
  const attachments = await withAttachmentTask(Promise.all(files.map(file => createAttachedFile(file, type))))
  attachedFiles.value.push(...attachments)
}

function removeAttached(index: number) {
  const [removed] = attachedFiles.value.splice(index, 1)
  revokePreview(removed?.preview ?? null)
  if (removed && inpaintSource.value?.file === removed.file) {
    showInpaintEditor.value = false
    inpaintSource.value = null
  }
}

function openInpaintEditor(attachment: AttachedFile) {
  if (props.disabled || isProcessingAttachments.value || attachment.type !== 'image') return
  inpaintSource.value = attachment
  showInpaintEditor.value = true
}

function handleInpaintSubmit(payload: { content: string; image: File; mask: File }) {
  emit('inpaint', payload.content, payload.image, payload.mask)
  message.value = ''
  attachedFiles.value.forEach(attachment => revokePreview(attachment.preview))
  attachedFiles.value = []
  inpaintSource.value = null
  showInpaintEditor.value = false
  showLinkInput.value = false
  linkUrl.value = ''
  nextTick(autoResize)
}

function addLink() {
  if (props.disabled) return

  if (linkUrl.value.trim()) {
    message.value += (message.value ? ' ' : '') + linkUrl.value.trim()
    linkUrl.value = ''
    showLinkInput.value = false
    nextTick(() => textareaRef.value?.focus())
  }
}

function handleLinkKey(e: KeyboardEvent) {
  if (e.key === 'Enter') { e.preventDefault(); addLink() }
  if (e.key === 'Escape') { showLinkInput.value = false; linkUrl.value = '' }
}

function handleDragOver(e: DragEvent) {
  e.preventDefault()
  isDragOver.value = true
}

function handleDragLeave() {
  isDragOver.value = false
}

async function handleDrop(e: DragEvent) {
  e.preventDefault()
  isDragOver.value = false
  if (props.disabled) return

  if (e.dataTransfer?.files?.length) {
    await processFiles(Array.from(e.dataTransfer.files), 'file')
  }
}

const addMenuRef = ref<HTMLDivElement | null>(null)
const toolsMenuRef = ref<HTMLDivElement | null>(null)

function onDocClick(e: MouseEvent) {
  const target = e.target as Node
  if (showAddMenu.value && !addMenuRef.value?.contains(target)) {
    showAddMenu.value = false
  }
  if (showToolsMenu.value && !toolsMenuRef.value?.contains(target)) {
    showToolsMenu.value = false
  }
  if (showAspectRatioPopup.value && !aspectRatioPopupRef.value?.contains(target)) {
    showAspectRatioPopup.value = false
  }
}

onMounted(() => document.addEventListener('pointerdown', onDocClick))
onUnmounted(() => {
  document.removeEventListener('pointerdown', onDocClick)
  clearProductImages()
})

function formatSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}

function selectAspectRatio(value: string) {
  chat.aspectRatio = value
  showAspectRatioPopup.value = false
}

function setMode(mode: 'text' | 'image' | 'video') {
  if (props.disabled) return

  chat.aiMode = mode
  showAspectRatioPopup.value = false
}

function applySuggestionAction(action: SuggestionAction) {
  if (props.disabled) return

  if (action.mode) {
    setMode(action.mode)
  }

  if (typeof action.prompt === 'string') {
    message.value = action.prompt
    nextTick(autoResize)
  }

  showAddMenu.value = false
  showToolsMenu.value = false

  if (action.open === 'upload') {
    imageInputRef.value?.click()
    return
  }

  if (action.open === 'styles') {
    emit('openStyles')
  } else if (action.open === 'products') {
    productMode.value = true
    nextTick(() => productInputRef.value?.click())
  } else if (action.open === 'productGallery') {
    emit('openProducts')
  }

  nextTick(() => textareaRef.value?.focus())
}

defineExpose({
  applySuggestionAction,
})
</script>

<template>
  <div
    class="chat-input-wrapper"
    :class="{ 'drag-over': isDragOver }"
    @dragover="handleDragOver"
    @dragleave="handleDragLeave"
    @drop="handleDrop"
  >
    <!-- Hidden file inputs -->
    <input
      ref="imageInputRef"
      type="file"
      accept="image/*"
      multiple
      :disabled="disabled"
      style="display: none"
      @change="handleImageSelect"
    />
    <input
      ref="fileInputRef"
      type="file"
      multiple
      :disabled="disabled"
      style="display: none"
      @change="handleFileSelect"
    />
    <input
      ref="productInputRef"
      type="file"
      accept="image/*"
      multiple
      :disabled="disabled"
      style="display: none"
      @change="handleProductSelect"
    />

    <!-- Drag overlay -->
    <Transition name="fade">
      <div v-if="isDragOver" class="drag-overlay">
        <i class="pi pi-cloud-upload" />
        <span>{{ t('chat.dropFiles') }}</span>
      </div>
    </Transition>

    <div class="chat-input-box">
      <!-- Attached files preview -->
      <div v-if="attachedFiles.length" class="attached-files">
        <div v-for="(af, idx) in attachedFiles" :key="idx" class="attached-item">
          <img v-if="af.preview" :src="af.preview" class="attached-thumb" />
          <div v-else class="attached-file-icon">
            <i class="pi pi-file" />
          </div>
          <div class="attached-info">
            <span class="attached-name">{{ af.file.name }}</span>
            <span class="attached-size">{{ formatSize(af.file.size) }}</span>
          </div>
          <button
            v-if="af.type === 'image'"
            class="attached-inpaint"
            :title="t('chat.editMaskedArea')"
            :disabled="disabled || isProcessingAttachments"
            @click="openInpaintEditor(af)"
          >
            <i class="pi pi-pencil" />
          </button>
          <button class="attached-remove" @click="removeAttached(idx)">
            <i class="pi pi-times" />
          </button>
        </div>
      </div>

      <!-- Link input -->
      <div v-if="showLinkInput" class="link-input-row">
        <i class="pi pi-link link-icon" />
        <input
          v-model="linkUrl"
          class="link-input"
          :placeholder="t('chat.linkPlaceholder')"
          @keydown="handleLinkKey"
        />
        <Button icon="pi pi-check" severity="secondary" text rounded size="small" @click="addLink" :disabled="!linkUrl.trim()" />
        <Button icon="pi pi-times" severity="secondary" text rounded size="small" @click="showLinkInput = false; linkUrl = ''" />
      </div>

      <!-- Product upload panel -->
      <div v-if="productMode" class="product-upload-panel">
        <div class="product-header">
          <div class="product-header-info">
            <i class="pi pi-box product-icon" />
            <div>
              <span class="product-title">{{ t('chat.productUploadTitle') }}</span>
              <span class="product-desc">{{ t('chat.productUploadDesc') }}</span>
            </div>
          </div>
          <button class="product-close" @click="cancelProductMode">
            <i class="pi pi-times" />
          </button>
        </div>

        <div class="product-images-grid">
          <div v-for="(img, idx) in productImages" :key="idx" class="product-image-item">
            <img v-if="img.preview" :src="img.preview" class="product-thumb" />
            <div v-else class="product-thumb-placeholder">
              <i class="pi pi-image" />
            </div>
            <button class="product-img-remove" @click="removeProductImage(idx)">
              <i class="pi pi-times" />
            </button>
          </div>
          <button class="product-add-btn" @click="addMoreProductImages">
            <i class="pi pi-plus" />
          </button>
        </div>

        <div v-if="productImages.length < 2" class="product-min-warning">
          <i class="pi pi-info-circle" />
          <span>{{ t('chat.productMinImages') }}</span>
        </div>
      </div>

      <!-- Aspect Ratio Selector: popup triggered by button in input row (image mode only) -->

      <div v-if="chat.aiMode === 'video' && !productMode" class="video-options-row">
        <div class="video-option-group" :aria-label="t('chat.videoAspectRatio')">
          <button
            type="button"
            class="video-option-btn"
            :class="{ active: chat.videoAspectRatio === '16:9' }"
            :disabled="disabled"
            :title="t('chat.videoLandscape')"
            @click="chat.videoAspectRatio = '16:9'"
          >
            <i class="pi pi-desktop" />
            <span>16:9</span>
          </button>
          <button
            type="button"
            class="video-option-btn"
            :class="{ active: chat.videoAspectRatio === '9:16' }"
            :disabled="disabled"
            :title="t('chat.videoPortrait')"
            @click="chat.videoAspectRatio = '9:16'"
          >
            <i class="pi pi-mobile" />
            <span>9:16</span>
          </button>
        </div>

        <div class="video-option-group" :aria-label="t('chat.videoDuration')">
          <button
            v-for="duration in durationOptions"
            :key="duration"
            type="button"
            class="video-option-btn"
            :class="{ active: chat.videoDurationSeconds === duration }"
            :disabled="disabled"
            :title="t('chat.videoDurationValue', { seconds: duration })"
            @click="chat.videoDurationSeconds = duration"
          >
            <i class="pi pi-clock" />
            <span>{{ duration }}s</span>
          </button>
        </div>

        <div class="video-option-group" :aria-label="t('chat.videoResolution')">
          <button
            v-for="resolution in resolutionOptions"
            :key="resolution"
            type="button"
            class="video-option-btn"
            :class="{ active: chat.videoResolution === resolution }"
            :disabled="disabled"
            :title="resolution"
            @click="chat.videoResolution = resolution"
          >
            <i class="pi pi-sparkles" />
            <span>{{ resolution }}</span>
          </button>
        </div>

        <button
          type="button"
          class="video-option-btn audio-toggle"
          :class="{ active: chat.videoGenerateAudio }"
          :disabled="disabled"
          :title="t('chat.videoAudio')"
          @click="chat.videoGenerateAudio = !chat.videoGenerateAudio"
        >
          <i :class="chat.videoGenerateAudio ? 'pi pi-volume-up' : 'pi pi-volume-off'" />
          <span>{{ t('chat.videoAudioShort') }}</span>
        </button>
      </div>

      <div class="input-row">
        <!-- Add / input actions -->
        <div class="tools-container">
          <Button
            icon="pi pi-plus"
            severity="secondary"
            text
            rounded
            size="small"
            class="add-btn"
            :class="{ active: showAddMenu }"
            :disabled="disabled"
            @click="toggleAddMenu"
          />
          <Transition name="pop">
            <div v-if="showAddMenu" ref="addMenuRef" class="tools-menu add-menu">
              <div class="tools-menu-section">
                <p class="tools-menu-label">{{ t('chat.inputTools') }}</p>
                <button class="tool-item" @click="handleToolClick('upload')">
                  <i class="pi pi-image" />
                  <span>{{ t('chat.uploadImage') }}</span>
                </button>
                <button class="tool-item" @click="handleToolClick('file')">
                  <i class="pi pi-file" />
                  <span>{{ t('chat.uploadFile') }}</span>
                </button>
                <button class="tool-item" @click="handleToolClick('link')">
                  <i class="pi pi-link" />
                  <span>{{ t('chat.pasteLink') }}</span>
                </button>
                <button class="tool-item" @click="handleToolClick('products')">
                  <i class="pi pi-box" />
                  <span>{{ t('chat.uploadProducts') }}</span>
                </button>
                <button class="tool-item" @click="handleToolClick('styles')">
                  <i class="pi pi-palette" />
                  <span>{{ t('chat.imageStyles') }}</span>
                </button>
                <button class="tool-item" @click="handleToolClick('productGallery')">
                  <i class="pi pi-shopping-bag" />
                  <span>{{ t('chat.productGallery') }}</span>
                </button>
              </div>
            </div>
          </Transition>
        </div>

        <!-- Tools / models only -->
        <div class="tools-container">
          <button
            type="button"
            class="tools-trigger"
            :class="{ active: showToolsMenu }"
            :disabled="disabled"
            :aria-expanded="showToolsMenu"
            :title="t('chat.tools')"
            @click="toggleToolsMenu"
          >
            <span class="tools-trigger-label">
              <i class="pi pi-wrench" />
              <span>{{ t('chat.tools') }}</span>
            </span>
            <span class="tools-trigger-current" :class="`mode-${chat.aiMode}`">
              <i :class="currentModeIcon" />
              <span>{{ currentModeLabel }}</span>
            </span>
            <i class="pi pi-chevron-down tools-trigger-chevron" />
          </button>
          <Transition name="pop">
            <div v-if="showToolsMenu" ref="toolsMenuRef" class="tools-menu">
              <div class="tools-menu-section">
                <p class="tools-menu-label">{{ t('chat.aiModes') }}</p>
                <button class="tool-item" :class="{ active: chat.aiMode === 'text' }" @click="handleModeToolClick('text')">
                  <i class="pi pi-comments" />
                  <span>{{ t('chat.modeText') }}</span>
                </button>
                <button class="tool-item" :class="{ active: chat.aiMode === 'image' }" @click="handleModeToolClick('image')">
                  <i class="pi pi-image" />
                  <span>{{ t('chat.modeImage') }}</span>
                </button>
                <button class="tool-item" :class="{ active: chat.aiMode === 'video' }" @click="handleModeToolClick('video')">
                  <i class="pi pi-video" />
                  <span>{{ t('chat.modeVideo') }}</span>
                </button>
              </div>
            </div>
          </Transition>
        </div>

        <!-- Textarea -->
        <textarea
          ref="textareaRef"
          v-model="message"
          rows="1"
          class="chat-textarea"
          :placeholder="productMode ? t('chat.productPromptPlaceholder') : t('chat.placeholder')"
          :disabled="disabled || isProcessingAttachments"
          @keydown="handleKeydown"
        />

        <!-- Aspect Ratio button (image mode only) -->
        <div v-if="chat.aiMode === 'image'" class="aspect-ratio-container">
          <button
            class="aspect-ratio-btn"
            :class="{ active: showAspectRatioPopup }"
            :disabled="disabled"
            :title="t('chat.aspectRatio')"
            @click.stop="showAspectRatioPopup = !showAspectRatioPopup"
          >
            <i class="pi pi-objects-column" />
            <span class="ar-current-label">{{ chat.aspectRatio }}</span>
          </button>
          <Transition name="pop">
            <div v-if="showAspectRatioPopup" ref="aspectRatioPopupRef" class="aspect-ratio-popup">
              <AspectRatioSelector :model-value="chat.aspectRatio" @update:model-value="selectAspectRatio" />
            </div>
          </Transition>
        </div>
        <Button
          icon="pi pi-arrow-up"
          rounded
          size="small"
          class="send-btn"
          :disabled="productMode ? (productImages.length < 2 || !message.trim() || disabled || isProcessingAttachments) : ((!message.trim() && !attachedFiles.length) || disabled || isProcessingAttachments)"
          @click="handleSend"
        />
      </div>
    </div>

    <InpaintingEditor
      :visible="showInpaintEditor"
      :source-file="inpaintSourceFile"
      :source-url="inpaintSourceUrl"
      :initial-prompt="message"
      :disabled="disabled"
      @close="showInpaintEditor = false"
      @submit="handleInpaintSubmit"
    />
  </div>
</template>

<style scoped>
.chat-input-wrapper {
  position: relative;
  width: 100%;
  max-width: 720px;
  margin: 0 auto;
  padding: 0 16px 12px;
}

.drag-over .chat-input-box {
  border-color: var(--active-color);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.drag-overlay {
  position: absolute;
  inset: 0;
  background: rgba(99, 102, 241, 0.08);
  border: 2px dashed var(--active-color);
  border-radius: 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  z-index: 10;
  color: var(--active-color);
  font-size: 0.85rem;
  font-weight: 600;
  backdrop-filter: blur(4px);
}

.drag-overlay i {
  font-size: 1.5rem;
}

.chat-input-box {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 18px;
  padding: 6px 6px 6px 10px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.chat-input-box:focus-within {
  border-color: var(--active-color);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.08);
}

.input-row {
  display: flex;
  align-items: flex-end;
  gap: 4px;
}

.tools-container {
  position: relative;
  flex-shrink: 0;
}

.add-btn {
  width: 30px !important;
  height: 30px !important;
  color: var(--text-muted) !important;
  transition: transform 0.2s, color 0.2s !important;
}

.add-btn.active {
  transform: rotate(45deg);
  color: var(--active-color) !important;
}

.tools-trigger {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 30px;
  padding: 0 10px;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--hover-bg);
  color: var(--text-secondary);
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s, color 0.2s;
}

.tools-trigger:hover,
.tools-trigger.active {
  border-color: var(--active-color);
  color: var(--text-primary);
}

.tools-trigger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.tools-trigger-label,
.tools-trigger-current {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
}

.tools-trigger-label {
  font-size: 0.74rem;
  font-weight: 700;
}

.tools-trigger-current {
  padding: 2px 6px;
  border-radius: 999px;
  background: rgba(148, 163, 184, 0.12);
  font-size: 0.68rem;
  font-weight: 700;
}

.tools-trigger-current.mode-image {
  color: #8b5cf6;
  background: rgba(139, 92, 246, 0.12);
}

.tools-trigger-current.mode-video {
  color: #0d9488;
  background: rgba(20, 184, 166, 0.12);
}

.tools-trigger-current.mode-text {
  color: var(--active-color);
  background: var(--active-bg);
}

.tools-trigger-chevron {
  font-size: 0.72rem;
}

.video-options-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  padding: 8px 4px;
  border-bottom: 1px solid var(--card-border);
  margin-bottom: 4px;
}

.video-option-group {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 2px;
  border-radius: 10px;
  background: var(--hover-bg);
}

.video-option-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-width: 34px;
  height: 26px;
  padding: 3px 8px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--text-secondary);
  font-size: 0.68rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.2s, color 0.2s, box-shadow 0.2s;
}

.video-option-btn:hover,
.video-option-btn.active {
  background: rgba(20, 184, 166, 0.1);
  color: #0d9488;
}

.video-option-btn.active {
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
}

.video-option-btn i {
  font-size: 0.72rem;
}

.audio-toggle {
  background: var(--hover-bg);
}

.tools-menu {
  position: absolute;
  bottom: 100%;
  inset-inline-start: 0;
  margin-bottom: 8px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 14px;
  padding: 6px;
  min-width: 200px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
  z-index: 20;
}

.add-menu {
  min-width: 200px;
}

.tools-menu-section {
  display: grid;
  gap: 2px;
}

.tools-menu-label {
  margin: 0;
  padding: 6px 10px 4px;
  color: var(--text-muted);
  font-size: 0.66rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.tools-menu-divider {
  height: 1px;
  margin: 6px 4px;
  background: var(--card-border);
}

.tool-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 12px;
  border: none;
  background: none;
  color: var(--text-secondary);
  font-size: 0.82rem;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.14s, color 0.14s;
}

.tool-item:hover {
  background: var(--hover-bg);
  color: var(--text-primary);
}

.tool-item.active {
  background: var(--active-bg);
  color: var(--active-color);
}

.tool-item i {
  font-size: 0.9rem;
  width: 20px;
  text-align: center;
}

.chat-textarea {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 0.84rem;
  color: var(--text-primary);
  padding: 5px 4px;
  resize: none;
  min-height: 30px;
  max-height: 180px;
  line-height: 1.5;
  font-family: inherit;
}

.chat-textarea::placeholder {
  color: var(--text-muted);
}

/* Aspect Ratio button & popup */
.aspect-ratio-container {
  position: relative;
  flex-shrink: 0;
}

.aspect-ratio-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border: 1px solid var(--card-border);
  border-radius: 8px;
  background: var(--hover-bg);
  color: var(--text-muted);
  font-size: 0.68rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  height: 30px;
  white-space: nowrap;
}

.aspect-ratio-btn:hover {
  border-color: #8b5cf6;
  color: #8b5cf6;
  background: rgba(139, 92, 246, 0.08);
}

.aspect-ratio-btn.active {
  border-color: #8b5cf6;
  color: #8b5cf6;
  background: rgba(139, 92, 246, 0.1);
}

.aspect-ratio-btn i {
  font-size: 0.78rem;
}

.ar-current-label {
  line-height: 1;
}

.aspect-ratio-popup {
  position: absolute;
  bottom: 100%;
  inset-inline-end: 0;
  margin-bottom: 8px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 14px;
  padding: 8px 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
  z-index: 20;
  white-space: nowrap;
}

.send-btn {
  flex-shrink: 0;
  width: 30px !important;
  height: 30px !important;
  transition: transform 0.15s !important;
}

.send-btn:not(:disabled):hover {
  transform: scale(1.05);
}

.send-btn:not(:disabled):active {
  transform: scale(0.95);
}

/* Attached files */
.attached-files {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 8px 4px;
  border-bottom: 1px solid var(--card-border);
  margin-bottom: 4px;
}

.attached-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  background: var(--hover-bg);
  border-radius: 10px;
  max-width: 220px;
  animation: attachPop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes attachPop {
  from { transform: scale(0.9); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.attached-thumb {
  width: 36px;
  height: 36px;
  border-radius: 6px;
  object-fit: cover;
  flex-shrink: 0;
}

.attached-file-icon {
  width: 36px;
  height: 36px;
  border-radius: 6px;
  background: var(--active-bg);
  color: var(--active-color);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  flex-shrink: 0;
}

.attached-info {
  min-width: 0;
  flex: 1;
}

.attached-name {
  display: block;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.attached-size {
  display: block;
  font-size: 0.62rem;
  color: var(--text-muted);
}

.attached-inpaint,
.attached-remove {
  border: none;
  background: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 2px;
  border-radius: 4px;
  font-size: 0.65rem;
  flex-shrink: 0;
  transition: color 0.14s, background 0.14s;
}

.attached-inpaint:hover:not(:disabled) {
  color: #0284c7;
  background: rgba(14, 165, 233, 0.1);
}

.attached-inpaint:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.attached-remove:hover {
  color: #ef4444;
  background: rgba(239, 68, 68, 0.08);
}

/* Link input */
.link-input-row {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 4px;
  border-bottom: 1px solid var(--card-border);
  margin-bottom: 4px;
}

.link-icon {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.link-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 0.82rem;
  color: var(--text-primary);
  padding: 4px 0;
  min-width: 0;
}

.link-input::placeholder {
  color: var(--text-muted);
}

/* Animations */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.pop-enter-active {
  transition: opacity 0.15s, transform 0.15s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.pop-leave-active {
  transition: opacity 0.1s, transform 0.1s;
}

.pop-enter-from {
  opacity: 0;
  transform: scale(0.9) translateY(8px);
}

.pop-leave-to {
  opacity: 0;
  transform: scale(0.95) translateY(4px);
}

/* ── Product Upload Panel ── */
.product-upload-panel {
  padding: 10px 4px;
  border-bottom: 1px solid var(--card-border);
  margin-bottom: 4px;
  animation: attachPop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.product-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 10px;
}

.product-header-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.product-icon {
  font-size: 1rem;
  color: var(--active-color);
  background: var(--active-bg);
  padding: 8px;
  border-radius: 8px;
  flex-shrink: 0;
}

.product-title {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-primary);
}

.product-desc {
  display: block;
  font-size: 0.66rem;
  color: var(--text-muted);
  margin-top: 1px;
}

.product-close {
  border: none;
  background: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  font-size: 0.7rem;
  transition: color 0.14s, background 0.14s;
}

.product-close:hover {
  color: #ef4444;
  background: rgba(239, 68, 68, 0.08);
}

.product-images-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.product-image-item {
  position: relative;
  width: 72px;
  height: 72px;
  border-radius: 10px;
  overflow: hidden;
  animation: attachPop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.product-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.product-thumb-placeholder {
  width: 100%;
  height: 100%;
  background: var(--hover-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  font-size: 1.2rem;
}

.product-img-remove {
  position: absolute;
  top: 3px;
  inset-inline-end: 3px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: none;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  font-size: 0.5rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.14s;
}

.product-image-item:hover .product-img-remove {
  opacity: 1;
}

.product-add-btn {
  width: 72px;
  height: 72px;
  border-radius: 10px;
  border: 2px dashed var(--card-border);
  background: none;
  color: var(--text-muted);
  font-size: 1rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color 0.14s, color 0.14s, background 0.14s;
}

.product-add-btn:hover {
  border-color: var(--active-color);
  color: var(--active-color);
  background: var(--active-bg);
}

.product-min-warning {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  font-size: 0.68rem;
  color: #f59e0b;
}

.product-min-warning i {
  font-size: 0.72rem;
}

@media (max-width: 640px) {
  .chat-input-wrapper {
    padding: 0 8px 12px;
  }

  .chat-input-box {
    border-radius: 16px;
  }

  .product-image-item,
  .product-add-btn {
    width: 60px;
    height: 60px;
  }

  .product-img-remove {
    opacity: 1;
  }

  .tools-menu {
    min-width: 220px;
  }

  .video-options-row {
    gap: 5px;
  }

  .video-option-btn {
    padding-inline: 7px;
  }

  .tools-trigger {
    padding-inline: 8px;
    gap: 6px;
  }

  .add-menu {
    min-width: 180px;
  }

  .tools-trigger-label span {
    display: none;
  }
}
</style>
