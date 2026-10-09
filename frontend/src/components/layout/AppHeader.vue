<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useCartStore } from '../../stores/cart'
import { useAuthStore } from '../../stores/auth'

const cartStore = useCartStore()
const authStore = useAuthStore()
const router = useRouter()

const navLinks = [
  { label: 'Catalog', to: '/' },
  { label: 'Admin', to: '/admin' },
]

function handleLogout(): void {
  authStore.logout()
  router.push('/')
}
</script>

<template>
  <header class="app-header">
    <RouterLink to="/" class="app-header__brand">Commerce Platform</RouterLink>
    <nav class="app-header__nav">
      <RouterLink v-for="link in navLinks" :key="link.to" :to="link.to">
        {{ link.label }}
      </RouterLink>
      <RouterLink to="/cart" class="app-header__cart">
        Cart
        <span v-if="cartStore.totalItems > 0" class="app-header__cart-badge">{{ cartStore.totalItems }}</span>
      </RouterLink>

      <span v-if="authStore.isAuthenticated" class="app-header__session">
        <span class="app-header__session-email">{{ authStore.userEmail }}</span>
        <button type="button" class="app-header__logout" @click="handleLogout">Log out</button>
      </span>
      <RouterLink v-else to="/login">Log in</RouterLink>
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

.app-header__cart {
  display: inline-flex;
  align-items: center;
  gap: var(--space-xs);
}

.app-header__cart-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--color-accent-600);
  color: var(--color-neutral-0);
  font-size: 11px;
  font-weight: 700;
}

.app-header__session {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
}

.app-header__session-email {
  font-size: 13px;
  color: var(--color-text-muted);
}

.app-header__logout {
  background: none;
  border: none;
  color: var(--color-accent-600);
  font-weight: 500;
  font-size: 14px;
  cursor: pointer;
  padding: 0;
}

@media (max-width: 640px) {
  .app-header {
    flex-wrap: wrap;
    gap: var(--space-sm);
    padding: var(--space-md);
  }

  .app-header__brand {
    font-size: 16px;
    white-space: nowrap;
  }

  .app-header__nav {
    flex-wrap: wrap;
    gap: var(--space-sm) var(--space-md);
    row-gap: var(--space-xs);
    font-size: 14px;
  }

  .app-header__session-email {
    display: none;
  }
}
</style>
