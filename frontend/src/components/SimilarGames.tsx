import { useQueries } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { api, type ProductCard as ProductCardData, type ProductDetail } from '../api/client'
import { ProductCard } from './ProductCard'

const MAX_ITEMS = 5

/**
 * Up to five other products that share a genre with the current one, loaded from the catalog API.
 * Genres are queried in the product's own order (the first is the primary one), so games from the
 * primary genre come first and later genres only fill the remaining slots. Each query asks for one
 * extra row so the current product can be dropped and five still remain.
 * The section hides itself while loading, on error, or when nothing matches.
 */
export function SimilarGames({ product }: { product: ProductDetail }) {
  const genres = product.genres
  const results = useQueries({
    queries: genres.map((genre) => ({
      queryKey: ['products', { genre, limit: MAX_ITEMS + 1 }],
      queryFn: () => api.products({ genre, limit: MAX_ITEMS + 1 }),
    })),
  })

  // Wait for every genre so the list does not reshuffle while later queries arrive.
  if (genres.length === 0 || results.some((r) => r.isPending)) return null

  const seen = new Set([product.id])
  const similar: ProductCardData[] = []
  for (const r of results) {
    for (const p of r.data?.items ?? []) {
      if (similar.length === MAX_ITEMS) break
      if (seen.has(p.id)) continue
      seen.add(p.id)
      similar.push(p)
    }
  }
  if (similar.length === 0) return null

  const primary = genres[0]
  return (
    <section className="block similar" aria-labelledby="similar-title">
      <div className="block__head">
        <h2 id="similar-title">Similar games</h2>
        <Link to={`/store?genre=${encodeURIComponent(primary)}`} className="block__more">
          More {primary}
        </Link>
      </div>
      <div className="similar__grid">
        {similar.map((p) => (
          <ProductCard key={p.id} product={p} />
        ))}
      </div>
    </section>
  )
}
