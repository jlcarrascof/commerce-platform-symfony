<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import BaseInput from '../components/base/BaseInput.vue'
import BaseButton from '../components/base/BaseButton.vue'

const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const isSubmitting = ref(false)

const authStore = useAuthStore()
const router = useRouter()

async function handleSubmit(): Promise<void> {
  error.value = null
  isSubmitting.value = true

  try {
    await authStore.login(email.value, password.value)
    router.push('/')
  } catch {
    error.value = 'Invalid email or password.'
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
        <h1 class="login-view__brand-title">Commerce Platform</h1>
        <p class="login-view__brand-tagline">
          Manage your inventory, track orders and keep your catalog moving — all in one place.
        </p>
      </div>
    </aside>

    <div class="login-view__panel">
      <form class="login-view__form" @submit.prevent="handleSubmit">
        <h2 class="login-view__form-title">Welcome back</h2>
        <p class="login-view__form-subtitle">Log in to continue to your account.</p>

        <label class="login-view__field">
          <span>Email</span>
          <BaseInput v-model="email" type="email" required placeholder="you@example.com" />
        </label>
        <label class="login-view__field">
          <span>Password</span>
          <BaseInput v-model="password" type="password" required placeholder="••••••••" />
        </label>

        <p v-if="error" class="login-view__error">{{ error }}</p>

        <BaseButton type="submit" :disabled="isSubmitting" class="login-view__submit">
          {{ isSubmitting ? 'Logging in...' : 'Log in' }}
        </BaseButton>
      </form>
    </div>
  </div>
</template>

<style scoped>
.login-view {
  min-height: 100%;
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
  max-width: 360px;
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
