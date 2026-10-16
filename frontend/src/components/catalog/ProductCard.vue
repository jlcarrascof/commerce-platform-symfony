<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Product } from '../../types/product'
import { useCartStore } from '../../stores/cart'
import BaseCard from '../base/BaseCard.vue'
import BaseBadge from '../base/BaseBadge.vue'
import BaseButton from '../base/BaseButton.vue'

const props = defineProps<{
  product: Product
}>()

const { t } = useI18n()
const cartStore = useCartStore()
const justAdded = ref(false)

const formattedPrice = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
}).format(props.product.priceInCents / 100)

function handleAddToCart(): void {
  cartStore.addItem(props.product)
  justAdded.value = true
  setTimeout(() => {
    justAdded.value = false
  }, 1200)
}
</script>

<template>
  <BaseCard class="product-card">
    <img
      class="product-card__image"
      :src="product.imageUrl"
      :alt="product.name"
      loading="lazy"
    />
    <BaseBadge tone="neutral">{{ product.category.name }}</BaseBadge>
    <h3 class="product-card__name">{{ product.name }}</h3>
    <p class="product-card__description">{{ product.description }}</p>
    <div class="product-card__footer">
      <span class="product-card__price">{{ formattedPrice }}</span>
      <BaseButton class="product-card__add" @click="handleAddToCart">
        {{ justAdded ? t('product.added') : t('product.addToCart') }}
      </BaseButton>
    </div>
  </BaseCard>
</template>

<style scoped>
.product-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.product-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg, 0 12px 24px -8px rgba(0, 0, 0, 0.2));
}

.product-card__image {
  width: calc(100% + 2 * var(--space-lg));
  margin: calc(-1 * var(--space-lg)) calc(-1 * var(--space-lg)) 0;
  height: 190px;
  object-fit: cover;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  background: var(--color-surface);
}

.product-card__name {
  font-size: 16px;
  margin-top: var(--space-xs);
}

.product-card__description {
  color: var(--color-text-muted);
  font-size: 14px;
  flex: 1;
}

.product-card__footer {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  margin-top: var(--space-sm);
}

.product-card__price {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-text-heading);
}

.product-card__add {
  width: 100%;
}
</style>
