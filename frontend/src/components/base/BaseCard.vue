<script setup lang="ts"></script>

<template>
  <div class="base-card">
    <slot />
  </div>
</template>

<style scoped>
.base-card {
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-lg);
  transition: box-shadow 0.15s ease;
}

.base-card:hover {
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}
</style>
