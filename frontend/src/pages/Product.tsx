import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ApiError, api, formatPrice, type Offer, type ProductDetail } from '../api/client'
import { SimilarGames } from '../components/SimilarGames'
import { ErrorMessage, Spinner } from '../components/Status'
import { MAX_QUANTITY, useCart } from '../hooks/useCart'

export default function Product() {
  const { slug = '' } = useParams()
  const product = useQuery({ queryKey: ['product', slug], queryFn: () => api.product(slug) })

  // Opening another product (e.g. from Similar games at the bottom) should start at the top.
  useEffect(() => {
    window.scrollTo(0, 0)
  }, [slug])

  if (product.isPending) return <Spinner />
  if (product.isError) {
    if (product.error instanceof ApiError && product.error.status === 404) {
      return (
        <section className="empty-state">
          <h1>Product not found</h1>
          <p className="muted">It may have been removed or the link is wrong.</p>
          <Link to="/store" className="btn btn--primary">
            Browse the store
          </Link>
        </section>
      )
    }
    return <ErrorMessage error={product.error} onRetry={() => product.refetch()} />
  }

  const p = product.data
  return (
    <>
      <article className="product">
        <div className="product__cover">
          {p.coverUrl ? <img src={p.coverUrl} alt={p.name} /> : <div className="card__placeholder">{p.name}</div>}
        </div>

        <div className="product__info">
          <h1>{p.name}</h1>
          <div className="product__tags">
            <span className="tag">{p.platform}</span>
            <span className="tag">{p.region}</span>
            {p.genres.map((g) => (
              <Link key={g} to={`/store?genre=${encodeURIComponent(g)}`} className="tag tag--link">
                {g}
              </Link>
            ))}
          </div>
          {p.releaseDate && (
            <p className="muted">
              Released{' '}
              {new Date(p.releaseDate).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}
            </p>
          )}
          {p.description && <p className="product__description">{p.description}</p>}

          <Offers product={p} />
        </div>
      </article>
      <SimilarGames product={p} />
    </>
  )
}

function Offers({ product }: { product: ProductDetail }) {
  // Cheapest in-stock offers first; sold-out ones stay visible at the bottom.
  const offers = [...product.offers].sort(
    (a, b) => Number(b.inStock) - Number(a.inStock) || a.priceCents - b.priceCents,
  )

  return (
    <section className="offers">
      <h2>
        {offers.length} {offers.length === 1 ? 'offer' : 'offers'} from sellers
      </h2>
      {offers.length === 0 && <p className="muted">No seller offers this product right now.</p>}
      <ul className="offers__list">
        {offers.map((offer, i) => (
          <OfferRow key={offer.id} offer={offer} product={product} best={i === 0 && offer.inStock} />
        ))}
      </ul>
    </section>
  )
}

function OfferRow({ offer, product, best }: { offer: Offer; product: ProductDetail; best: boolean }) {
  const cart = useCart()
  const [added, setAdded] = useState(false)
  const inCart = cart.items.find((i) => i.offerId === offer.id)?.quantity ?? 0
  const limit = Math.min(offer.stock, MAX_QUANTITY)
  const canAdd = offer.inStock && inCart < limit

  function addToCart() {
    cart.add({
      offerId: offer.id,
      productSlug: product.slug,
      productName: product.name,
      coverUrl: product.coverUrl,
      platform: product.platform,
      region: product.region,
      sellerName: offer.seller.name,
      priceCents: offer.priceCents,
      currency: offer.currency,
    })
    setAdded(true)
  }

  return (
    <li className={`offer${best ? ' offer--best' : ''}${offer.inStock ? '' : ' offer--out'}`}>
      <div className="offer__seller">
        <strong>{offer.seller.name}</strong>
        <span className="muted">★ {offer.seller.rating.toFixed(1)}</span>
        {best && <span className="offer__badge">Best price</span>}
      </div>
      <div className="offer__stock muted">{offer.inStock ? `${offer.stock} in stock` : 'Sold out'}</div>
      <div className="offer__price">
        {offer.originalPriceCents !== null && offer.originalPriceCents > offer.priceCents && (
          <s className="muted">{formatPrice(offer.originalPriceCents, offer.currency)}</s>
        )}
        <strong>{formatPrice(offer.priceCents, offer.currency)}</strong>
      </div>
      <div className="offer__action">
        <button type="button" className="btn btn--primary btn--sm" disabled={!canAdd} onClick={addToCart}>
          {offer.inStock ? 'Add to cart' : 'Sold out'}
        </button>
        {inCart > 0 && (
          <Link to="/cart" className="offer__incart">
            {added ? 'Added! ' : ''}
            {inCart} in cart
          </Link>
        )}
      </div>
    </li>
  )
}
