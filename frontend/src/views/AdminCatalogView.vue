<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import apiClient from '../api/client'
import { useToast } from '../composables/useToast'
import type { Product } from '../types/product'
import type { Category } from '../types/category'
import BaseCard from '../components/base/BaseCard.vue'
import BaseModal from '../components/base/BaseModal.vue'
import BaseInput from '../components/base/BaseInput.vue'
import BaseButton from '../components/base/BaseButton.vue'

type Tab = 'products' | 'categories'
type CatalogItem = Product | Category

const { t } = useI18n()
const toast = useToast()
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

const searchTerm = ref('')
let searchDebounceId: ReturnType<typeof setTimeout> | undefined

function onSearchInput(value: string): void {
  searchTerm.value = value
  clearTimeout(searchDebounceId)
  searchDebounceId = setTimeout(() => {
    page.value = 1
    refresh()
  }, 300)
}

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
    const response = await apiClient.get<CatalogItem[]>(path, {
      params: { page: page.value, limit, search: searchTerm.value || undefined },
    })
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
  searchTerm.value = ''
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
    toast.deleted(activeTab.value === 'products' ? t('adminCatalog.toastProductDeleted') : t('adminCatalog.toastCategoryDeleted'))
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
    toast.restored(activeTab.value === 'products' ? t('adminCatalog.toastProductRestored') : t('adminCatalog.toastCategoryRestored'))
  } catch (err: any) {
    rowErrors[item.id] = err.response?.data?.message ?? t('adminCatalog.restoreError')
  } finally {
    pendingActions[item.id] = false
  }
}

// Create / edit modal
const allCategories = ref<Category[]>([])

async function fetchAllCategories(): Promise<void> {
  const response = await apiClient.get<Category[]>('/categories', { params: { limit: 100 } })
  allCategories.value = response.data
}

const isModalOpen = ref(false)
const editingItem = ref<CatalogItem | null>(null)
const formName = ref('')
const formSlug = ref('')
const formDescription = ref('')
const formPrice = ref('')
const formStock = ref('')
const formImageUrl = ref('')
const formCategoryId = ref<number | null>(null)
const formError = ref<string | null>(null)
const isSaving = ref(false)

function slugify(value: string): string {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function handleNameInput(value: string): void {
  formSlug.value = slugify(value)
}

function openCreateModal(): void {
  editingItem.value = null
  formName.value = ''
  formSlug.value = ''
  formDescription.value = ''
  formPrice.value = ''
  formStock.value = ''
  formImageUrl.value = ''
  formCategoryId.value = activeTab.value === 'products' ? (allCategories.value[0]?.id ?? null) : null
  formError.value = null
  isModalOpen.value = true
}

function openEditModal(item: CatalogItem): void {
  editingItem.value = item
  formName.value = item.name
  formSlug.value = item.slug
  if (activeTab.value === 'products') {
    const product = item as Product
    formDescription.value = product.description
    formPrice.value = (product.priceInCents / 100).toFixed(2)
    formStock.value = String(product.stock)
    formImageUrl.value = product.imageUrl
    formCategoryId.value = product.category.id
  }
  formError.value = null
  isModalOpen.value = true
}

async function submitForm(): Promise<void> {
  formError.value = null
  isSaving.value = true

  const resource = activeTab.value === 'products' ? 'products' : 'categories'
  const payload: Record<string, unknown> =
    activeTab.value === 'products'
      ? {
          name: formName.value,
          slug: formSlug.value,
          description: formDescription.value,
          priceInCents: Math.round(Number(formPrice.value) * 100),
          stock: Number(formStock.value),
          imageUrl: formImageUrl.value,
          categoryId: formCategoryId.value,
        }
      : {
          name: formName.value,
          slug: formSlug.value,
        }

  const isEditing = editingItem.value !== null

  try {
    if (editingItem.value) {
      await apiClient.put(`/${resource}/${editingItem.value.id}`, payload)
    } else {
      await apiClient.post(`/${resource}`, payload)
    }
    isModalOpen.value = false
    await refresh()
    if (resource === 'categories') {
      await fetchAllCategories()
    }

    if (activeTab.value === 'products') {
      isEditing ? toast.updated(t('adminCatalog.toastProductUpdated')) : toast.created(t('adminCatalog.toastProductCreated'))
    } else {
      isEditing ? toast.updated(t('adminCatalog.toastCategoryUpdated')) : toast.created(t('adminCatalog.toastCategoryCreated'))
    }
  } catch (err: any) {
    formError.value = err.response?.data?.errors?.[0]?.message ?? err.response?.data?.message ?? t('adminCatalog.saveError')
  } finally {
    isSaving.value = false
  }
}

onMounted(fetchAllCategories)
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
      <BaseButton class="admin-page__add" @click="openCreateModal">
        {{ activeTab === 'products' ? t('adminCatalog.addProduct') : t('adminCatalog.addCategory') }}
      </BaseButton>
      <select v-model="showDeleted" class="admin-page__filter">
        <option :value="false">{{ t('adminCatalog.active') }}</option>
        <option :value="true">{{ t('adminCatalog.deleted') }}</option>
      </select>
    </div>

    <div class="admin-page__search">
      <BaseInput
        :model-value="searchTerm"
        :placeholder="t('adminCatalog.searchPlaceholder')"
        @update:model-value="onSearchInput"
      />
    </div>

    <p v-if="isLoading" class="admin-page__message">{{ t('adminCatalog.loading') }}</p>
    <p v-else-if="error" class="admin-page__message admin-page__message--error">{{ error }}</p>
    <p v-else-if="visibleItems.length === 0 && searchTerm" class="admin-page__message">
      {{ t('adminCatalog.noSearchResults') }}
    </p>
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
                <template v-if="!showDeleted">
                  <button type="button" class="admin-table__action" @click="openEditModal(item)">
                    {{ t('common.edit') }}
                  </button>
                  <button
                    type="button"
                    class="admin-table__action admin-table__action--cancel"
                    :disabled="pendingActions[item.id]"
                    @click="deleteItem(item)"
                  >
                    {{ t('adminCatalog.delete') }}
                  </button>
                </template>
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

    <BaseModal
      v-model="isModalOpen"
      :title="
        activeTab === 'products'
          ? editingItem
            ? t('adminCatalog.editProduct')
            : t('adminCatalog.addProduct')
          : editingItem
            ? t('adminCatalog.editCategory')
            : t('adminCatalog.addCategory')
      "
    >
      <form class="catalog-form" @submit.prevent="submitForm">
        <label class="catalog-form__field">
          <span>{{ t('adminCatalog.colName') }}</span>
          <BaseInput v-model="formName" required @update:model-value="handleNameInput" />
        </label>

        <label class="catalog-form__field">
          <span>{{ t('adminCatalog.colSlug') }}</span>
          <BaseInput v-model="formSlug" required disabled />
          <small class="catalog-form__hint">{{ t('adminCatalog.slugHint') }}</small>
        </label>

        <template v-if="activeTab === 'products'">
          <label class="catalog-form__field">
            <span>{{ t('checkout.description') }}</span>
            <textarea v-model="formDescription" class="catalog-form__textarea" required rows="3"></textarea>
          </label>

          <div class="catalog-form__row">
            <label class="catalog-form__field">
              <span>{{ t('adminCatalog.colPrice') }}</span>
              <BaseInput v-model="formPrice" type="number" required />
            </label>
            <label class="catalog-form__field">
              <span>{{ t('adminCatalog.colStock') }}</span>
              <BaseInput v-model="formStock" type="number" required />
            </label>
          </div>

          <label class="catalog-form__field">
            <span>{{ t('adminCatalog.fieldImageUrl') }}</span>
            <BaseInput v-model="formImageUrl" required placeholder="https://..." />
          </label>

          <label class="catalog-form__field">
            <span>{{ t('adminCatalog.colCategory') }}</span>
            <select v-model.number="formCategoryId" class="catalog-form__select">
              <option v-for="category in allCategories" :key="category.id" :value="category.id">
                {{ category.name }}
              </option>
            </select>
          </label>
        </template>

        <p v-if="formError" class="catalog-form__error">{{ formError }}</p>
      </form>

      <template #footer>
        <BaseButton variant="secondary" :disabled="isSaving" @click="isModalOpen = false">
          {{ t('common.cancel') }}
        </BaseButton>
        <BaseButton :disabled="isSaving" @click="submitForm">
          {{ isSaving ? (editingItem ? t('common.saving') : t('common.creating')) : (editingItem ? t('common.save') : t('common.create')) }}
        </BaseButton>
      </template>
    </BaseModal>
  </section>
</template>

<style scoped>
.admin-page {
  width: 100%;
  padding: var(--space-xl);
  max-width: 1400px;
  margin: 0 auto;
}

.catalog-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.catalog-form__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-md);
}

.catalog-form__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
  font-size: 14px;
  color: var(--color-text-heading);
}

.catalog-form__textarea,
.catalog-form__select {
  font-family: var(--font-sans);
  font-size: 14px;
  padding: var(--space-sm) var(--space-md);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-bg);
  color: var(--color-text);
}

.catalog-form__textarea {
  resize: vertical;
}

.catalog-form__hint {
  color: var(--color-text-muted);
  font-size: 12px;
}

.catalog-form__error {
  color: var(--color-error-600, #dc2626);
  font-size: 14px;
  margin: 0;
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

.admin-page__add {
  font-size: 13px;
  padding: var(--space-xs) var(--space-md);
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

.admin-page__search {
  max-width: 320px;
  margin-bottom: var(--space-md);
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
