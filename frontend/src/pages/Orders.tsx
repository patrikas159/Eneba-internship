import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { api, formatPrice } from '../api/client'
import { OrderStatus } from '../components/OrderView'
import { formatDate } from '../utils'
import { ErrorMessage, Spinner } from '../components/Status'

export default function Orders() {
  const query = useQuery({ queryKey: ['orders'], queryFn: api.orders })

  if (query.isPending) return <Spinner />
  if (query.isError) return <ErrorMessage error={query.error} onRetry={() => query.refetch()} />

  const orders = query.data.items
  if (orders.length === 0) {
    return (
      <section className="empty-state">
        <h1>No orders yet</h1>
        <p className="muted">Keys you buy will show up here.</p>
        <Link to="/store" className="btn btn--primary">
          Browse the store
        </Link>
      </section>
    )
  }

  return (
    <section>
      <h1>My orders</h1>
      <ul className="orders">
        {orders.map((order) => {
          const names = [...new Set(order.items.map((i) => i.productName))]
          const covers = [...new Set(order.items.flatMap((i) => (i.coverUrl ? [i.coverUrl] : [])))]
          const keyCount = order.items.reduce((n, i) => n + i.quantity, 0)
          return (
            <li key={order.reference}>
              <Link
                to={order.status === 'pending' ? `/checkout/${order.reference}` : `/orders/${order.reference}`}
                className="orders__row"
              >
                <div className="orders__covers">
                  {covers.slice(0, 3).map((url) => (
                    <img key={url} src={url} alt="" />
                  ))}
                </div>
                <div className="orders__info">
                  <strong>{names.slice(0, 2).join(', ') + (names.length > 2 ? ` +${names.length - 2} more` : '')}</strong>
                  <span className="muted">
                    {order.reference} · {formatDate(order.createdAt)} · {keyCount} {keyCount === 1 ? 'key' : 'keys'}
                  </span>
                </div>
                <OrderStatus status={order.status} />
                <strong className="orders__total">{formatPrice(order.totalCents, order.currency)}</strong>
              </Link>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
