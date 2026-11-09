import { useState } from 'react'
import { Link } from 'react-router-dom'
import { formatPrice, type Order, type OrderItem } from '../api/client'

const STATUS_LABEL: Record<Order['status'], string> = {
  pending: 'Awaiting payment',
  paid: 'Paid',
  cancelled: 'Cancelled',
  failed: 'Failed',
}

export function OrderStatus({ status }: { status: Order['status'] }) {
  return <span className={`status status--${status}`}>{STATUS_LABEL[status]}</span>
}

/** Order lines; for paid orders each line also lists its delivered key codes. */
export function OrderItems({ order }: { order: Order }) {
  return (
    <ul className="order-items">
      {order.items.map((item) => (
        <OrderLine key={item.offerId} item={item} currency={order.currency} />
      ))}
    </ul>
  )
}

function OrderLine({ item, currency }: { item: OrderItem; currency: string }) {
  return (
    <li className="order-line">
      <div className="order-line__main">
        <Link to={`/product/${item.productSlug}`} className="cart-line__cover">
          {item.coverUrl ? <img src={item.coverUrl} alt="" /> : null}
        </Link>
        <div className="cart-line__info">
          <Link to={`/product/${item.productSlug}`} className="cart-line__name">
            {item.productName}
          </Link>
          <span className="muted">
            {item.platform} · {item.region} · Sold by {item.sellerName}
          </span>
          <span className="muted">
            {item.quantity} × {formatPrice(item.unitPriceCents, currency)}
          </span>
        </div>
        <strong className="cart-line__price">{formatPrice(item.unitPriceCents * item.quantity, currency)}</strong>
      </div>
      {item.keys.length > 0 && (
        <ul className="keys" aria-label={`Keys for ${item.productName}`}>
          {item.keys.map((code) => (
            <KeyCode key={code} code={code} />
          ))}
        </ul>
      )}
    </li>
  )
}

function KeyCode({ code }: { code: string }) {
  const [copied, setCopied] = useState(false)

  async function copy() {
    try {
      await navigator.clipboard.writeText(code)
      setCopied(true)
      setTimeout(() => setCopied(false), 1500)
    } catch {
      // Clipboard can be unavailable (insecure context); the code stays selectable.
    }
  }

  return (
    <li className="key">
      <code>{code}</code>
      <button type="button" className="btn btn--ghost btn--sm" onClick={copy}>
        {copied ? 'Copied' : 'Copy'}
      </button>
    </li>
  )
}
