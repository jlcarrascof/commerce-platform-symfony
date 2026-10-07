import { defineStore } from 'pinia'
import type { Product } from '../types/product'

const CART_STORAGE_KEY = 'cart_items'

export interface CartItem {
  product: Product
  quantity: number
}

function loadFromStorage(): CartItem[] {
  try {
    const raw = localStorage.getItem(CART_STORAGE_KEY)
    return raw ? (JSON.parse(raw) as CartItem[]) : []
  } catch {
    return []
  }
}

function saveToStorage(items: CartItem[]): void {
  localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(items))
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
  },
})
