import { useMutation } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import { ApiError, api, formatPrice } from '../api/client'
import { useAuth } from '../hooks/useAuth'
import { MAX_QUANTITY, useCart, type CartItem } from '../hooks/useCart'

export default function Cart() {
  const cart = useCart()
  const { user } = useAuth()
  const navigate = useNavigate()

  const checkout = useMutation({
    mutationFn: () => api.createOrder(cart.items.map((i) => ({ offerId: i.offerId, quantity: i.quantity }))),
    onSuccess: ({ order }) => {
      cart.clear()
      navigate(`/checkout/${order.reference}`)
    },
  })

  function startCheckout() {
    if (!user) {
      navigate('/login?next=/cart')
      return
    }
    checkout.mutate()
  }

  if (cart.items.length === 0) {
    return (
      <section className="empty-state">
        <h1>Your cart is empty</h1>
        <p className="muted">Find a game you like and add a key from one of the sellers.</p>
        <Link to="/store" className="btn btn--primary">
          Browse the store
        </Link>
      </section>
    )
  }

  const error = checkout.error instanceof ApiError ? checkout.error : null
  const problemOfferId = typeof error?.body.offerId === 'number' ? error.body.offerId : null
  const available = typeof error?.body.available === 'number' ? error.body.available : null
  const currency = cart.items[0].currency

  return (
    <div className="cart">
      <section>
        <h1>Cart</h1>
        <ul className="cart__list">
          {cart.items.map((item) => (
            <CartLine key={item.offerId} item={item} hasProblem={item.offerId === problemOfferId} />
          ))}
        </ul>
      </section>

      <aside className="cart__summary">
        <h2>Summary</h2>
        <div className="cart__row">
          <span className="muted">Items</span>
          <span>{cart.count}</span>
        </div>
        <div className="cart__row cart__total">
          <span>Total</span>
          <strong>{formatPrice(cart.totalCents, currency)}</strong>
        </div>

        {checkout.isError && (
          <div className="notice notice--error" role="alert">
            <span>
              {checkout.error.message}
              {problemOfferId !== null && available !== null && available > 0 && (
                <>
                  {' '}
                  <button
                    type="button"
                    className="link-button"
                    onClick={() => {
                      cart.setQuantity(problemOfferId, available)
                      checkout.reset()
                    }}
                  >
                    Change to {available}
                  </button>
                </>
              )}
              {problemOfferId !== null && (available === null || available === 0) && (
                <>
                  {' '}
                  <button
                    type="button"
                    className="link-button"
                    onClick={() => {
                      cart.remove(problemOfferId)
                      checkout.reset()
                    }}
                  >
                    Remove it
                  </button>
                </>
              )}
            </span>
          </div>
        )}

        <button
          type="button"
          className="btn btn--primary btn--block"
          disabled={checkout.isPending || user === undefined}
          onClick={startCheckout}
        >
          {checkout.isPending ? 'Reserving keys...' : user === null ? 'Log in to check out' : 'Checkout'}
        </button>
        <p className="muted cart__hint">Keys are reserved for you while you pay. Prices are confirmed at checkout.</p>
      </aside>
    </div>
  )
}

function CartLine({ item, hasProblem }: { item: CartItem; hasProblem: boolean }) {
  const cart = useCart()
  return (
    <li className={`cart-line${hasProblem ? ' cart-line--problem' : ''}`}>
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
        <button type="button" className="link-button cart-line__remove" onClick={() => cart.remove(item.offerId)}>
          Remove
        </button>
      </div>
      <div className="stepper" aria-label={`Quantity of ${item.productName}`}>
        <button
          type="button"
          aria-label="Decrease quantity"
          onClick={() => cart.setQuantity(item.offerId, item.quantity - 1)}
        >
          −
        </button>
        <span>{item.quantity}</span>
        <button
          type="button"
          aria-label="Increase quantity"
          disabled={item.quantity >= MAX_QUANTITY}
          onClick={() => cart.setQuantity(item.offerId, item.quantity + 1)}
        >
          +
        </button>
      </div>
      <strong className="cart-line__price">{formatPrice(item.priceCents * item.quantity, item.currency)}</strong>
    </li>
  )
}
