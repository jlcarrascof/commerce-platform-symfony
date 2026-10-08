<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import apiClient from '../api/client'
import type { Order, OrderStatus } from '../types/order'
import BaseCard from '../components/base/BaseCard.vue'
import BaseBadge from '../components/base/BaseBadge.vue'

const orders = ref<Order[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)
const statusFilter = ref<OrderStatus | ''>('')

const actionErrors = reactive<Record<number, string>>({})
const pendingActions = reactive<Record<number, boolean>>({})

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
      params: statusFilter.value ? { status: statusFilter.value } : {},
    })
    orders.value = response.data
  } catch {
    error.value = 'Could not load orders. Please try again later.'
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchOrders)
watch(statusFilter, fetchOrders)

async function performAction(order: Order, action: 'confirm' | 'cancel'): Promise<void> {
  delete actionErrors[order.id]
  pendingActions[order.id] = true

  try {
    const response = await apiClient.post<Order>(`/orders/${order.id}/${action}`)
    const index = orders.value.findIndex((o) => o.id === order.id)
    if (index !== -1) {
      orders.value[index] = response.data
    }
  } catch (err: any) {
    actionErrors[order.id] = err.response?.data?.error ?? `Could not ${action} the order.`
  } finally {
    pendingActions[order.id] = false
  }
}
</script>

<template>
  <section class="admin-page">
    <div class="admin-page__header">
      <h1>Orders</h1>
      <select v-model="statusFilter" class="admin-page__filter">
        <option value="">All statuses</option>
        <option value="pending">Pending</option>
        <option value="confirmed">Confirmed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>

    <p v-if="isLoading" class="admin-page__message">Loading orders...</p>
    <p v-else-if="error" class="admin-page__message admin-page__message--error">{{ error }}</p>
    <p v-else-if="orders.length === 0" class="admin-page__message">No orders match this filter.</p>

    <BaseCard v-else class="admin-page__card">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Created</th>
            <th class="admin-table__total">Total</th>
            <th class="admin-table__actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="order in orders" :key="order.id">
            <tr>
              <td>#{{ order.id }}</td>
              <td>{{ order.customer.fullName }}</td>
              <td><BaseBadge :tone="badgeTone(order.status)">{{ order.status }}</BaseBadge></td>
              <td>{{ formatDate(order.createdAt) }}</td>
              <td class="admin-table__total">{{ formatPrice(orderTotal(order)) }}</td>
              <td class="admin-table__actions">
                <button
                  v-if="order.status === 'pending'"
                  type="button"
                  class="admin-table__action admin-table__action--confirm"
                  :disabled="pendingActions[order.id]"
                  @click="performAction(order, 'confirm')"
                >
                  Confirm
                </button>
                <button
                  v-if="order.status !== 'cancelled'"
                  type="button"
                  class="admin-table__action admin-table__action--cancel"
                  :disabled="pendingActions[order.id]"
                  @click="performAction(order, 'cancel')"
                >
                  Cancel
                </button>
              </td>
            </tr>
            <tr v-if="actionErrors[order.id]">
              <td colspan="6" class="admin-table__row-error">{{ actionErrors[order.id] }}</td>
            </tr>
          </template>
        </tbody>
      </table>
    </BaseCard>
  </section>
</template>

<style scoped>
.admin-page {
  padding: var(--space-xl);
  max-width: 1000px;
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

.admin-table th {
  text-align: left;
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

.admin-table__action {
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: 600;
  padding: var(--space-xs) var(--space-sm);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
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
</style>
