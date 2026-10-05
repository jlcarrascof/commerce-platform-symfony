<script setup lang="ts">
import { mockProducts } from '../mocks/products'
import ProductGrid from '../components/catalog/ProductGrid.vue'
</script>

<template>
  <section class="catalog-page">
    <h1>Catalog</h1>
    <ProductGrid :products="mockProducts" />
  </section>
</template>

<style scoped>
.catalog-page {
  padding: var(--space-xl);
  max-width: 1200px;
  margin: 0 auto;
}

.catalog-page h1 {
  margin-bottom: var(--space-lg);
}
</style>
