import { defineStore } from 'pinia'
import apiClient, { AUTH_TOKEN_STORAGE_KEY, REFRESH_TOKEN_STORAGE_KEY } from '../api/client'
import { useCartStore } from './cart'

interface JwtPayload {
  username?: string
  roles?: string[]
}

function decodeToken(token: string): JwtPayload | null {
  try {
    return JSON.parse(atob(token.split('.')[1]))
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(AUTH_TOKEN_STORAGE_KEY) as string | null,
  }),

  getters: {
    isAuthenticated: (state) => state.token !== null,

    userEmail: (state): string | null => {
      if (!state.token) {
        return null
      }
      return decodeToken(state.token)?.username ?? null
    },

    isAdmin: (state): boolean => {
      if (!state.token) {
        return false
      }
      return decodeToken(state.token)?.roles?.includes('ROLE_ADMIN') ?? false
    },
  },

  actions: {
    async login(email: string, password: string): Promise<void> {
      // Discard whatever cart is left in this browser before switching
      // identities, so a new login never inherits the previous user's cart.
      useCartStore().clear()

      const response = await apiClient.post<{ token: string; refresh_token: string }>('/login', {
        email,
        password,
      })
      this.token = response.data.token
      localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, this.token)
      localStorage.setItem(REFRESH_TOKEN_STORAGE_KEY, response.data.refresh_token)
    },

    logout(): void {
      this.token = null
      localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY)
      localStorage.removeItem(REFRESH_TOKEN_STORAGE_KEY)
      useCartStore().clear()
    },
  },
})
