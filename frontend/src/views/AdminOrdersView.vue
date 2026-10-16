<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import apiClient from '../api/client'
import type { Order, OrderStatus } from '../types/order'
import type { AuditLogEntry } from '../types/auditLog'
import BaseCard from '../components/base/BaseCard.vue'
import BaseBadge from '../components/base/BaseBadge.vue'

const { t } = useI18n()
const orders = ref<Order[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)
const statusFilter = ref<OrderStatus | ''>('')

const page = ref(1)
const limit = 12
const totalCount = ref(0)

const actionErrors = reactive<Record<number, string>>({})
const pendingActions = reactive<Record<number, boolean>>({})

const expandedOrderId = ref<number | null>(null)
const auditLogs = reactive<Record<number, AuditLogEntry[]>>({})
const auditLoading = reactive<Record<number, boolean>>({})

const formatter = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })

function orderTotal(order: Order): number {
  return order.items.reduce((sum, item) => sum + item.unitPriceInCents * item.quantity, 0)
}

function formatPrice(priceInCents: number): string {
  return formatter.format(priceInCents / 100)
}

function formatDate(isoDate: string): string {
  return new Date(isoDate).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })
}

function badgeTone(status: OrderStatus): 'success' | 'error' | 'warning' {
  if (status === 'confirmed') return 'success'
  if (status === 'cancelled') return 'error'
  return 'warning'
}

async function fetchOrders(): Promise<void> {
  isLoading.value = true
  error.value = null

  try {
    const response = await apiClient.get<Order[]>('/orders', {
      params: {
        page: page.value,
        limit,
        ...(statusFilter.value ? { status: statusFilter.value } : {}),
      },
    })
    orders.value = response.data
    totalCount.value = Number(response.headers['x-total-count'] ?? response.data.length)
  } catch {
    error.value = t('common.genericLoadError')
  } finally {
    isLoading.value = false
  }
}

function goToPage(nextPage: number): void {
  page.value = nextPage
  fetchOrders()
}

onMounted(fetchOrders)
watch(statusFilter, () => {
  page.value = 1
  fetchOrders()
})

async function performAction(order: Order, action: 'confirm' | 'cancel'): Promise<void> {
  delete actionErrors[order.id]
  pendingActions[order.id] = true

  try {
    const response = await apiClient.post<Order>(`/orders/${order.id}/${action}`)
    const index = orders.value.findIndex((o) => o.id === order.id)
    if (index !== -1) {
      orders.value[index] = response.data
    }
    // Invalidate the cached history so a re-opened panel reflects this new action.
    delete auditLogs[order.id]
    if (expandedOrderId.value === order.id) {
      await refreshHistory(order.id)
    }
  } catch (err: any) {
    actionErrors[order.id] = err.response?.data?.error ?? `Could not ${action} the order.`
  } finally {
    pendingActions[order.id] = false
  }
}

async function refreshHistory(orderId: number): Promise<void> {
  auditLoading[orderId] = true
  try {
    const response = await apiClient.get<AuditLogEntry[]>(`/orders/${orderId}/audit-log`)
    auditLogs[orderId] = response.data
  } catch {
    auditLogs[orderId] = []
  } finally {
    auditLoading[orderId] = false
  }
}

async function toggleHistory(order: Order): Promise<void> {
  if (expandedOrderId.value === order.id) {
    expandedOrderId.value = null
    return
  }

  expandedOrderId.value = order.id

  if (auditLogs[order.id]) {
    return
  }

  await refreshHistory(order.id)
}
</script>

<template>
  <section class="admin-page">
    <div class="admin-page__header">
      <h1>{{ t('admin.orders') }}</h1>
      <select v-model="statusFilter" class="admin-page__filter">
        <option value="">{{ t('admin.allStatuses') }}</option>
        <option value="pending">{{ t('admin.pending') }}</option>
        <option value="confirmed">{{ t('admin.confirmed') }}</option>
        <option value="cancelled">{{ t('admin.cancelled') }}</option>
      </select>
    </div>

    <p v-if="isLoading" class="admin-page__message">{{ t('admin.loading') }}</p>
    <p v-else-if="error" class="admin-page__message admin-page__message--error">{{ error }}</p>
    <p v-else-if="orders.length === 0" class="admin-page__message">{{ t('admin.noMatch') }}</p>

    <BaseCard v-else class="admin-page__card">
      <table class="admin-table">
        <thead>
          <tr>
            <th>{{ t('admin.colId') }}</th>
            <th>{{ t('admin.colCustomer') }}</th>
            <th>{{ t('admin.colStatus') }}</th>
            <th>{{ t('admin.colCreated') }}</th>
            <th class="admin-table__total">{{ t('admin.colTotal') }}</th>
            <th class="admin-table__actions">{{ t('admin.colActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="order in orders" :key="order.id">
            <tr>
              <td data-label="ID">#{{ order.id }}</td>
              <td data-label="Customer">{{ order.customer.fullName }}</td>
              <td data-label="Status"><BaseBadge :tone="badgeTone(order.status)">{{ order.status }}</BaseBadge></td>
              <td data-label="Created">{{ formatDate(order.createdAt) }}</td>
              <td data-label="Total" class="admin-table__total">{{ formatPrice(orderTotal(order)) }}</td>
              <td data-label="Actions" class="admin-table__actions">
                <button
                  v-if="order.status === 'pending'"
                  type="button"
                  class="admin-table__action admin-table__action--confirm"
                  :disabled="pendingActions[order.id]"
                  @click="performAction(order, 'confirm')"
                >
                  {{ t('admin.confirm') }}
                </button>
                <button
                  v-if="order.status !== 'cancelled'"
                  type="button"
                  class="admin-table__action admin-table__action--cancel"
                  :disabled="pendingActions[order.id]"
                  @click="performAction(order, 'cancel')"
                >
                  {{ t('admin.cancel') }}
                </button>
                <button
                  type="button"
                  class="admin-table__action"
                  @click="toggleHistory(order)"
                >
                  {{ expandedOrderId === order.id ? t('admin.hideHistory') : t('admin.history') }}
                </button>
              </td>
            </tr>
            <tr v-if="actionErrors[order.id]">
              <td colspan="6" class="admin-table__row-error">{{ actionErrors[order.id] }}</td>
            </tr>
            <tr v-if="expandedOrderId === order.id">
              <td colspan="6" class="admin-table__history">
                <p v-if="order.shippingAddress" class="admin-table__shipping-address">
                  <strong>{{ t('admin.shippingAddress') }}:</strong> {{ order.shippingAddress }}
                </p>
                <div class="admin-table__history-header">
                  <span class="admin-table__history-title">{{ t('admin.history') }}</span>
                  <button
                    type="button"
                    class="admin-table__history-refresh"
                    :disabled="auditLoading[order.id]"
                    @click="refreshHistory(order.id)"
                  >
                    {{ t('admin.refresh') }}
                  </button>
                </div>
                <p v-if="auditLoading[order.id]" class="admin-table__history-message">{{ t('admin.loadingHistory') }}</p>
                <p v-else-if="!auditLogs[order.id] || auditLogs[order.id].length === 0" class="admin-table__history-message">
                  {{ t('admin.noHistory') }}
                </p>
                <ul v-else class="admin-table__history-list">
                  <li v-for="entry in auditLogs[order.id]" :key="entry.id">
                    <strong>{{ entry.action }}</strong> {{ t('admin.historyBy') }} {{ entry.userEmail }} — {{ formatDate(entry.createdAt) }}
                  </li>
                </ul>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </BaseCard>

    <div v-if="!isLoading && !error && totalCount > 0" class="admin-page__pagination">
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
  justify-content: space-between;
  margin-bottom: var(--space-lg);
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

.admin-table__total {
  text-align: right;
  font-weight: 700;
  color: var(--color-text-heading);
  font-variant-numeric: tabular-nums;
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
  /* Explicit color keeps this readable instead of falling back to the
     browser's default disabled-control color, which is invisible
     against a dark surface. */
  color: var(--color-text-muted);
  opacity: 0.5;
  cursor: not-allowed;
}

.admin-table__history {
  background: var(--color-surface);
  padding: var(--space-sm) var(--space-md) !important;
}

.admin-table__shipping-address {
  font-size: 13px;
  color: var(--color-text);
  margin: 0 0 var(--space-sm);
}

.admin-table__history-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-xs);
}

.admin-table__history-title {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  font-weight: 600;
  color: var(--color-text-muted);
}

.admin-table__history-refresh {
  font-family: var(--font-sans);
  font-size: 12px;
  font-weight: 600;
  padding: 2px var(--space-sm);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: var(--color-bg);
  color: var(--color-text);
  cursor: pointer;
}

.admin-table__history-refresh:disabled {
  color: var(--color-text-muted);
  opacity: 0.5;
  cursor: not-allowed;
}

.admin-table__history-message {
  color: var(--color-text-muted);
  font-size: 13px;
  margin: 0;
}

.admin-table__history-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
  font-size: 13px;
  color: var(--color-text);
}

@media (max-width: 768px) {
  .admin-page {
    padding: var(--space-md);
  }

  .admin-page__header {
    flex-direction: column;
    align-items: stretch;
    gap: var(--space-sm);
  }

  .admin-page__filter {
    width: 100%;
  }

  /* Collapse the table into a stack of cards: each row becomes a card,
     each cell becomes a labelled line using its data-label attribute. */
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

  .admin-table tr:has(.admin-table__row-error),
  .admin-table tr:has(.admin-table__history) {
    margin-bottom: 0;
    border: none;
    padding: 0;
  }

  .admin-table__row-error,
  .admin-table__history {
    display: block !important;
    text-align: left;
  }

  .admin-table__row-error::before,
  .admin-table__history::before {
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
    flex-wrap: wrap;
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
