import { ref } from 'vue'

export const THEME_STORAGE_KEY = 'theme'
export type Theme = 'light' | 'dark'

function detectInitialTheme(): Theme {
  const stored = localStorage.getItem(THEME_STORAGE_KEY)
  if (stored === 'light' || stored === 'dark') {
    return stored
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

function applyTheme(value: Theme): void {
  document.documentElement.setAttribute('data-theme', value)
}

// Module-scoped singleton: every component calling useTheme() shares the
// same reactive state, so the toggle updates everywhere at once without
// needing a Pinia store for something this small.
const theme = ref<Theme>(detectInitialTheme())
applyTheme(theme.value)

export function useTheme() {
  function setTheme(value: Theme): void {
    theme.value = value
    localStorage.setItem(THEME_STORAGE_KEY, value)
    applyTheme(value)
  }

  function toggleTheme(): void {
    setTheme(theme.value === 'dark' ? 'light' : 'dark')
  }

  return { theme, setTheme, toggleTheme }
}
