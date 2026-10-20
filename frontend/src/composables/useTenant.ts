import { ref } from 'vue'

export const TENANT_STORAGE_KEY = 'tenant_slug'

export interface TenantOption {
  slug: string
  label: string
}

// Hardcoded for this checkpoint's demo fixtures (acme/globex). A real app
// would fetch this list, or resolve it from the subdomain instead of asking
// the user to pick it on login.
export const AVAILABLE_TENANTS: TenantOption[] = [
  { slug: 'acme', label: 'Acme Corp' },
  { slug: 'globex', label: 'Globex Inc' },
]

function detectInitialTenant(): string {
  const stored = localStorage.getItem(TENANT_STORAGE_KEY)
  if (stored && AVAILABLE_TENANTS.some((t) => t.slug === stored)) {
    return stored
  }

  return AVAILABLE_TENANTS[0].slug
}

// Module-scoped singleton, same pattern as useTheme.ts: every component
// calling useTenant() shares the same reactive state.
const tenantSlug = ref<string>(detectInitialTenant())

export function useTenant() {
  function setTenant(slug: string): void {
    tenantSlug.value = slug
    localStorage.setItem(TENANT_STORAGE_KEY, slug)
  }

  return { tenantSlug, setTenant }
}
