<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import apiClient from '../api/client'
import { useAuthStore } from '../stores/auth'
import type { AdminUser } from '../types/user'
import BaseCard from '../components/base/BaseCard.vue'
import BaseModal from '../components/base/BaseModal.vue'
import BaseInput from '../components/base/BaseInput.vue'
import BaseButton from '../components/base/BaseButton.vue'

const { t } = useI18n()
const authStore = useAuthStore()

const showDeleted = ref(false)
const users = ref<AdminUser[]>([])

const isLoading = ref(true)
const error = ref<string | null>(null)
const rowErrors = reactive<Record<number, string>>({})
const pendingActions = reactive<Record<number, boolean>>({})

const page = ref(1)
const limit = 12
const totalCount = ref(0)

async function refresh(): Promise<void> {
  isLoading.value = true
  error.value = null

  const path = showDeleted.value ? '/users/deleted' : '/users'

  try {
    const response = await apiClient.get<AdminUser[]>(path, { params: { page: page.value, limit } })
    users.value = response.data
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
watch(showDeleted, () => {
  page.value = 1
  refresh()
})

async function deactivateUser(user: AdminUser): Promise<void> {
  delete rowErrors[user.id]
  pendingActions[user.id] = true

  try {
    await apiClient.delete(`/users/${user.id}`)
    await refresh()
  } catch (err: any) {
    rowErrors[user.id] = err.response?.data?.message ?? t('adminUsers.deactivateError')
  } finally {
    pendingActions[user.id] = false
  }
}

async function restoreUser(user: AdminUser): Promise<void> {
  delete rowErrors[user.id]
  pendingActions[user.id] = true

  try {
    await apiClient.post(`/users/${user.id}/restore`)
    await refresh()
  } catch (err: any) {
    rowErrors[user.id] = err.response?.data?.message ?? t('adminUsers.restoreError')
  } finally {
    pendingActions[user.id] = false
  }
}

// Create / edit modal
const isModalOpen = ref(false)
const editingUser = ref<AdminUser | null>(null)
const formEmail = ref('')
const formPassword = ref('')
const formIsAdmin = ref(false)
const formFullName = ref('')
const formError = ref<string | null>(null)
const isSaving = ref(false)

function openCreateModal(): void {
  editingUser.value = null
  formEmail.value = ''
  formPassword.value = ''
  formIsAdmin.value = false
  formFullName.value = ''
  formError.value = null
  isModalOpen.value = true
}

function openEditModal(user: AdminUser): void {
  editingUser.value = user
  formEmail.value = user.email
  formPassword.value = ''
  formIsAdmin.value = user.isAdmin
  formFullName.value = user.fullName ?? ''
  formError.value = null
  isModalOpen.value = true
}

async function submitForm(): Promise<void> {
  formError.value = null
  isSaving.value = true

  const payload: Record<string, unknown> = {
    email: formEmail.value,
    isAdmin: formIsAdmin.value,
    fullName: formIsAdmin.value ? null : formFullName.value,
  }
  if (formPassword.value !== '') {
    payload.password = formPassword.value
  }

  try {
    if (editingUser.value) {
      await apiClient.put(`/users/${editingUser.value.id}`, payload)
    } else {
      await apiClient.post('/users', payload)
    }
    isModalOpen.value = false
    await refresh()
  } catch (err: any) {
    formError.value = err.response?.data?.errors?.[0]?.message ?? err.response?.data?.message ?? t('common.saveError')
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <section class="admin-page">
    <div class="admin-page__header">
      <h1>{{ t('adminUsers.title') }}</h1>
      <BaseButton class="admin-page__add" @click="openCreateModal">{{ t('adminUsers.addUser') }}</BaseButton>
      <select v-model="showDeleted" class="admin-page__filter">
        <option :value="false">{{ t('adminCatalog.active') }}</option>
        <option :value="true">{{ t('adminCatalog.deleted') }}</option>
      </select>
    </div>

    <p v-if="isLoading" class="admin-page__message">{{ t('adminUsers.loading') }}</p>
    <p v-else-if="error" class="admin-page__message admin-page__message--error">{{ error }}</p>
    <p v-else-if="users.length === 0" class="admin-page__message">
      {{ showDeleted ? t('adminUsers.noDeletedItems') : t('adminUsers.noItemsYet') }}
    </p>

    <BaseCard v-else class="admin-page__card">
      <table class="admin-table">
        <thead>
          <tr>
            <th>{{ t('adminUsers.colEmail') }}</th>
            <th>{{ t('adminUsers.colFullName') }}</th>
            <th>{{ t('adminUsers.colRole') }}</th>
            <th class="admin-table__actions">{{ t('adminUsers.colActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="user in users" :key="user.id">
            <tr>
              <td data-label="Email">{{ user.email }}</td>
              <td data-label="Full name">{{ user.fullName ?? '—' }}</td>
              <td data-label="Role">{{ user.isAdmin ? t('adminUsers.roleAdmin') : t('adminUsers.roleCustomer') }}</td>
              <td data-label="Actions" class="admin-table__actions">
                <template v-if="!showDeleted">
                  <button type="button" class="admin-table__action" @click="openEditModal(user)">
                    {{ t('common.edit') }}
                  </button>
                  <button
                    type="button"
                    class="admin-table__action admin-table__action--cancel"
                    :disabled="pendingActions[user.id] || user.email === authStore.userEmail"
                    @click="deactivateUser(user)"
                  >
                    {{ t('adminUsers.deactivate') }}
                  </button>
                </template>
                <button
                  v-else
                  type="button"
                  class="admin-table__action admin-table__action--confirm"
                  :disabled="pendingActions[user.id]"
                  @click="restoreUser(user)"
                >
                  {{ t('adminCatalog.restore') }}
                </button>
              </td>
            </tr>
            <tr v-if="rowErrors[user.id]">
              <td colspan="4" class="admin-table__row-error">{{ rowErrors[user.id] }}</td>
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

    <BaseModal v-model="isModalOpen" :title="editingUser ? t('adminUsers.editUser') : t('adminUsers.addUser')">
      <form class="user-form" @submit.prevent="submitForm">
        <label class="user-form__field">
          <span>{{ t('adminUsers.fieldEmail') }}</span>
          <BaseInput v-model="formEmail" type="email" required />
        </label>

        <label class="user-form__field">
          <span>{{ t('adminUsers.fieldPassword') }}</span>
          <BaseInput v-model="formPassword" type="password" :required="!editingUser" :minlength="8" />
          <small class="user-form__hint">
            {{ editingUser ? t('adminUsers.fieldPasswordHintEdit') : t('adminUsers.fieldPasswordHintCreate') }}
          </small>
        </label>

        <label class="user-form__field">
          <span>{{ t('adminUsers.fieldRole') }}</span>
          <select v-model="formIsAdmin" class="user-form__select">
            <option :value="false">{{ t('adminUsers.roleCustomer') }}</option>
            <option :value="true">{{ t('adminUsers.roleAdmin') }}</option>
          </select>
        </label>

        <label v-if="!formIsAdmin" class="user-form__field">
          <span>{{ t('adminUsers.fieldFullName') }}</span>
          <BaseInput v-model="formFullName" required />
        </label>

        <p v-if="formError" class="user-form__error">{{ formError }}</p>
      </form>

      <template #footer>
        <BaseButton variant="secondary" :disabled="isSaving" @click="isModalOpen = false">
          {{ t('common.cancel') }}
        </BaseButton>
        <BaseButton :disabled="isSaving" @click="submitForm">
          {{ isSaving ? (editingUser ? t('common.saving') : t('common.creating')) : (editingUser ? t('common.save') : t('common.create')) }}
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

.admin-page__header {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
}

.admin-page__add {
  margin-left: auto;
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

.user-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.user-form__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
  font-size: 14px;
  color: var(--color-text-heading);
}

.user-form__select {
  font-family: var(--font-sans);
  font-size: 14px;
  padding: var(--space-sm) var(--space-md);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-bg);
  color: var(--color-text);
}

.user-form__hint {
  color: var(--color-text-muted);
  font-size: 12px;
}

.user-form__error {
  color: var(--color-error-600, #dc2626);
  font-size: 14px;
  margin: 0;
}

@media (max-width: 768px) {
  .admin-page {
    padding: var(--space-md);
  }

  .admin-page__header {
    flex-wrap: wrap;
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
