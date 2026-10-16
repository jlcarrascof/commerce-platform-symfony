<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useCartStore } from '../../stores/cart'
import { useCartDrawer } from '../../composables/useCartDrawer'
import BaseButton from '../base/BaseButton.vue'

const { t } = useI18n()
const router = useRouter()
const cartStore = useCartStore()
const { isOpen, closeCartDrawer } = useCartDrawer()

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

function goToCheckout(): void {
  closeCartDrawer()
  router.push('/checkout')
}
</script>

<template>
  <Transition name="cart-drawer-fade">
    <div v-if="isOpen" class="cart-drawer__backdrop" @click="closeCartDrawer"></div>
  </Transition>
  <Transition name="cart-drawer-slide">
    <aside v-if="isOpen" class="cart-drawer" role="dialog" aria-modal="true" :aria-label="t('cart.title')">
      <div class="cart-drawer__header">
        <h2>{{ t('cart.title') }}</h2>
        <button type="button" class="cart-drawer__close" :aria-label="t('common.close')" @click="closeCartDrawer">
          ✕
        </button>
      </div>

      <p v-if="cartStore.isEmpty" class="cart-drawer__message">{{ t('cart.empty') }}</p>

      <template v-else>
        <ul class="cart-drawer__list">
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
            <button
              type="button"
              class="cart-item__remove"
              :aria-label="t('cart.remove')"
              :title="t('cart.remove')"
              @click="cartStore.removeItem(item.product.id)"
            >
              🗑️
            </button>
          </li>
        </ul>

        <div class="cart-drawer__summary">
          <span class="cart-drawer__total-label">{{ t('cart.total') }}</span>
          <span class="cart-drawer__total-value">{{ formattedTotal }}</span>
        </div>

        <BaseButton class="cart-drawer__checkout" @click="goToCheckout">{{ t('cart.checkout') }}</BaseButton>
      </template>
    </aside>
  </Transition>
</template>

<style scoped>
.cart-drawer__backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  z-index: 40;
}

.cart-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  max-width: 420px;
  display: flex;
  flex-direction: column;
  padding: var(--space-lg);
  background: var(--color-bg);
  border-left: 1px solid var(--color-border);
  box-shadow: -12px 0 32px -16px rgba(15, 23, 42, 0.35);
  z-index: 41;
  overflow-y: auto;
}

.cart-drawer__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-lg);
}

.cart-drawer__header h2 {
  margin: 0;
  font-size: 20px;
}

.cart-drawer__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: var(--radius-sm);
  background: none;
  color: var(--color-text-muted);
  font-size: 16px;
  cursor: pointer;
}

.cart-drawer__close:hover {
  background: var(--color-surface);
  color: var(--color-text-heading);
}

.cart-drawer__message {
  color: var(--color-text-muted);
}

.cart-drawer__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  flex: 1;
  overflow-y: auto;
}

.cart-item {
  display: grid;
  grid-template-columns: 48px 1fr auto;
  grid-template-rows: auto auto;
  align-items: center;
  gap: var(--space-xs) var(--space-sm);
  padding-bottom: var(--space-sm);
  border-bottom: 1px solid var(--color-border);
}

.cart-item__image {
  grid-row: 1 / 3;
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: var(--radius-md);
}

.cart-item__info {
  display: flex;
  flex-direction: column;
}

.cart-item__name {
  font-weight: 600;
  font-size: 14px;
  color: var(--color-text-heading);
}

.cart-item__price {
  font-size: 12px;
  color: var(--color-text-muted);
}

.cart-item__subtotal {
  font-weight: 700;
  font-size: 14px;
  color: var(--color-text-heading);
  text-align: right;
}

.cart-item__quantity {
  display: flex;
  align-items: center;
  gap: var(--space-xs);
}

.cart-item__quantity button {
  width: 22px;
  height: 22px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  cursor: pointer;
}

.cart-item__remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: none;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  line-height: 1;
  cursor: pointer;
  justify-self: end;
  transition: background-color 0.15s ease;
}

.cart-item__remove:hover {
  background: color-mix(in srgb, var(--color-error-600, #dc2626) 12%, transparent);
}

.cart-drawer__summary {
  display: flex;
  justify-content: space-between;
  margin-top: var(--space-lg);
  font-size: 16px;
}

.cart-drawer__total-label {
  color: var(--color-text-muted);
}

.cart-drawer__total-value {
  font-weight: 800;
  color: var(--color-text-heading);
}

.cart-drawer__checkout {
  margin-top: var(--space-md);
  width: 100%;
}

.cart-drawer-slide-enter-active,
.cart-drawer-slide-leave-active {
  transition: transform 0.2s ease;
}

.cart-drawer-slide-enter-from,
.cart-drawer-slide-leave-to {
  transform: translateX(100%);
}

.cart-drawer-fade-enter-active,
.cart-drawer-fade-leave-active {
  transition: opacity 0.2s ease;
}

.cart-drawer-fade-enter-from,
.cart-drawer-fade-leave-to {
  opacity: 0;
}

@media (max-width: 480px) {
  .cart-drawer {
    max-width: 100%;
  }
}
</style>
