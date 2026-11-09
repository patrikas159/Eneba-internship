/** Catalog shortcuts: each one is a store view (existing route + sort), not a separate page. */
export const CATALOG_LINKS = [
  { label: 'Store', to: '/store', sort: null },
  { label: 'Deals', to: '/store?sort=discount', sort: 'discount' },
  { label: 'New releases', to: '/store?sort=newest', sort: 'newest' },
] as const

export const REPO_URL = 'https://github.com/patrikas159/Eneba-internship'
