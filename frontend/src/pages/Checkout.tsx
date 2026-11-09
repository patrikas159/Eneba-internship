import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { ApiError, api, formatPrice, type Order } from '../api/client'
import { OrderItems, OrderStatus } from '../components/OrderView'
import { ErrorMessage, Spinner } from '../components/Status'

const DECLINE_CARD = '4000 0000 0000 0002'

export default function Checkout() {
  const { reference = '' } = useParams()
  // Remount per order so the idempotency key and form state never leak between orders.
  return <CheckoutOrder key={reference} reference={reference} />
}

function CheckoutOrder({ reference }: { reference: string }) {
  const queryClient = useQueryClient()
  const query = useQuery({ queryKey: ['orders', reference], queryFn: () => api.order(reference) })

  if (query.isPending) return <Spinner />
  if (query.isError) return <ErrorMessage error={query.error} onRetry={() => query.refetch()} />

  const order = query.data.order
  const setOrder = (o: Order) => {
    queryClient.setQueryData(['orders', reference], { order: o })
    queryClient.invalidateQueries({ queryKey: ['orders'], exact: true })
  }

  return (
    <div className="checkout">
      <header className="page-head">
        <h1>Checkout</h1>
        <span className="muted">
          Order {order.reference} · <OrderStatus status={order.status} />
        </span>
      </header>

      <div className="cart">
        <section>
          {order.status === 'paid' && (
            <div className="notice notice--success" role="status">
              <span>Payment complete. Your keys are below and are saved in your order history.</span>
              <Link to={`/orders/${order.reference}`} className="btn btn--ghost btn--sm">
                View order
              </Link>
            </div>
          )}
          {(order.status === 'cancelled' || order.status === 'failed') && (
            <div className="notice notice--error" role="alert">
              <span>This order can no longer be paid; the reserved keys were released.</span>
              <Link to="/store" className="btn btn--ghost btn--sm">
                Back to the store
              </Link>
            </div>
          )}
          <OrderItems order={order} />
        </section>

        <aside className="cart__summary">
          <h2>Summary</h2>
          <div className="cart__row cart__total">
            <span>Total</span>
            <strong>{formatPrice(order.totalCents, order.currency)}</strong>
          </div>
          {order.status === 'pending' && (
            <PaymentForm order={order} onPaid={setOrder} onStale={query.refetch} />
          )}
        </aside>
      </div>
    </div>
  )
}

/** One idempotency key per payment attempt, kept across reloads so a retry never charges twice. */
function storedKey(reference: string, fresh = false): string {
  const storageKey = `eneba.pay.${reference}`
  try {
    const existing = sessionStorage.getItem(storageKey)
    if (existing && !fresh) return existing
  } catch {
    // Storage blocked: fall back to an in-memory key.
  }
  const key = crypto.randomUUID()
  try {
    sessionStorage.setItem(storageKey, key)
  } catch {
    // ignore
  }
  return key
}

function PaymentForm({ order, onPaid, onStale }: { order: Order; onPaid: (o: Order) => void; onStale: () => void }) {
  const [card, setCard] = useState('4242 4242 4242 4242')
  const [idempotencyKey, setIdempotencyKey] = useState(() => storedKey(order.reference))

  const pay = useMutation({
    mutationFn: () => api.payOrder(order.reference, idempotencyKey, card),
    onSuccess: ({ order: paid }) => onPaid(paid),
    onError: (e) => {
      if (!(e instanceof ApiError)) return // network error: keep the key so a retry is a replay
      if (e.status === 402) setIdempotencyKey(storedKey(order.reference, true)) // declined: next try is a new attempt
      if (e.status === 409) onStale() // already paid, expired or cancelled: reload the order
    },
  })

  function submit(e: FormEvent) {
    e.preventDefault()
    pay.mutate()
  }

  return (
    <form className="pay-form" onSubmit={submit}>
      {order.expiresAt && <Countdown until={order.expiresAt} onExpire={onStale} />}
      <label className="field">
        <span>Card number</span>
        <input
          className="input"
          inputMode="numeric"
          autoComplete="cc-number"
          value={card}
          onChange={(e) => setCard(e.target.value)}
          required
        />
      </label>
      <div className="field-row">
        <label className="field">
          <span>Expiry</span>
          <input className="input" defaultValue="12/30" autoComplete="cc-exp" />
        </label>
        <label className="field">
          <span>CVC</span>
          <input className="input" defaultValue="123" autoComplete="cc-csc" />
        </label>
      </div>
      {pay.isError && <ErrorMessage error={pay.error} />}
      <button type="submit" className="btn btn--primary btn--block" disabled={pay.isPending}>
        {pay.isPending ? 'Processing...' : `Pay ${formatPrice(order.totalCents, order.currency)}`}
      </button>
      <p className="muted cart__hint">
        Payments are simulated. Use <code>{DECLINE_CARD}</code> to test a declined card.
      </p>
    </form>
  )
}

function Countdown({ until, onExpire }: { until: string; onExpire: () => void }) {
  const end = new Date(until).getTime()
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    if (Date.now() >= end) return
    const id = setInterval(() => {
      const t = Date.now()
      setNow(t)
      if (t >= end) {
        clearInterval(id)
        onExpire()
      }
    }, 1000)
    return () => clearInterval(id)
  }, [end, onExpire])

  const left = Math.max(0, Math.floor((end - now) / 1000))

  const mm = String(Math.floor(left / 60)).padStart(2, '0')
  const ss = String(left % 60).padStart(2, '0')
  return (
    <p className={`countdown${left < 60 ? ' countdown--low' : ''}`}>
      {left > 0 ? (
        <>
          Keys reserved for <strong>{mm}:{ss}</strong>
        </>
      ) : (
        'Reservation expired'
      )}
    </p>
  )
}
