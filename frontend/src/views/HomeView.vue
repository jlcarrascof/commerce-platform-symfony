<script setup lang="ts">
import { onMounted, ref } from 'vue'
import apiClient from '../api/client'
import type { Product } from '../types/product'
import ProductGrid from '../components/catalog/ProductGrid.vue'

const products = ref<Product[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    const response = await apiClient.get<Product[]>('/products')
    products.value = response.data
  } catch {
    error.value = 'Could not load the catalog. Please try again later.'
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <section class="catalog-page">
    <h1>Catalog</h1>

    <p v-if="isLoading" class="catalog-page__message">Loading products...</p>
    <p v-else-if="error" class="catalog-page__message catalog-page__message--error">{{ error }}</p>
    <p v-else-if="products.length === 0" class="catalog-page__message">No products available yet.</p>
    <ProductGrid v-else :products="products" />
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

.catalog-page__message {
  color: var(--color-text-muted);
}

.catalog-page__message--error {
  color: var(--color-error-600, #dc2626);
}
</style>
