<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'

const { t } = useI18n()

const prompt = ref('')
const inputRef = ref<HTMLInputElement | null>(null)

const suggestions = computed(() => [
  { icon: 'pi pi-image', label: t('client.suggestDesign'), color: '#8b5cf6' },
  { icon: 'pi pi-pencil', label: t('client.suggestEdit'), color: '#0ea5e9' },
  { icon: 'pi pi-palette', label: t('client.suggestStyle'), color: '#f59e0b' },
  { icon: 'pi pi-sparkles', label: t('client.suggestCreate'), color: '#10b981' },
])

function useSuggestion(text: string) {
  prompt.value = text
  nextTick(() => {
    inputRef.value?.focus()
  })
}

function handleSend() {
  if (!prompt.value.trim()) return
  // Future: send prompt to AI
  prompt.value = ''
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    handleSend()
  }
}
</script>

<template>
  <div class="home-page">
    <div class="home-center">
      <!-- Logo -->
      <div class="hero-logo">
        <div class="logo-circle">
          <i class="pi pi-sparkles" />
        </div>
      </div>

      <!-- Heading -->
      <h1 class="hero-title">{{ t('client.heroTitle') }}</h1>
      <p class="hero-sub">{{ t('client.heroSub') }}</p>

      <!-- Prompt box -->
      <div class="prompt-box">
        <div class="prompt-row">
          <Button icon="pi pi-plus" severity="secondary" text rounded size="small" class="attach-btn" />
          <input
            ref="inputRef"
            v-model="prompt"
            type="text"
            class="prompt-input"
            :placeholder="t('client.promptPlaceholder')"
            @keydown="handleKeydown"
          />
          <Button icon="pi pi-microphone" severity="secondary" text rounded size="small" class="mic-btn" />
          <Button
            icon="pi pi-arrow-up"
            :disabled="!prompt.trim()"
            rounded
            size="small"
            class="send-btn"
            @click="handleSend"
          />
        </div>
      </div>

      <!-- Suggestions -->
      <div class="suggestions">
        <button
          v-for="(s, i) in suggestions"
          :key="i"
          class="suggestion-chip"
          @click="useSuggestion(s.label)"
        >
          <i :class="s.icon" :style="{ color: s.color }" />
          <span>{{ s.label }}</span>
        </button>
      </div>
    </div>

    <!-- Footer -->
    <footer class="home-footer">
      <span>{{ t('client.footerDisclaimer') }}</span>
    </footer>
  </div>
</template>

<style scoped>
.home-page {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  min-height: calc(100vh - 52px);
}

.home-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  max-width: 680px;
  width: 100%;
  gap: 16px;
}

/* Logo */
.hero-logo {
  margin-bottom: 8px;
}

.logo-circle {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.4rem;
}

/* Heading */
.hero-title {
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
  text-align: center;
}

.hero-sub {
  font-size: 0.88rem;
  color: var(--text-muted);
  margin: 0;
  text-align: center;
  max-width: 460px;
}

/* Prompt */
.prompt-box {
  width: 100%;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 16px;
  padding: 6px;
  margin-top: 8px;
  transition: border-color 0.2s;
}

.prompt-box:focus-within {
  border-color: var(--active-color);
}

.prompt-row {
  display: flex;
  align-items: center;
  gap: 4px;
}

.prompt-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 0.92rem;
  color: var(--text-primary);
  padding: 10px 8px;
  min-width: 0;
}

.prompt-input::placeholder {
  color: var(--text-muted);
}

.attach-btn,
.mic-btn {
  flex-shrink: 0;
  color: var(--text-muted) !important;
}

.send-btn {
  flex-shrink: 0;
  width: 36px !important;
  height: 36px !important;
}

/* Suggestions */
.suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: center;
  margin-top: 4px;
}

.suggestion-chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 16px;
  border-radius: 12px;
  border: 1px solid var(--card-border);
  background: var(--card-bg);
  color: var(--text-secondary);
  font-size: 0.78rem;
  cursor: pointer;
  transition: border-color 0.14s, background 0.14s;
  white-space: nowrap;
}

.suggestion-chip:hover {
  border-color: var(--active-color);
  background: var(--active-bg);
  color: var(--text-primary);
}

.suggestion-chip i {
  font-size: 0.82rem;
}

/* Footer */
.home-footer {
  padding: 16px;
  text-align: center;
  font-size: 0.68rem;
  color: var(--text-muted);
  margin-top: auto;
}

@media (max-width: 640px) {
  .hero-title {
    font-size: 1.3rem;
  }

  .suggestions {
    gap: 6px;
  }

  .suggestion-chip {
    font-size: 0.72rem;
    padding: 6px 12px;
  }
}
</style>
