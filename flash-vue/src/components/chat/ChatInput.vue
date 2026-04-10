<script setup lang="ts">
import { ref, nextTick, watch, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useChatStore } from '@/stores/chat'
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
  toggleStyles: []
  toggleProducts: []
  openUpload: []
}>()

interface AttachedFile {
  file: File
  preview: string | null
  type: 'image' | 'file'
}

const message = ref('')
const textareaRef = ref<HTMLTextAreaElement | null>(null)
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
    if (productImages.value.length < 2) return
    if (!text) return
    const files = productImages.value.map(af => af.file)
    emit('sendProducts', text, files)
    message.value = ''
    productImages.value = []
    productMode.value = false
    nextTick(autoResize)
    return
  }

  if (!text && attachedFiles.value.length === 0) return
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
  showToolsMenu.value = false
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

function handleProductSelect(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files) return
  for (const file of Array.from(input.files)) {
    if (!file.type.startsWith('image/')) continue
    const preview = URL.createObjectURL(file)
    productImages.value.push({ file, preview, type: 'image' })
  }
  input.value = ''
}

function removeProductImage(index: number) {
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
  productImages.value = []
}

function handleImageSelect(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files) return
  processFiles(Array.from(input.files), 'image')
  input.value = ''
}

function handleFileSelect(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files) return
  processFiles(Array.from(input.files), 'file')
  input.value = ''
}

function processFiles(files: File[], type: 'image' | 'file') {
  for (const file of files) {
    const isImage = file.type.startsWith('image/')
    const attached: AttachedFile = {
      file,
      preview: null,
      type: isImage ? 'image' : type,
    }
    if (isImage) {
      const reader = new FileReader()
      reader.onload = (ev) => {
        attached.preview = ev.target?.result as string
      }
      reader.readAsDataURL(file)
    }
    attachedFiles.value.push(attached)
  }
}

function removeAttached(index: number) {
  attachedFiles.value.splice(index, 1)
}

function addLink() {
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

function handleDrop(e: DragEvent) {
  e.preventDefault()
  isDragOver.value = false
  if (e.dataTransfer?.files?.length) {
    processFiles(Array.from(e.dataTransfer.files), 'file')
  }
}

const toolsMenuRef = ref<HTMLDivElement | null>(null)

function onDocClick(e: MouseEvent) {
  if (!showToolsMenu.value) return
  const target = e.target as Node
  if (toolsMenuRef.value?.contains(target)) return
  showToolsMenu.value = false
}

onMounted(() => document.addEventListener('pointerdown', onDocClick))
onUnmounted(() => document.removeEventListener('pointerdown', onDocClick))

function formatSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
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
      style="display: none"
      @change="handleImageSelect"
    />
    <input
      ref="fileInputRef"
      type="file"
      multiple
      style="display: none"
      @change="handleFileSelect"
    />
    <input
      ref="productInputRef"
      type="file"
      accept="image/*"
      multiple
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

      <div class="input-row">
        <!-- Tools button -->
        <div class="tools-container">
          <Button
            icon="pi pi-plus"
            severity="secondary"
            text
            rounded
            size="small"
            class="tools-btn"
            :class="{ active: showToolsMenu }"
            @click="showToolsMenu = !showToolsMenu"
          />
          <Transition name="pop">
            <div v-if="showToolsMenu" ref="toolsMenuRef" class="tools-menu">
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
          </Transition>
        </div>

        <!-- Mode toggle -->
        <button
          class="mode-toggle"
          :class="{ 'mode-image': chat.aiMode === 'image' }"
          :title="chat.aiMode === 'text' ? t('chat.switchToImage') : t('chat.switchToText')"
          @click="chat.aiMode = chat.aiMode === 'text' ? 'image' : 'text'"
        >
          <i :class="chat.aiMode === 'text' ? 'pi pi-comments' : 'pi pi-image'" />
          <span class="mode-label">{{ chat.aiMode === 'text' ? t('chat.modeText') : t('chat.modeImage') }}</span>
        </button>

        <!-- Textarea -->
        <textarea
          ref="textareaRef"
          v-model="message"
          rows="1"
          class="chat-textarea"
          :placeholder="productMode ? t('chat.productPromptPlaceholder') : t('chat.placeholder')"
          :disabled="disabled"
          @keydown="handleKeydown"
        />

        <!-- Action buttons -->
        <Button
          icon="pi pi-microphone"
          severity="secondary"
          text
          rounded
          size="small"
          class="mic-btn"
        />
        <Button
          icon="pi pi-arrow-up"
          rounded
          size="small"
          class="send-btn"
          :disabled="productMode ? (productImages.length < 2 || !message.trim()) : ((!message.trim() && !attachedFiles.length) || disabled)"
          @click="handleSend"
        />
      </div>
    </div>
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

.tools-btn {
  width: 30px !important;
  height: 30px !important;
  color: var(--text-muted) !important;
  transition: transform 0.2s, color 0.2s !important;
}

.tools-btn.active {
  transform: rotate(45deg);
  color: var(--active-color) !important;
}

/* Mode toggle */
.mode-toggle {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: var(--hover-bg);
  color: var(--text-secondary);
  font-size: 0.7rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  flex-shrink: 0;
  height: 30px;
}

.mode-toggle:hover {
  border-color: var(--active-color);
  color: var(--active-color);
  background: var(--active-bg);
}

.mode-toggle.mode-image {
  border-color: #8b5cf6;
  background: rgba(139, 92, 246, 0.08);
  color: #8b5cf6;
}

.mode-toggle.mode-image:hover {
  background: rgba(139, 92, 246, 0.14);
}

.mode-toggle i {
  font-size: 0.78rem;
}

.mode-label {
  line-height: 1;
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

.mic-btn {
  flex-shrink: 0;
  width: 30px !important;
  height: 30px !important;
  color: var(--text-muted) !important;
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
    min-width: 180px;
  }
}
</style>
