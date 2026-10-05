<script setup lang="ts">
const year = new Date().getFullYear()
</script>

<template>
  <footer class="app-footer">
    <p>&copy; {{ year }} Commerce Platform</p>
  </footer>
</template>

<style scoped>
.app-footer {
  padding: var(--space-lg) var(--space-xl);
  border-top: 1px solid var(--color-border);
  text-align: center;
}

.app-footer p {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 14px;
}
</style>
