<script setup lang="ts">
</script>

<template>
  <div class="typing-indicator">
    <div class="typing-avatar">
      <i class="pi pi-sparkles" />
    </div>
    <div class="typing-bubble">
      <span class="dot" />
      <span class="dot" />
      <span class="dot" />
    </div>
  </div>
</template>

<style scoped>
.typing-indicator {
  display: flex;
  gap: 12px;
  max-width: 780px;
  margin: 0 auto;
  padding: 8px 16px;
  animation: fadeIn 0.3s;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

.typing-avatar {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.78rem;
  margin-top: 4px;
}

.typing-bubble {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 14px 20px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 18px;
  border-start-start-radius: 4px;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--text-muted);
  animation: dotBounce 1.4s infinite ease-in-out;
}

.dot:nth-child(1) { animation-delay: 0s; }
.dot:nth-child(2) { animation-delay: 0.2s; }
.dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes dotBounce {
  0%, 80%, 100% {
    transform: scale(0.6);
    opacity: 0.4;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>
