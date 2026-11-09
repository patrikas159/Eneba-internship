import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'

/** A cart line keeps a snapshot for display; the server re-prices on checkout. */
export interface CartItem {
  offerId: number
  quantity: number
  productSlug: string
  productName: string
  coverUrl: string | null
  platform: string
  region: string
  sellerName: string
  priceCents: number
  currency: string
}

interface CartContextValue {
  items: CartItem[]
  count: number
  totalCents: number
  add: (item: Omit<CartItem, 'quantity'>, quantity?: number) => void
  setQuantity: (offerId: number, quantity: number) => void
  remove: (offerId: number) => void
  clear: () => void
}

const STORAGE_KEY = 'eneba.cart.v1'
export const MAX_QUANTITY = 10

const CartContext = createContext<CartContextValue | null>(null)

function load(): CartItem[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    const parsed: unknown = raw ? JSON.parse(raw) : []
    return Array.isArray(parsed) ? (parsed as CartItem[]) : []
  } catch {
    return []
  }
}

export function CartProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<CartItem[]>(load)

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items))
    } catch {
      // Storage unavailable (private mode): cart still works for this tab.
    }
  }, [items])

  const add = useCallback((item: Omit<CartItem, 'quantity'>, quantity = 1) => {
    setItems((prev) => {
      const existing = prev.find((i) => i.offerId === item.offerId)
      if (!existing) return [...prev, { ...item, quantity: Math.min(quantity, MAX_QUANTITY) }]
      return prev.map((i) =>
        i.offerId === item.offerId ? { ...i, quantity: Math.min(i.quantity + quantity, MAX_QUANTITY) } : i,
      )
    })
  }, [])

  const setQuantity = useCallback((offerId: number, quantity: number) => {
    setItems((prev) =>
      quantity <= 0
        ? prev.filter((i) => i.offerId !== offerId)
        : prev.map((i) => (i.offerId === offerId ? { ...i, quantity: Math.min(quantity, MAX_QUANTITY) } : i)),
    )
  }, [])

  const remove = useCallback((offerId: number) => {
    setItems((prev) => prev.filter((i) => i.offerId !== offerId))
  }, [])

  const clear = useCallback(() => setItems([]), [])

  const value = useMemo<CartContextValue>(
    () => ({
      items,
      count: items.reduce((n, i) => n + i.quantity, 0),
      totalCents: items.reduce((n, i) => n + i.quantity * i.priceCents, 0),
      add,
      setQuantity,
      remove,
      clear,
    }),
    [items, add, setQuantity, remove, clear],
  )

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

export function useCart(): CartContextValue {
  const ctx = useContext(CartContext)
  if (!ctx) throw new Error('useCart must be used inside <CartProvider>')
  return ctx
}
