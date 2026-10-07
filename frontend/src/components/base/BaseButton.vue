<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary'
    type?: 'button' | 'submit'
    disabled?: boolean
  }>(),
  { variant: 'primary', type: 'button' },
)
</script>

<template>
  <button class="base-button" :class="`base-button--${variant}`" :type="type" :disabled="disabled">
    <slot />
  </button>
</template>

<style scoped>
.base-button {
  font-family: var(--font-sans);
  font-weight: 600;
  font-size: 14px;
  padding: var(--space-sm) var(--space-lg);
  border-radius: var(--radius-md);
  border: 1px solid transparent;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.base-button--primary {
  background: var(--color-accent-600);
  color: var(--color-neutral-0);
}

.base-button--primary:hover {
  background: var(--color-accent-700);
}

.base-button--secondary {
  background: var(--color-surface);
  color: var(--color-text-heading);
  border-color: var(--color-border);
}

.base-button--secondary:hover {
  border-color: var(--color-accent-500);
}
</style>
