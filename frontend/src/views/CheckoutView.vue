<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../api/client'
import { useCartStore } from '../stores/cart'
import BaseButton from '../components/base/BaseButton.vue'

const cartStore = useCartStore()
const router = useRouter()

const isSubmitting = ref(false)
const error = ref<string | null>(null)
const orderId = ref<number | null>(null)

// Stable per checkout attempt so a retry after a network error does not create a duplicate order.
const idempotencyKey = crypto.randomUUID()

const formatter = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })

function formatPrice(priceInCents: number): string {
  return formatter.format(priceInCents / 100)
}

async function placeOrder(): Promise<void> {
  error.value = null
  isSubmitting.value = true

  try {
    const response = await apiClient.post<{ id: number }>(
      '/orders',
      {
        items: cartStore.items.map((item) => ({
          productId: item.product.id,
          quantity: item.quantity,
        })),
      },
      { headers: { 'Idempotency-Key': idempotencyKey } },
    )
    orderId.value = response.data.id
    cartStore.clear()
  } catch (err: any) {
    const backendMessage =
      err.response?.data?.error ?? err.response?.data?.errors?.[0]?.message ?? null
    error.value = backendMessage ?? 'Could not place the order. Please try again.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <section class="checkout-page">
    <h1>Checkout</h1>

    <div v-if="orderId" class="checkout-page__success">
      <p>Order #{{ orderId }} created successfully.</p>
      <BaseButton @click="router.push('/')">Back to catalog</BaseButton>
    </div>

    <p v-else-if="cartStore.isEmpty" class="checkout-page__message">
      Your cart is empty. Add some products before checking out.
    </p>

    <template v-else>
      <ul class="checkout-page__list">
        <li v-for="item in cartStore.items" :key="item.product.id" class="checkout-item">
          <span>{{ item.product.name }} × {{ item.quantity }}</span>
          <span>{{ formatPrice(item.product.priceInCents * item.quantity) }}</span>
        </li>
      </ul>

      <div class="checkout-page__summary">
        <span>Total</span>
        <span>{{ formatPrice(cartStore.totalPriceInCents) }}</span>
      </div>

      <p v-if="error" class="checkout-page__error">{{ error }}</p>

      <BaseButton :disabled="isSubmitting" @click="placeOrder">
        {{ isSubmitting ? 'Placing order...' : 'Place order' }}
      </BaseButton>
    </template>
  </section>
</template>

<style scoped>
.checkout-page {
  padding: var(--space-xl);
  max-width: 600px;
  margin: 0 auto;
}

.checkout-page h1 {
  margin-bottom: var(--space-lg);
}

.checkout-page__message {
  color: var(--color-text-muted);
}

.checkout-page__list {
  list-style: none;
  margin: 0 0 var(--space-md);
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.checkout-item {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  color: var(--color-text);
}

.checkout-page__summary {
  display: flex;
  justify-content: space-between;
  font-weight: 800;
  font-size: 18px;
  color: var(--color-text-heading);
  padding-top: var(--space-sm);
  border-top: 1px solid var(--color-border);
  margin-bottom: var(--space-lg);
}

.checkout-page__error {
  color: var(--color-error-600, #dc2626);
  margin-bottom: var(--space-md);
}

.checkout-page__success {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  align-items: flex-start;
}
</style>
