import { defineStore } from 'pinia'
import apiClient, { AUTH_TOKEN_STORAGE_KEY } from '../api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(AUTH_TOKEN_STORAGE_KEY) as string | null,
  }),

  getters: {
    isAuthenticated: (state) => state.token !== null,
  },

  actions: {
    async login(email: string, password: string): Promise<void> {
      const response = await apiClient.post<{ token: string }>('/login', { email, password })
      this.token = response.data.token
      localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, this.token)
    },

    logout(): void {
      this.token = null
      localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY)
    },
  },
})
