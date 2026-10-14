<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { isAxiosError } from 'axios'
import { useAuthStore } from '../stores/auth'
import BaseInput from '../components/base/BaseInput.vue'
import BaseButton from '../components/base/BaseButton.vue'

const { t } = useI18n()
const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const isSubmitting = ref(false)
const isPasswordVisible = ref(false)

const authStore = useAuthStore()
const router = useRouter()

async function handleSubmit(): Promise<void> {
  error.value = null
  isSubmitting.value = true

  try {
    await authStore.login(email.value, password.value)
    router.push('/')
  } catch (err) {
    if (isAxiosError(err) && err.response?.status === 429) {
      error.value = t('login.tooManyAttempts')
    } else {
      error.value = t('login.invalidCredentials')
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="login-view">
    <aside class="login-view__brand">
      <div class="login-view__brand-content">
        <span class="login-view__brand-mark">◆</span>
        <h1 class="login-view__brand-title">{{ t('login.brandTitle') }}</h1>
        <p class="login-view__brand-tagline">
          {{ t('login.brandTagline') }}
        </p>
      </div>
    </aside>

    <div class="login-view__panel">
      <form class="login-view__form" @submit.prevent="handleSubmit">
        <h2 class="login-view__form-title">{{ t('login.welcomeBack') }}</h2>
        <p class="login-view__form-subtitle">{{ t('login.subtitle') }}</p>

        <label class="login-view__field">
          <span>{{ t('login.email') }}</span>
          <BaseInput v-model="email" type="email" required placeholder="you@example.com">
            <template #icon>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2" />
                <path d="m22 7-10 7L2 7" />
              </svg>
            </template>
          </BaseInput>
        </label>
        <label class="login-view__field">
          <span>{{ t('login.password') }}</span>
          <BaseInput
            v-model="password"
            :type="isPasswordVisible ? 'text' : 'password'"
            required
            placeholder="••••••••"
          >
            <template #icon>
              <button
                type="button"
                class="login-view__password-toggle"
                :aria-label="isPasswordVisible ? t('login.hidePassword') : t('login.showPassword')"
                @click="isPasswordVisible = !isPasswordVisible"
              >
                <svg v-if="isPasswordVisible" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
                <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                  <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 11 7 11 7a13.16 13.16 0 0 1-1.67 2.68" />
                  <path d="M6.61 6.61A13.52 13.52 0 0 0 1 12s4 7 11 7a9.74 9.74 0 0 0 5.39-1.61" />
                  <path d="M2 2l20 20" />
                </svg>
              </button>
            </template>
          </BaseInput>
        </label>

        <p v-if="error" class="login-view__error">{{ error }}</p>

        <BaseButton type="submit" :disabled="isSubmitting" class="login-view__submit">
          {{ isSubmitting ? t('login.loggingIn') : t('login.logIn') }}
        </BaseButton>
      </form>
    </div>
  </div>
</template>

<style scoped>
.login-view {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr;
}

.login-view__brand {
  display: none;
}

.login-view__panel {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-xl) var(--space-md);
}

.login-view__form {
  width: 100%;
  max-width: 432px;
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  box-shadow: 0 20px 40px -20px rgba(15, 23, 42, 0.25);
}

.login-view__form-title {
  margin: 0;
}

.login-view__form-subtitle {
  margin: calc(-1 * var(--space-sm)) 0 0;
  color: var(--color-text-muted);
  font-size: 14px;
}

.login-view__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
  font-size: 14px;
  color: var(--color-text-heading);
}

.login-view__error {
  color: var(--color-error-600, #dc2626);
  font-size: 14px;
  margin: 0;
}

.login-view__submit {
  margin-top: var(--space-xs);
}

.login-view__password-toggle {
  display: inline-flex;
  padding: 0;
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
}

.login-view__password-toggle:hover {
  color: var(--color-text);
}

@media (min-width: 900px) {
  .login-view {
    grid-template-columns: 1.1fr 1fr;
  }

  .login-view__brand {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-xl);
    background: linear-gradient(135deg, var(--color-accent-600), var(--color-accent-700));
    color: var(--color-neutral-0);
  }

  .login-view__brand-content {
    max-width: 380px;
  }

  .login-view__brand-mark {
    display: inline-block;
    font-size: 28px;
    margin-bottom: var(--space-md);
    opacity: 0.85;
  }

  .login-view__brand-title {
    color: var(--color-neutral-0);
    font-size: 32px;
    margin-bottom: var(--space-md);
  }

  .login-view__brand-tagline {
    font-size: 16px;
    line-height: 1.6;
    opacity: 0.9;
  }
}
</style>
