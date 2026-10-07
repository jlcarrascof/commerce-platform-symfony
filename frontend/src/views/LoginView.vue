<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import BaseCard from '../components/base/BaseCard.vue'
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
    <BaseCard class="login-view__card">
      <h1 class="login-view__title">Log in</h1>
      <form class="login-view__form" @submit.prevent="handleSubmit">
        <label class="login-view__field">
          <span>Email</span>
          <BaseInput v-model="email" type="email" required />
        </label>
        <label class="login-view__field">
          <span>Password</span>
          <BaseInput v-model="password" type="password" required />
        </label>
        <p v-if="error" class="login-view__error">{{ error }}</p>
        <BaseButton type="submit" :disabled="isSubmitting">
          {{ isSubmitting ? 'Logging in...' : 'Log in' }}
        </BaseButton>
      </form>
    </BaseCard>
  </div>
</template>

<style scoped>
.login-view {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100%;
  padding: var(--space-xl) var(--space-md);
}

.login-view__card {
  width: 100%;
  max-width: 360px;
}

.login-view__title {
  margin-top: 0;
  text-align: center;
}

.login-view__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
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
</style>
