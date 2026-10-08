import { defineStore } from 'pinia'
import apiClient, { AUTH_TOKEN_STORAGE_KEY } from '../api/client'

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
