<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import apiClient from '../api/client'
import type { Product } from '../types/product'
import type { Category } from '../types/category'
import BaseCard from '../components/base/BaseCard.vue'

type Tab = 'products' | 'categories'
type CatalogItem = Product | Category

const { t } = useI18n()
const activeTab = ref<Tab>('products')
const showDeleted = ref(false)

const visibleItems = ref<CatalogItem[]>([])

const isLoading = ref(true)
const error = ref<string | null>(null)
const rowErrors = reactive<Record<number, string>>({})
const pendingActions = reactive<Record<number, boolean>>({})

const page = ref(1)
const limit = 12
const totalCount = ref(0)

const formatter = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })

function formatPrice(priceInCents: number): string {
  return formatter.format(priceInCents / 100)
}

async function refresh(): Promise<void> {
  isLoading.value = true
  error.value = null

  const resource = activeTab.value === 'products' ? 'products' : 'categories'
  const path = showDeleted.value ? `/${resource}/deleted` : `/${resource}`

  try {
    const response = await apiClient.get<CatalogItem[]>(path, { params: { page: page.value, limit } })
    visibleItems.value = response.data
    totalCount.value = Number(response.headers['x-total-count'] ?? response.data.length)
  } catch {
    error.value = t('common.genericLoadError')
  } finally {
    isLoading.value = false
  }
}

function goToPage(nextPage: number): void {
  page.value = nextPage
  refresh()
}

onMounted(refresh)
watch(activeTab, () => {
  showDeleted.value = false
})
watch([activeTab, showDeleted], () => {
  page.value = 1
  refresh()
})

async function deleteItem(item: CatalogItem): Promise<void> {
  delete rowErrors[item.id]
  pendingActions[item.id] = true

  const resource = activeTab.value === 'products' ? 'products' : 'categories'

  try {
    await apiClient.delete(`/${resource}/${item.id}`)
    await refresh()
  } catch (err: any) {
    rowErrors[item.id] = err.response?.data?.message ?? t('adminCatalog.deleteError')
  } finally {
    pendingActions[item.id] = false
  }
}

async function restoreItem(item: CatalogItem): Promise<void> {
  delete rowErrors[item.id]
  pendingActions[item.id] = true

  const resource = activeTab.value === 'products' ? 'products' : 'categories'

  try {
    await apiClient.post(`/${resource}/${item.id}/restore`)
    await refresh()
  } catch (err: any) {
    rowErrors[item.id] = err.response?.data?.message ?? t('adminCatalog.restoreError')
  } finally {
    pendingActions[item.id] = false
  }
}
</script>

<template>
  <section class="admin-page">
    <div class="admin-page__header">
      <h1>{{ t('adminCatalog.title') }}</h1>
      <div class="admin-page__tabs">
        <button
          type="button"
          class="admin-page__tab"
          :class="{ 'admin-page__tab--active': activeTab === 'products' }"
          @click="activeTab = 'products'"
        >
          {{ t('adminCatalog.products') }}
        </button>
        <button
          type="button"
          class="admin-page__tab"
          :class="{ 'admin-page__tab--active': activeTab === 'categories' }"
          @click="activeTab = 'categories'"
        >
          {{ t('adminCatalog.categories') }}
        </button>
      </div>
      <select v-model="showDeleted" class="admin-page__filter">
        <option :value="false">{{ t('adminCatalog.active') }}</option>
        <option :value="true">{{ t('adminCatalog.deleted') }}</option>
      </select>
    </div>

    <p v-if="isLoading" class="admin-page__message">{{ t('adminCatalog.loading') }}</p>
    <p v-else-if="error" class="admin-page__message admin-page__message--error">{{ error }}</p>
    <p v-else-if="visibleItems.length === 0" class="admin-page__message">
      {{ showDeleted ? t('adminCatalog.noDeletedItems') : t('adminCatalog.noItemsYet') }}
    </p>

    <BaseCard v-else class="admin-page__card">
      <table class="admin-table">
        <thead>
          <tr>
            <th>{{ t('adminCatalog.colName') }}</th>
            <th v-if="activeTab === 'products'">{{ t('adminCatalog.colCategory') }}</th>
            <th v-else>{{ t('adminCatalog.colSlug') }}</th>
            <th v-if="activeTab === 'products'">{{ t('adminCatalog.colPrice') }}</th>
            <th v-if="activeTab === 'products'">{{ t('adminCatalog.colStock') }}</th>
            <th class="admin-table__actions">{{ t('adminCatalog.colActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="item in visibleItems" :key="item.id">
            <tr>
              <td data-label="Name">{{ item.name }}</td>
              <td v-if="activeTab === 'products'" data-label="Category">{{ (item as Product).category.name }}</td>
              <td v-else data-label="Slug">{{ (item as Category).slug }}</td>
              <td v-if="activeTab === 'products'" data-label="Price">{{ formatPrice((item as Product).priceInCents) }}</td>
              <td v-if="activeTab === 'products'" data-label="Stock">{{ (item as Product).stock }}</td>
              <td data-label="Actions" class="admin-table__actions">
                <button
                  v-if="!showDeleted"
                  type="button"
                  class="admin-table__action admin-table__action--cancel"
                  :disabled="pendingActions[item.id]"
                  @click="deleteItem(item)"
                >
                  {{ t('adminCatalog.delete') }}
                </button>
                <button
                  v-else
                  type="button"
                  class="admin-table__action admin-table__action--confirm"
                  :disabled="pendingActions[item.id]"
                  @click="restoreItem(item)"
                >
                  {{ t('adminCatalog.restore') }}
                </button>
              </td>
            </tr>
            <tr v-if="rowErrors[item.id]">
              <td :colspan="activeTab === 'products' ? 5 : 3" class="admin-table__row-error">
                {{ rowErrors[item.id] }}
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </BaseCard>

    <div v-if="!isLoading && !error && totalCount > limit" class="admin-page__pagination">
      <span class="admin-page__pagination-info">
        {{ t('admin.showing', { from: (page - 1) * limit + 1, to: Math.min(page * limit, totalCount), total: totalCount }) }}
      </span>
      <div class="admin-page__pagination-controls">
        <button type="button" :disabled="page === 1" @click="goToPage(page - 1)">{{ t('admin.previous') }}</button>
        <span>{{ t('admin.pageOf', { page, total: Math.max(1, Math.ceil(totalCount / limit)) }) }}</span>
        <button type="button" :disabled="page * limit >= totalCount" @click="goToPage(page + 1)">{{ t('admin.next') }}</button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.admin-page {
  width: 100%;
  padding: var(--space-xl);
  max-width: 1400px;
  margin: 0 auto;
}

.admin-page__header {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
}

.admin-page__tabs {
  display: flex;
  gap: var(--space-xs);
  margin-left: auto;
}

.admin-page__tab {
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: 600;
  padding: var(--space-xs) var(--space-md);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
}

.admin-page__tab--active {
  color: var(--color-accent-text);
  border-color: var(--color-accent-text);
}

.admin-page__filter {
  font-family: var(--font-sans);
  font-size: 14px;
  padding: var(--space-xs) var(--space-sm);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-bg);
  color: var(--color-text);
}

.admin-page__message {
  color: var(--color-text-muted);
}

.admin-page__message--error {
  color: var(--color-error-600, #dc2626);
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
}

.admin-table :where(th) {
  text-align: left;
}

.admin-table th {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-muted);
  font-weight: 600;
  padding-bottom: var(--space-sm);
  border-bottom: 2px solid var(--color-border);
}

.admin-table td {
  padding: var(--space-sm) 0;
  border-bottom: 1px solid var(--color-border);
  font-size: 14px;
}

.admin-table__actions {
  display: flex;
  gap: var(--space-sm);
  justify-content: flex-end;
}

th.admin-table__actions {
  justify-content: flex-end;
  padding-right: 20%;
}

.admin-table__action {
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: 600;
  padding: var(--space-xs) var(--space-sm);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
}

.admin-table__action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.admin-table__action--confirm {
  color: var(--color-success);
  border-color: color-mix(in srgb, var(--color-success) 40%, var(--color-border));
}

.admin-table__action--cancel {
  color: var(--color-error-600, #dc2626);
  border-color: color-mix(in srgb, var(--color-error) 40%, var(--color-border));
}

.admin-table__row-error {
  color: var(--color-error-600, #dc2626);
  font-size: 13px;
  padding-bottom: var(--space-sm);
}

.admin-page__pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: var(--space-md);
  font-size: 13px;
  color: var(--color-text-muted);
}

.admin-page__pagination-controls {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.admin-page__pagination-controls button {
  font-family: var(--font-sans);
  font-size: 13px;
  padding: var(--space-xs) var(--space-sm);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
}

.admin-page__pagination-controls button:disabled {
  color: var(--color-text-muted);
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .admin-page {
    padding: var(--space-md);
  }

  .admin-page__header {
    flex-wrap: wrap;
  }

  .admin-page__tabs {
    margin-left: 0;
    order: 3;
    width: 100%;
  }

  .admin-page__filter {
    margin-left: auto;
  }

  .admin-table thead {
    display: none;
  }

  .admin-table,
  .admin-table tbody,
  .admin-table tr,
  .admin-table td {
    display: block;
    width: 100%;
  }

  .admin-table tr {
    margin-bottom: var(--space-md);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-sm) var(--space-md);
  }

  .admin-table tr:has(.admin-table__row-error) {
    margin-bottom: 0;
    border: none;
    padding: 0;
  }

  .admin-table__row-error {
    display: block !important;
    text-align: left;
  }

  .admin-table__row-error::before {
    content: none;
  }

  .admin-table td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-xs) 0;
    border-bottom: 1px solid var(--color-border);
    text-align: right;
  }

  .admin-table td:last-child {
    border-bottom: none;
  }

  .admin-table td::before {
    content: attr(data-label);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-text-muted);
    font-weight: 600;
    text-align: left;
  }

  .admin-table__actions {
    justify-content: flex-end;
    padding-right: 0;
  }

  th.admin-table__actions {
    display: none;
  }

  .admin-page__pagination {
    flex-direction: column;
    align-items: stretch;
    gap: var(--space-sm);
    text-align: center;
  }
}
</style>
