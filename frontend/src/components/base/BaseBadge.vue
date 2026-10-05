<script setup lang="ts">
withDefaults(
  defineProps<{
    tone?: 'neutral' | 'success' | 'error' | 'warning'
  }>(),
  { tone: 'neutral' },
)
</script>

<template>
  <span class="base-badge" :class="`base-badge--${tone}`">
    <slot />
  </span>
</template>

<style scoped>
.base-badge {
  display: inline-flex;
  align-items: center;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 2px var(--space-sm);
  border-radius: var(--radius-sm);
}

.base-badge--neutral {
  background: var(--color-surface);
  color: var(--color-text-muted);
}

.base-badge--success {
  background: color-mix(in srgb, var(--color-success) 15%, transparent);
  color: var(--color-success);
}

.base-badge--error {
  background: color-mix(in srgb, var(--color-error) 15%, transparent);
  color: var(--color-error);
}

.base-badge--warning {
  background: color-mix(in srgb, var(--color-warning) 15%, transparent);
  color: var(--color-warning);
}
</style>
