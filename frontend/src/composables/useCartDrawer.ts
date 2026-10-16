import { ref } from 'vue'

// Module-scoped singleton: shared across every component so opening the
// drawer from one place (e.g. the header's cart icon) is visible everywhere.
const isOpen = ref(false)

export function useCartDrawer() {
  function openCartDrawer(): void {
    isOpen.value = true
  }

  function closeCartDrawer(): void {
    isOpen.value = false
  }

  return { isOpen, openCartDrawer, closeCartDrawer }
}
