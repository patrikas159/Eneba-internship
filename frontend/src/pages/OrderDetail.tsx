import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { api, formatPrice } from '../api/client'
import { OrderItems, OrderStatus } from '../components/OrderView'
import { formatDate } from '../utils'
import { ErrorMessage, Spinner } from '../components/Status'

export default function OrderDetail() {
  const { reference = '' } = useParams()
  const query = useQuery({ queryKey: ['orders', reference], queryFn: () => api.order(reference) })

  if (query.isPending) return <Spinner />
  if (query.isError) return <ErrorMessage error={query.error} onRetry={() => query.refetch()} />

  const order = query.data.order
  return (
    <section className="order-detail">
      <Link to="/orders" className="muted">
        ← All orders
      </Link>
      <header className="page-head">
        <h1>Order {order.reference}</h1>
        <span className="muted">
          Placed {formatDate(order.createdAt)}
          {order.paidAt && <> · Paid {formatDate(order.paidAt)}</>} · <OrderStatus status={order.status} />
        </span>
      </header>

      {order.status === 'pending' && (
        <div className="notice notice--error" role="status">
          <span>This order is waiting for payment.</span>
          <Link to={`/checkout/${order.reference}`} className="btn btn--primary btn--sm">
            Pay now
          </Link>
        </div>
      )}

      <OrderItems order={order} />

      <div className="order-detail__total">
        <span>Total</span>
        <strong>{formatPrice(order.totalCents, order.currency)}</strong>
      </div>
    </section>
  )
}
