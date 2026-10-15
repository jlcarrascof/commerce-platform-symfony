import { ref } from 'vue'

export type ToastType = 'created' | 'updated' | 'deleted' | 'restored' | 'error'

export interface Toast {
  id: number
  message: string
  type: ToastType
}

const DURATION_MS = 4000

// Module-scoped singleton: every component calling useToast() shares the
// same reactive list, so any part of the app can push a toast and a single
// <ToastContainer> mounted once in App.vue renders all of them.
const toasts = ref<Toast[]>([])
let nextId = 0

function dismiss(id: number): void {
  toasts.value = toasts.value.filter((toast) => toast.id !== id)
}

function push(message: string, type: ToastType): void {
  const id = nextId++
  toasts.value.push({ id, message, type })
  setTimeout(() => dismiss(id), DURATION_MS)
}

export function useToast() {
  return {
    toasts,
    dismiss,
    created: (message: string) => push(message, 'created'),
    updated: (message: string) => push(message, 'updated'),
    deleted: (message: string) => push(message, 'deleted'),
    restored: (message: string) => push(message, 'restored'),
    error: (message: string) => push(message, 'error'),
  }
}
