<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../api/client'
import { useCartStore } from '../stores/cart'
import BaseCard from '../components/base/BaseCard.vue'
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
    <BaseCard class="checkout-card">
      <h1 class="checkout-card__title">Checkout</h1>

      <div v-if="orderId" class="checkout-page__success">
        <span class="checkout-page__success-icon">✓</span>
        <p class="checkout-page__success-text">Order #{{ orderId }} created successfully.</p>
        <BaseButton @click="router.push('/')">Back to catalog</BaseButton>
      </div>

      <p v-else-if="cartStore.isEmpty" class="checkout-page__message">
        Your cart is empty. Add some products before checking out.
      </p>

      <template v-else>
        <table class="checkout-table">
          <thead>
            <tr>
              <th class="checkout-table__qty">Qty</th>
              <th class="checkout-table__description">Description</th>
              <th class="checkout-table__unit-price">Unit price</th>
              <th class="checkout-table__line-total">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in cartStore.items" :key="item.product.id">
              <td class="checkout-table__qty">{{ item.quantity }}</td>
              <td class="checkout-table__description">{{ item.product.name }}</td>
              <td class="checkout-table__unit-price">{{ formatPrice(item.product.priceInCents) }}</td>
              <td class="checkout-table__line-total">
                {{ formatPrice(item.product.priceInCents * item.quantity) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="checkout-table__total-label">Total</td>
              <td class="checkout-table__total-value">{{ formatPrice(cartStore.totalPriceInCents) }}</td>
            </tr>
          </tfoot>
        </table>

        <p v-if="error" class="checkout-page__error">{{ error }}</p>

        <BaseButton :disabled="isSubmitting" class="checkout-page__submit" @click="placeOrder">
          {{ isSubmitting ? 'Placing order...' : 'Place order' }}
        </BaseButton>
      </template>
    </BaseCard>
  </section>
</template>

<style scoped>
.checkout-page {
  display: flex;
  justify-content: center;
  padding: var(--space-xl) var(--space-md);
}

.checkout-card {
  width: 100%;
  max-width: 640px;
}

.checkout-card__title {
  margin-bottom: var(--space-lg);
}

.checkout-page__message {
  color: var(--color-text-muted);
}

.checkout-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: var(--space-lg);
}

.checkout-table th {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-muted);
  font-weight: 600;
  padding-bottom: var(--space-sm);
  border-bottom: 2px solid var(--color-border);
}

.checkout-table td {
  padding: var(--space-sm) 0;
  border-bottom: 1px solid var(--color-border);
  font-size: 14px;
  color: var(--color-text);
}

.checkout-table__qty {
  width: 48px;
  text-align: center;
}

.checkout-table__unit-price,
.checkout-table__line-total {
  width: 110px;
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.checkout-table__description {
  text-align: left;
  font-weight: 500;
  color: var(--color-text-heading);
}

.checkout-table__line-total {
  font-weight: 700;
  color: var(--color-text-heading);
}

.checkout-table__total-label {
  padding-top: var(--space-md);
  border-bottom: none;
  text-align: right;
  font-weight: 700;
  color: var(--color-text-heading);
}

.checkout-table__total-value {
  padding-top: var(--space-md);
  border-bottom: none;
  text-align: right;
  font-weight: 800;
  font-size: 20px;
  color: var(--color-accent-600);
  font-variant-numeric: tabular-nums;
}

.checkout-page__error {
  color: var(--color-error-600, #dc2626);
  margin-bottom: var(--space-md);
}

.checkout-page__submit {
  width: 100%;
}

.checkout-page__success {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: var(--space-md);
  padding: var(--space-lg) 0;
}

.checkout-page__success-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--color-success) 15%, white);
  color: var(--color-success);
  font-size: 24px;
}

.checkout-page__success-text {
  font-size: 16px;
  color: var(--color-text-heading);
  margin: 0;
}
</style>
