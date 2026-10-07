<script setup lang="ts">
import { onMounted, ref } from 'vue'
import apiClient from '../api/client'
import type { Product } from '../types/product'
import ProductGrid from '../components/catalog/ProductGrid.vue'

const products = ref<Product[]>([])

onMounted(async () => {
  const response = await apiClient.get<Product[]>('/products')
  products.value = response.data
})
</script>

<template>
  <section class="catalog-page">
    <h1>Catalog</h1>
    <ProductGrid :products="products" />
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
