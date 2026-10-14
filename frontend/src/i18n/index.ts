import { createI18n } from 'vue-i18n'
import en from './locales/en.json'
import es from './locales/es.json'

export const LOCALE_STORAGE_KEY = 'locale'
export const SUPPORTED_LOCALES = ['en', 'es'] as const
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number]

function detectInitialLocale(): SupportedLocale {
  const stored = localStorage.getItem(LOCALE_STORAGE_KEY)
  if (stored && (SUPPORTED_LOCALES as readonly string[]).includes(stored)) {
    return stored as SupportedLocale
  }

  const browserLocale = navigator.language.slice(0, 2).toLowerCase()
  return (SUPPORTED_LOCALES as readonly string[]).includes(browserLocale)
    ? (browserLocale as SupportedLocale)
    : 'en'
}

const i18n = createI18n({
  legacy: false,
  locale: detectInitialLocale(),
  fallbackLocale: 'en',
  messages: { en, es },
})

export function setLocale(locale: SupportedLocale): void {
  i18n.global.locale.value = locale
  localStorage.setItem(LOCALE_STORAGE_KEY, locale)
}

export default i18n
