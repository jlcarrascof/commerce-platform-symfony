<script setup lang="ts">
import type { Product } from '../../types/product'
import BaseCard from '../base/BaseCard.vue'
import BaseBadge from '../base/BaseBadge.vue'
import BaseButton from '../base/BaseButton.vue'

const props = defineProps<{
  product: Product
}>()

const formattedPrice = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
}).format(props.product.priceInCents / 100)
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
      <BaseButton>Add to cart</BaseButton>
    </div>
  </BaseCard>
</template>

<style scoped>
.product-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.product-card__image {
  width: calc(100% + 2 * var(--space-lg));
  margin: calc(-1 * var(--space-lg)) calc(-1 * var(--space-lg)) 0;
  height: 160px;
  object-fit: cover;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
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
  align-items: center;
  justify-content: space-between;
  margin-top: var(--space-sm);
}

.product-card__price {
  font-weight: 700;
  color: var(--color-text-heading);
}
</style>
