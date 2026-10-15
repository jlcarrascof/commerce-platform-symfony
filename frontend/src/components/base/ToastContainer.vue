<script setup lang="ts">
import { useToast } from '../../composables/useToast'

const { toasts, dismiss } = useToast()
</script>

<template>
  <div class="toast-container">
    <TransitionGroup name="toast" tag="div">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="toast"
        :class="`toast--${toast.type}`"
        role="status"
        @click="dismiss(toast.id)"
      >
        {{ toast.message }}
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-container {
  position: fixed;
  top: var(--space-lg);
  right: var(--space-lg);
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  max-width: 360px;
}

.toast {
  padding: var(--space-sm) var(--space-md);
  border-radius: var(--radius-md);
  font-family: var(--font-sans);
  font-size: 14px;
  font-weight: 500;
  color: #fff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  cursor: pointer;
}

.toast--created {
  background: var(--color-success);
}

.toast--updated {
  background: var(--color-info);
}

.toast--deleted {
  background: var(--color-error);
}

.toast--restored {
  background: var(--color-accent-600);
}

.toast--error {
  background: var(--color-warning);
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.25s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(24px);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(24px);
}

@media (max-width: 768px) {
  .toast-container {
    left: var(--space-md);
    right: var(--space-md);
    max-width: none;
  }
}
</style>
