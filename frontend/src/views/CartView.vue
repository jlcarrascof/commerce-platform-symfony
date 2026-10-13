<script setup lang="ts">
import { computed } from 'vue'
import { useCartStore } from '../stores/cart'
import BaseButton from '../components/base/BaseButton.vue'

const cartStore = useCartStore()

const formatter = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })

const formattedTotal = computed(() => formatter.format(cartStore.totalPriceInCents / 100))

function formatPrice(priceInCents: number): string {
  return formatter.format(priceInCents / 100)
}

function decrease(productId: number, currentQuantity: number): void {
  cartStore.setQuantity(productId, currentQuantity - 1)
}

function increase(productId: number, currentQuantity: number): void {
  cartStore.setQuantity(productId, currentQuantity + 1)
}
</script>

<template>
  <section class="cart-page">
    <h1>Cart</h1>

    <p v-if="cartStore.isEmpty" class="cart-page__message">Your cart is empty.</p>

    <template v-else>
      <ul class="cart-page__list">
        <li v-for="item in cartStore.items" :key="item.product.id" class="cart-item">
          <img class="cart-item__image" :src="item.product.imageUrl" :alt="item.product.name" />
          <div class="cart-item__info">
            <span class="cart-item__name">{{ item.product.name }}</span>
            <span class="cart-item__price">{{ formatPrice(item.product.priceInCents) }}</span>
          </div>
          <div class="cart-item__quantity">
            <button type="button" @click="decrease(item.product.id, item.quantity)">−</button>
            <span>{{ item.quantity }}</span>
            <button type="button" @click="increase(item.product.id, item.quantity)">+</button>
          </div>
          <span class="cart-item__subtotal">{{ formatPrice(item.product.priceInCents * item.quantity) }}</span>
          <button type="button" class="cart-item__remove" @click="cartStore.removeItem(item.product.id)">
            Remove
          </button>
        </li>
      </ul>

      <div class="cart-page__summary">
        <span class="cart-page__total-label">Total</span>
        <span class="cart-page__total-value">{{ formattedTotal }}</span>
      </div>

      <RouterLink to="/checkout">
        <BaseButton class="cart-page__checkout">Proceed to checkout</BaseButton>
      </RouterLink>
    </template>
  </section>
</template>

<style scoped>
.cart-page {
  padding: var(--space-xl);
  max-width: 800px;
  margin: 0 auto;
}

.cart-page h1 {
  margin-bottom: var(--space-lg);
}

.cart-page__message {
  color: var(--color-text-muted);
}

.cart-page__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.cart-item {
  display: grid;
  grid-template-columns: 56px 1fr auto auto auto;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-sm) 0;
  border-bottom: 1px solid var(--color-border);
}

.cart-item__image {
  width: 56px;
  height: 56px;
  object-fit: cover;
  border-radius: var(--radius-md);
}

.cart-item__info {
  display: flex;
  flex-direction: column;
}

.cart-item__name {
  font-weight: 600;
  color: var(--color-text-heading);
}

.cart-item__price {
  font-size: 13px;
  color: var(--color-text-muted);
}

.cart-item__quantity {
  display: flex;
  align-items: center;
  gap: var(--space-xs);
}

.cart-item__quantity button {
  width: 24px;
  height: 24px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  cursor: pointer;
}

.cart-item__subtotal {
  font-weight: 700;
  color: var(--color-text-heading);
}

.cart-item__remove {
  background: none;
  border: none;
  color: var(--color-error-600, #dc2626);
  font-size: 13px;
  cursor: pointer;
}

.cart-page__summary {
  display: flex;
  justify-content: space-between;
  margin-top: var(--space-lg);
  font-size: 18px;
}

.cart-page__total-label {
  color: var(--color-text-muted);
}

.cart-page__total-value {
  font-weight: 800;
  color: var(--color-text-heading);
}

.cart-page__checkout {
  margin-top: var(--space-md);
  width: 100%;
}

@media (max-width: 600px) {
  .cart-page {
    padding: var(--space-md);
  }

  .cart-item {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-sm) var(--space-md);
  }

  .cart-item__image {
    width: 48px;
    height: 48px;
  }

  .cart-item__info {
    flex: 1 1 140px;
  }

  /* Image + name/price take the first line; quantity, subtotal and
     remove wrap together onto a second line below them. */
  .cart-item__quantity,
  .cart-item__subtotal,
  .cart-item__remove {
    flex: 0 0 auto;
  }
}
</style>
