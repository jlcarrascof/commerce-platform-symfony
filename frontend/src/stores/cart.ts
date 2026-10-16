import { defineStore } from 'pinia'
import type { Product } from '../types/product'
import { AUTH_TOKEN_STORAGE_KEY } from '../api/client'

const CART_STORAGE_PREFIX = 'cart_items'

export interface CartItem {
  product: Product
  quantity: number
}

function decodeEmailFromToken(token: string): string | null {
  try {
    return JSON.parse(atob(token.split('.')[1])).username ?? null
  } catch {
    return null
  }
}

// Each user (and the anonymous/logged-out state) gets its own storage slot,
// keyed off the JWT's username, so switching accounts in the same browser
// never leaks one person's cart into another's.
function currentCartKey(): string {
  const token = localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)
  const email = token ? decodeEmailFromToken(token) : null
  return email ? `${CART_STORAGE_PREFIX}:${email}` : CART_STORAGE_PREFIX
}

function loadFromStorage(): CartItem[] {
  try {
    const raw = localStorage.getItem(currentCartKey())
    return raw ? (JSON.parse(raw) as CartItem[]) : []
  } catch {
    return []
  }
}

function saveToStorage(items: CartItem[]): void {
  localStorage.setItem(currentCartKey(), JSON.stringify(items))
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: loadFromStorage() as CartItem[],
  }),

  getters: {
    totalItems: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0),
    totalPriceInCents: (state) =>
      state.items.reduce((sum, item) => sum + item.product.priceInCents * item.quantity, 0),
    isEmpty: (state) => state.items.length === 0,
  },

  actions: {
    addItem(product: Product, quantity = 1): void {
      const existing = this.items.find((item) => item.product.id === product.id)
      if (existing) {
        existing.quantity += quantity
      } else {
        this.items.push({ product, quantity })
      }
      saveToStorage(this.items)
    },

    removeItem(productId: number): void {
      this.items = this.items.filter((item) => item.product.id !== productId)
      saveToStorage(this.items)
    },

    setQuantity(productId: number, quantity: number): void {
      if (quantity < 1) {
        this.removeItem(productId)
        return
      }
      const existing = this.items.find((item) => item.product.id === productId)
      if (existing) {
        existing.quantity = quantity
        saveToStorage(this.items)
      }
    },

    clear(): void {
      this.items = []
      saveToStorage(this.items)
    },

    // Re-reads from storage under whichever user's cart key is now active,
    // called right after the token changes on login/logout.
    reload(): void {
      this.items = loadFromStorage()
    },
  },
})
