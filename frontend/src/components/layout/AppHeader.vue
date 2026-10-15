<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useCartStore } from '../../stores/cart'
import { useAuthStore } from '../../stores/auth'
import { setLocale, SUPPORTED_LOCALES, type SupportedLocale } from '../../i18n'
import { useTheme } from '../../composables/useTheme'

const cartStore = useCartStore()
const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const { t, locale } = useI18n()
const { theme, toggleTheme } = useTheme()

const adminLinks = computed(() => [
  { label: t('admin.orders'), to: '/admin' },
  { label: t('nav.adminCatalog'), to: '/admin/catalog' },
  { label: t('nav.adminUsers'), to: '/admin/users' },
])
const isAdminRouteActive = computed(() => route.path.startsWith('/admin'))

const LOCALE_FLAGS: Record<SupportedLocale, { flag: string; label: string }> = {
  en: { flag: 'https://flagcdn.com/us.svg', label: 'English' },
  es: { flag: 'https://flagcdn.com/es.svg', label: 'Español' },
}

const isLocaleMenuOpen = ref(false)
const localeMenuRef = ref<HTMLElement | null>(null)
const isAdminMenuOpen = ref(false)
const adminMenuRef = ref<HTMLElement | null>(null)

function handleLogout(): void {
  authStore.logout()
  router.push('/')
}

function selectLocale(value: SupportedLocale): void {
  setLocale(value)
  isLocaleMenuOpen.value = false
}

function handleClickOutside(event: MouseEvent): void {
  if (localeMenuRef.value && !localeMenuRef.value.contains(event.target as Node)) {
    isLocaleMenuOpen.value = false
  }
  if (adminMenuRef.value && !adminMenuRef.value.contains(event.target as Node)) {
    isAdminMenuOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside))
</script>

<template>
  <header class="app-header">
    <RouterLink to="/" class="app-header__brand">{{ t('login.brandTitle') }}</RouterLink>
    <nav class="app-header__nav">
      <RouterLink to="/">{{ t('nav.catalog') }}</RouterLink>

      <div v-if="authStore.isAdmin" ref="adminMenuRef" class="app-header__admin-menu">
        <button
          type="button"
          class="app-header__admin-trigger"
          :class="{ 'app-header__admin-trigger--active': isAdminRouteActive }"
          aria-haspopup="menu"
          :aria-expanded="isAdminMenuOpen"
          @click="isAdminMenuOpen = !isAdminMenuOpen"
        >
          {{ t('nav.admin') }}
          <span class="app-header__admin-caret">▾</span>
        </button>
        <div v-if="isAdminMenuOpen" class="app-header__admin-dropdown" role="menu">
          <RouterLink
            v-for="link in adminLinks"
            :key="link.to"
            :to="link.to"
            class="app-header__admin-item"
            role="menuitem"
            @click="isAdminMenuOpen = false"
          >
            {{ link.label }}
          </RouterLink>
        </div>
      </div>

      <RouterLink to="/cart" class="app-header__cart">
        {{ t('nav.cart') }}
        <span v-if="cartStore.totalItems > 0" class="app-header__cart-badge">{{ cartStore.totalItems }}</span>
      </RouterLink>

      <span v-if="authStore.isAuthenticated" class="app-header__session">
        <span class="app-header__session-email">{{ authStore.userEmail }}</span>
        <button type="button" class="app-header__logout" @click="handleLogout">{{ t('nav.logOut') }}</button>
      </span>
      <RouterLink v-else to="/login">{{ t('nav.logIn') }}</RouterLink>

      <div ref="localeMenuRef" class="app-header__locale">
        <button
          type="button"
          class="app-header__locale-trigger"
          aria-haspopup="listbox"
          :aria-expanded="isLocaleMenuOpen"
          aria-label="Language"
          @click="isLocaleMenuOpen = !isLocaleMenuOpen"
        >
          <img :src="LOCALE_FLAGS[locale as SupportedLocale].flag" :alt="LOCALE_FLAGS[locale as SupportedLocale].label" />
          <span>{{ (locale as SupportedLocale).toUpperCase() }}</span>
        </button>
        <ul v-if="isLocaleMenuOpen" class="app-header__locale-menu" role="listbox">
          <li v-for="code in SUPPORTED_LOCALES" :key="code">
            <button
              type="button"
              class="app-header__locale-option"
              :class="{ 'app-header__locale-option--active': code === locale }"
              role="option"
              :aria-selected="code === locale"
              @click="selectLocale(code)"
            >
              <img :src="LOCALE_FLAGS[code].flag" :alt="LOCALE_FLAGS[code].label" />
              <span>{{ LOCALE_FLAGS[code].label }}</span>
            </button>
          </li>
        </ul>
      </div>

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

:root[data-theme='dark'] .app-header__nav a:not(.router-link-active) {
  color: var(--color-text);
}

:root[data-theme='dark'] .app-header__nav a.router-link-active {
  color: var(--color-accent-300);
}

@media (prefers-color-scheme: dark) {
  :root:not([data-theme='light']) .app-header__nav a:not(.router-link-active) {
    color: var(--color-text);
  }

  :root:not([data-theme='light']) .app-header__nav a.router-link-active {
    color: var(--color-accent-300);
  }
}

.app-header__nav a.router-link-active {
  color: var(--color-accent-600);
}

.app-header__admin-menu {
  position: relative;
}

.app-header__admin-trigger {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-family: var(--font-sans);
  font-size: 15px;
  font-weight: 500;
  color: var(--color-text-muted);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

.app-header__admin-trigger--active {
  color: var(--color-accent-text);
}

.app-header__admin-caret {
  font-size: 10px;
}

.app-header__admin-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  min-width: 180px;
  display: flex;
  flex-direction: column;
  padding: var(--space-xs);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 12px 24px -12px rgba(15, 23, 42, 0.3);
  z-index: 10;
}

.app-header__admin-item {
  padding: var(--space-xs) var(--space-sm);
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  color: var(--color-text);
}

.app-header__admin-item:hover {
  background: var(--color-bg);
}

.app-header__admin-item.router-link-active {
  color: var(--color-accent-text);
  font-weight: 600;
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

:root[data-theme='dark'] .app-header__session-email {
  color: var(--color-text);
}

:root[data-theme='dark'] .app-header__logout {
  color: var(--color-accent-300);
}

@media (prefers-color-scheme: dark) {
  :root:not([data-theme='light']) .app-header__session-email {
    color: var(--color-text);
  }

  :root:not([data-theme='light']) .app-header__logout {
    color: var(--color-accent-300);
  }
}

.app-header__locale {
  position: relative;
}

.app-header__locale-trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: 600;
  padding: 4px var(--space-xs);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: var(--color-bg);
  color: var(--color-text);
  cursor: pointer;
}

.app-header__locale-trigger img {
  width: 18px;
  height: 13px;
  object-fit: cover;
  border-radius: 2px;
}

.app-header__locale-menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  margin: 0;
  padding: var(--space-xs);
  list-style: none;
  min-width: 140px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 12px 24px -12px rgba(15, 23, 42, 0.3);
  z-index: 10;
}

.app-header__locale-option {
  display: flex;
  align-items: center;
  gap: var(--space-xs);
  width: 100%;
  font-family: var(--font-sans);
  font-size: 13px;
  padding: var(--space-xs) var(--space-sm);
  border: none;
  border-radius: var(--radius-sm);
  background: none;
  color: var(--color-text);
  cursor: pointer;
  text-align: left;
}

.app-header__locale-option:hover {
  background: var(--color-bg);
}

.app-header__locale-option--active {
  color: var(--color-accent-text);
  font-weight: 600;
}

.app-header__locale-option img {
  width: 18px;
  height: 13px;
  object-fit: cover;
  border-radius: 2px;
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
