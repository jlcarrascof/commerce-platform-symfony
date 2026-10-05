<script setup lang="ts">
const navLinks = [
  { label: 'Catalog', to: '/' },
  { label: 'Cart', to: '/cart' },
  { label: 'Admin', to: '/admin' },
]
</script>

<template>
  <header class="app-header">
    <RouterLink to="/" class="app-header__brand">Commerce Platform</RouterLink>
    <nav class="app-header__nav">
      <RouterLink v-for="link in navLinks" :key="link.to" :to="link.to">
        {{ link.label }}
      </RouterLink>
    </nav>
  </header>
</template>

<style scoped>
.app-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-md) var(--space-xl);
  border-bottom: 1px solid var(--color-border);
}

.app-header__brand {
  font-weight: 800;
  font-size: 18px;
  color: var(--color-text-heading);
  text-decoration: none;
}

.app-header__nav {
  display: flex;
  gap: var(--space-lg);
}

.app-header__nav a {
  color: var(--color-text-muted);
  text-decoration: none;
  font-weight: 500;
}

.app-header__nav a.router-link-active {
  color: var(--color-accent-600);
}
</style>
