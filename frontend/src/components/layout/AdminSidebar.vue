<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const links = [
  { label: () => t('admin.orders'), to: '/admin' },
  { label: () => t('nav.adminCatalog'), to: '/admin/catalog' },
  { label: () => t('nav.adminUsers'), to: '/admin/users' },
]
</script>

<template>
  <aside class="admin-sidebar">
    <nav class="admin-sidebar__nav">
      <RouterLink
        v-for="link in links"
        :key="link.to"
        :to="link.to"
        class="admin-sidebar__link"
        exact-active-class="admin-sidebar__link--active"
      >
        {{ link.label() }}
      </RouterLink>
    </nav>
  </aside>
</template>

<style scoped>
.admin-sidebar {
  width: 200px;
  flex-shrink: 0;
  padding: var(--space-lg) var(--space-md);
  border-right: 1px solid var(--color-border);
}

.admin-sidebar__nav {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.admin-sidebar__link {
  padding: var(--space-sm) var(--space-md);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-muted);
  text-decoration: none;
}

.admin-sidebar__link:hover {
  background: var(--color-surface);
  color: var(--color-text-heading);
}

.admin-sidebar__link--active {
  background: color-mix(in srgb, var(--color-accent-600) 12%, transparent);
  color: var(--color-accent-text);
  font-weight: 700;
}

@media (max-width: 768px) {
  .admin-sidebar {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid var(--color-border);
    padding: var(--space-sm) var(--space-md);
  }

  .admin-sidebar__nav {
    flex-direction: row;
    overflow-x: auto;
  }
}
</style>
