import { Link } from 'react-router-dom'
import { formatPrice, type ProductCard as ProductCardData } from '../api/client'

export function ProductCard({ product }: { product: ProductCardData }) {
  const p = product
  return (
    <Link to={`/product/${p.slug}`} className="card">
      <div className="card__cover">
        {p.coverUrl ? <img src={p.coverUrl} alt="" loading="lazy" /> : <div className="card__placeholder">{p.name}</div>}
        {p.discountPercent ? <span className="card__discount">-{p.discountPercent}%</span> : null}
      </div>
      <div className="card__body">
        <div className="card__name" title={p.name}>
          {p.name}
        </div>
        <div className="card__meta">
          {p.platform} · {p.region}
        </div>
        {p.inStock && p.priceCents !== null ? (
          <div className="card__price">
            <span className="card__from">From</span>
            {p.originalPriceCents && p.originalPriceCents > p.priceCents && (
              <s className="card__old">{formatPrice(p.originalPriceCents, p.currency)}</s>
            )}
            <strong>{formatPrice(p.priceCents, p.currency)}</strong>
          </div>
        ) : (
          <div className="card__price card__price--out">Out of stock</div>
        )}
      </div>
    </Link>
  )
}
