<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import apiClient from '../api/client'
import type { Product } from '../types/product'
import type { Category } from '../types/category'
import ProductGrid from '../components/catalog/ProductGrid.vue'

const { t } = useI18n()

const products = ref<Product[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)

const categories = ref<Category[]>([])
const activeCategory = ref<string>('')

const page = ref(1)
const limit = 12
const totalCount = ref(0)

async function refresh(): Promise<void> {
  isLoading.value = true
  error.value = null

  try {
    const response = await apiClient.get<Product[]>('/products', {
      params: { page: page.value, limit, category: activeCategory.value || undefined },
    })
    products.value = response.data
    totalCount.value = Number(response.headers['x-total-count'] ?? response.data.length)
  } catch {
    error.value = t('home.error')
  } finally {
    isLoading.value = false
  }
}

function goToPage(nextPage: number): void {
  page.value = nextPage
  refresh()
}

function selectCategory(slug: string): void {
  activeCategory.value = slug
  page.value = 1
  refresh()
}

onMounted(refresh)
onMounted(async () => {
  const response = await apiClient.get<Category[]>('/categories', { params: { limit: 100 } })
  categories.value = response.data
})
</script>

<template>
  <section class="catalog-page">
    <h1>{{ t('home.title') }}</h1>

    <div v-if="categories.length > 0 && categories.length <= 8" class="catalog-page__pills">
      <button
        type="button"
        class="catalog-page__pill"
        :class="{ 'catalog-page__pill--active': activeCategory === '' }"
        @click="selectCategory('')"
      >
        {{ t('home.allCategories') }}
      </button>
      <button
        v-for="category in categories"
        :key="category.id"
        type="button"
        class="catalog-page__pill"
        :class="{ 'catalog-page__pill--active': activeCategory === category.slug }"
        @click="selectCategory(category.slug)"
      >
        {{ category.name }}
      </button>
    </div>

    <p v-if="isLoading" class="catalog-page__message">{{ t('home.loading') }}</p>
    <p v-else-if="error" class="catalog-page__message catalog-page__message--error">{{ error }}</p>
    <p v-else-if="products.length === 0" class="catalog-page__message">{{ t('home.empty') }}</p>
    <ProductGrid v-else :products="products" />

    <div v-if="!isLoading && !error && totalCount > limit" class="catalog-page__pagination">
      <span class="catalog-page__pagination-info">
        {{ t('admin.showing', { from: (page - 1) * limit + 1, to: Math.min(page * limit, totalCount), total: totalCount }) }}
      </span>
      <div class="catalog-page__pagination-controls">
        <button type="button" :disabled="page === 1" @click="goToPage(page - 1)">{{ t('admin.previous') }}</button>
        <span>{{ t('admin.pageOf', { page, total: Math.max(1, Math.ceil(totalCount / limit)) }) }}</span>
        <button type="button" :disabled="page * limit >= totalCount" @click="goToPage(page + 1)">{{ t('admin.next') }}</button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.catalog-page {
  width: 100%;
  padding: var(--space-xl);
  max-width: 1200px;
  margin: 0 auto;
}

.catalog-page h1 {
  margin-bottom: var(--space-lg);
}

.catalog-page__pills {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-sm);
  margin-bottom: var(--space-lg);
}

.catalog-page__pill {
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: 600;
  padding: var(--space-xs) var(--space-md);
  border-radius: 999px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.catalog-page__pill:hover {
  border-color: var(--color-accent-500);
  color: var(--color-text-heading);
}

.catalog-page__pill--active {
  background: var(--color-accent-600);
  border-color: var(--color-accent-600);
  color: var(--color-neutral-0);
}

.catalog-page__message {
  color: var(--color-text-muted);
}

.catalog-page__message--error {
  color: var(--color-error-600, #dc2626);
}

.catalog-page__pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: var(--space-md);
  font-size: 13px;
  color: var(--color-text-muted);
}

.catalog-page__pagination-controls {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.catalog-page__pagination-controls button {
  font-family: var(--font-sans);
  font-size: 13px;
  padding: var(--space-xs) var(--space-sm);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
}

.catalog-page__pagination-controls button:disabled {
  color: var(--color-text-muted);
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .catalog-page__pagination {
    flex-direction: column;
    align-items: stretch;
    gap: var(--space-sm);
    text-align: center;
  }
}
</style>
