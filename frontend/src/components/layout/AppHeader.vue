<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useCartStore } from '../../stores/cart'
import { useAuthStore } from '../../stores/auth'
import { setLocale, type SupportedLocale } from '../../i18n'
import { useTheme } from '../../composables/useTheme'

const cartStore = useCartStore()
const authStore = useAuthStore()
const router = useRouter()
const { t, locale } = useI18n()
const { theme, toggleTheme } = useTheme()

const navLinks = computed(() => [
  { label: t('nav.catalog'), to: '/' },
  { label: t('nav.admin'), to: '/admin' },
  { label: t('nav.adminCatalog'), to: '/admin/catalog' },
])

function handleLogout(): void {
  authStore.logout()
  router.push('/')
}

function handleLocaleChange(event: Event): void {
  setLocale((event.target as HTMLSelectElement).value as SupportedLocale)
}
</script>

<template>
  <header class="app-header">
    <RouterLink to="/" class="app-header__brand">{{ t('login.brandTitle') }}</RouterLink>
    <nav class="app-header__nav">
      <RouterLink v-for="link in navLinks" :key="link.to" :to="link.to">
        {{ link.label }}
      </RouterLink>
      <RouterLink to="/cart" class="app-header__cart">
        {{ t('nav.cart') }}
        <span v-if="cartStore.totalItems > 0" class="app-header__cart-badge">{{ cartStore.totalItems }}</span>
      </RouterLink>

      <span v-if="authStore.isAuthenticated" class="app-header__session">
        <span class="app-header__session-email">{{ authStore.userEmail }}</span>
        <button type="button" class="app-header__logout" @click="handleLogout">{{ t('nav.logOut') }}</button>
      </span>
      <RouterLink v-else to="/login">{{ t('nav.logIn') }}</RouterLink>

      <select
        class="app-header__locale"
        :value="locale"
        aria-label="Language"
        @change="handleLocaleChange"
      >
        <option value="en">EN</option>
        <option value="es">ES</option>
      </select>

      <button
        type="button"
        class="app-header__theme-toggle"
        :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="toggleTheme"
      >
        {{ theme === 'dark' ? '☀️' : '🌙' }}
      </button>
    </nav>
  </header>
</template>

<style scoped>
.app-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-md) var(--space-xl);
  background: var(--glass-bg);
  backdrop-filter: blur(var(--glass-blur));
  -webkit-backdrop-filter: blur(var(--glass-blur));
  border-bottom: 1px solid var(--glass-border);
}

.app-header__brand {
  font-weight: 800;
  font-size: 18px;
  color: var(--color-text-heading);
  text-decoration: none;
}

.app-header__nav {
  display: flex;
  align-items: center;
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

.app-header__locale {
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: 600;
  padding: 2px var(--space-xs);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: var(--color-bg);
  color: var(--color-text);
  cursor: pointer;
}

.app-header__theme-toggle {
  font-size: 16px;
  line-height: 1;
  padding: var(--space-xs);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: var(--color-bg);
  cursor: pointer;
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
