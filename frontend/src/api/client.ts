// Typed client for the backend REST API. Types come from schema.d.ts,
// which is generated from backend/openapi.yaml (`npm run gen:api`).
import type { components, paths } from './schema'

type Schemas = components['schemas']
export type User = Schemas['User']
export type HomeBlock = Schemas['HomeBlock']
export type Banner = Schemas['Banner']
export type ProductCard = Schemas['ProductCard']
export type ProductPage = Schemas['ProductPage']
export type ProductDetail = Schemas['ProductDetail']
export type Offer = Schemas['Offer']
export type Filters = Schemas['Filters']
export type Order = Schemas['Order']
export type OrderItem = Schemas['OrderItem']
export type CreateOrderRequest = Schemas['CreateOrderRequest']
export type ProductQuery = NonNullable<paths['/products']['get']['parameters']['query']>
export type ProductSort = NonNullable<ProductQuery['sort']>

/** Error body returned by the API ({error, message, ...}) plus the HTTP status. */
export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly body: Record<string, unknown>

  constructor(status: number, body: Record<string, unknown>) {
    super(typeof body.message === 'string' ? body.message : `Request failed (${status})`)
    this.status = status
    this.code = typeof body.error === 'string' ? body.error : 'http_error'
    this.body = body
  }
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers)
  if (init.body !== undefined) headers.set('Content-Type', 'application/json')
  headers.set('Accept', 'application/json')

  // Same origin in prod and via the Vite proxy in dev, so the auth cookie is sent.
  const res = await fetch(`/api${path}`, { ...init, headers, credentials: 'same-origin' })
  if (res.status === 204) return undefined as T

  const body = await res.json().catch(() => ({}))
  if (!res.ok) throw new ApiError(res.status, body as Record<string, unknown>)
  return body as T
}

function toQueryString(params: Record<string, string | number | boolean | undefined>): string {
  const qs = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== '') qs.set(key, String(value))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

const json = (data: unknown) => JSON.stringify(data)

export const api = {
  home: () => request<{ blocks: HomeBlock[] }>('/home'),

  products: (query: ProductQuery = {}) => request<ProductPage>(`/products${toQueryString(query)}`),
  filters: () => request<Filters>('/products/filters'),
  product: (slug: string) => request<ProductDetail>(`/products/${encodeURIComponent(slug)}`),
  suggest: (q: string) => request<{ items: ProductCard[] }>(`/search/suggest${toQueryString({ q })}`),

  me: () => request<{ user: User }>('/auth/me'),
  login: (email: string, password: string) =>
    request<{ user: User }>('/auth/login', { method: 'POST', body: json({ email, password }) }),
  register: (email: string, password: string, displayName?: string) =>
    request<{ user: User }>('/auth/register', {
      method: 'POST',
      body: json({ email, password, displayName }),
    }),
  logout: () => request<void>('/auth/logout', { method: 'POST' }),

  orders: () => request<{ items: Order[] }>('/orders'),
  order: (reference: string) => request<{ order: Order }>(`/orders/${encodeURIComponent(reference)}`),
  createOrder: (items: CreateOrderRequest['items']) =>
    request<{ order: Order }>('/orders', { method: 'POST', body: json({ items }) }),
  /** The idempotency key must be stable across retries of the same payment attempt. */
  payOrder: (reference: string, idempotencyKey: string, cardNumber?: string) =>
    request<{ order: Order }>(`/orders/${encodeURIComponent(reference)}/pay`, {
      method: 'POST',
      headers: { 'Idempotency-Key': idempotencyKey },
      body: json(cardNumber ? { cardNumber } : {}),
    }),
}

/** Format integer cents as a price, e.g. 1999 -> "€19.99". */
export function formatPrice(cents: number, currency = 'EUR'): string {
  return new Intl.NumberFormat('en-IE', { style: 'currency', currency }).format(cents / 100)
}

export function isBanner(item: ProductCard | Banner): item is Banner {
  return 'imageUrl' in item
}
