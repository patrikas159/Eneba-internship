import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { api, isBanner, type HomeBlock, type ProductCard as ProductCardData } from '../api/client'
import { BannerCarousel } from '../components/BannerCarousel'
import { ProductCard } from '../components/ProductCard'
import { ErrorMessage, Spinner } from '../components/Status'

/** Store link that shows the full list behind a product block. */
function seeAllLink(block: HomeBlock): string {
  switch (block.type) {
    case 'deals':
      return '/store?sort=discount'
    case 'new_releases':
      return '/store?sort=newest'
    case 'genre':
      return typeof block.settings.genre === 'string'
        ? `/store?genre=${encodeURIComponent(block.settings.genre)}`
        : '/store'
    default:
      return '/store'
  }
}

/** Renders whatever blocks the backend layout returns, in order. Nothing is hardcoded here. */
export default function Home() {
  const home = useQuery({ queryKey: ['home'], queryFn: api.home })

  if (home.isPending) return <Spinner />
  if (home.isError) return <ErrorMessage error={home.error} onRetry={() => home.refetch()} />

  return (
    <div className="home">
      {home.data.blocks.map((block) => {
        if (block.itemKind === 'banner') {
          return <BannerCarousel key={block.id} banners={block.items.filter(isBanner)} />
        }
        const products = block.items.filter((i): i is ProductCardData => !isBanner(i))
        if (products.length === 0) return null
        return (
          <section key={block.id} className="block">
            <div className="block__head">
              <h2>{block.title}</h2>
              <Link to={seeAllLink(block)} className="block__more">
                See all
              </Link>
            </div>
            <div className="row">
              {products.map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </section>
        )
      })}
    </div>
  )
}
