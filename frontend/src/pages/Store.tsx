import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api, type Filters, type ProductQuery, type ProductSort } from '../api/client'
import { ProductCard } from '../components/ProductCard'
import { ErrorMessage, Spinner } from '../components/Status'

const PAGE_SIZE = 24

const SORT_LABELS: Record<ProductSort, string> = {
  relevance: 'Relevance',
  newest: 'Newest',
  price_asc: 'Price: low to high',
  price_desc: 'Price: high to low',
  discount: 'Biggest discount',
  name: 'Name (A-Z)',
}

/** Multi-value filters are stored comma-separated in the URL, like the API expects. */
const splitList = (value: string | null) => (value ? value.split(',').filter(Boolean) : [])

function toInt(value: string | null): number | undefined {
  if (value === null || value === '') return undefined
  const n = Number(value)
  return Number.isFinite(n) && n >= 0 ? Math.floor(n) : undefined
}

/** Everything the listing depends on is read from the URL, so links and Back/Forward just work. */
function queryFromParams(params: URLSearchParams): ProductQuery {
  const sort = params.get('sort')
  return {
    q: params.get('q') ?? undefined,
    platform: params.get('platform') ?? undefined,
    region: params.get('region') ?? undefined,
    genre: params.get('genre') ?? undefined,
    minPrice: toInt(params.get('minPrice')),
    maxPrice: toInt(params.get('maxPrice')),
    inStock: params.get('inStock') === 'true' ? true : undefined,
    sort: sort && sort in SORT_LABELS ? (sort as ProductSort) : undefined,
    page: toInt(params.get('page')) || 1,
    limit: PAGE_SIZE,
  }
}

export default function Store() {
  const [params, setParams] = useSearchParams()
  const query = queryFromParams(params)

  const filters = useQuery({ queryKey: ['filters'], queryFn: api.filters, staleTime: 5 * 60_000 })
  const products = useQuery({
    queryKey: ['products', query],
    queryFn: () => api.products(query),
    placeholderData: keepPreviousData,
  })

  /** Apply changes to the URL; any filter change resets pagination. */
  function update(changes: Record<string, string | undefined>, keepPage = false) {
    const next = new URLSearchParams(params)
    for (const [key, value] of Object.entries(changes)) {
      if (value === undefined || value === '') next.delete(key)
      else next.set(key, value)
    }
    if (!keepPage) next.delete('page')
    setParams(next)
  }

  function toggleListValue(key: 'platform' | 'region', value: string) {
    const current = splitList(params.get(key))
    const nextList = current.includes(value) ? current.filter((v) => v !== value) : [...current, value]
    update({ [key]: nextList.join(',') })
  }

  const hasFilters = ['platform', 'region', 'genre', 'minPrice', 'maxPrice', 'inStock', 'q'].some((k) => params.has(k))
  const total = products.data?.total ?? 0
  const pageCount = Math.max(1, Math.ceil(total / PAGE_SIZE))
  const page = query.page ?? 1

  return (
    <div className="store">
      <aside className="store__filters">
        {filters.isPending && <Spinner />}
        {filters.isError && <ErrorMessage error={filters.error} onRetry={() => filters.refetch()} />}
        {filters.data && (
          <FilterPanel
            filters={filters.data}
            platforms={splitList(params.get('platform'))}
            regions={splitList(params.get('region'))}
            genre={params.get('genre') ?? ''}
            inStock={query.inStock === true}
            minPrice={query.minPrice}
            maxPrice={query.maxPrice}
            onToggle={toggleListValue}
            onUpdate={update}
          />
        )}
        {hasFilters && (
          <button type="button" className="btn btn--ghost btn--sm btn--block" onClick={() => setParams({})}>
            Clear all filters
          </button>
        )}
      </aside>

      <section className="store__results">
        <div className="store__toolbar">
          <div>
            <h1 className="store__title">{query.q ? `Results for "${query.q}"` : 'Store'}</h1>
            {products.data && (
              <span className="muted">
                {total} {total === 1 ? 'product' : 'products'}
              </span>
            )}
          </div>
          <label className="store__sort">
            <span className="muted">Sort by</span>
            <select
              className="input"
              value={query.sort ?? (query.q ? 'relevance' : 'newest')}
              onChange={(e) => update({ sort: e.target.value })}
            >
              {(Object.keys(SORT_LABELS) as ProductSort[])
                .filter((s) => s !== 'relevance' || query.q)
                .map((s) => (
                  <option key={s} value={s}>
                    {SORT_LABELS[s]}
                  </option>
                ))}
            </select>
          </label>
        </div>

        {products.isPending && <Spinner />}
        {products.isError && <ErrorMessage error={products.error} onRetry={() => products.refetch()} />}
        {products.data && products.data.items.length === 0 && (
          <div className="empty-state">
            <h2>No products found</h2>
            <p className="muted">Try another spelling or remove some filters.</p>
          </div>
        )}
        {products.data && products.data.items.length > 0 && (
          <div className={`grid${products.isPlaceholderData ? ' is-loading' : ''}`}>
            {products.data.items.map((p) => (
              <ProductCard key={p.id} product={p} />
            ))}
          </div>
        )}

        {pageCount > 1 && (
          <nav className="pagination" aria-label="Pages">
            <button
              type="button"
              className="btn btn--ghost btn--sm"
              disabled={page <= 1}
              onClick={() => update({ page: String(page - 1) }, true)}
            >
              Previous
            </button>
            <span className="muted">
              Page {page} of {pageCount}
            </span>
            <button
              type="button"
              className="btn btn--ghost btn--sm"
              disabled={page >= pageCount}
              onClick={() => update({ page: String(page + 1) }, true)}
            >
              Next
            </button>
          </nav>
        )}
      </section>
    </div>
  )
}

interface FilterPanelProps {
  filters: Filters
  platforms: string[]
  regions: string[]
  genre: string
  inStock: boolean
  minPrice?: number
  maxPrice?: number
  onToggle: (key: 'platform' | 'region', value: string) => void
  onUpdate: (changes: Record<string, string | undefined>) => void
}

function FilterPanel(props: FilterPanelProps) {
  const { filters, onToggle, onUpdate } = props
  return (
    <>
      <fieldset className="filter">
        <legend>Availability</legend>
        <label className="filter__option">
          <input
            type="checkbox"
            checked={props.inStock}
            onChange={(e) => onUpdate({ inStock: e.target.checked ? 'true' : undefined })}
          />
          <span>In stock only</span>
        </label>
      </fieldset>

      <fieldset className="filter">
        <legend>Platform</legend>
        {filters.platforms.map((f) => (
          <label key={f.value} className="filter__option">
            <input
              type="checkbox"
              checked={props.platforms.includes(f.value)}
              onChange={() => onToggle('platform', f.value)}
            />
            <span>{f.value}</span>
            <small className="muted">{f.count}</small>
          </label>
        ))}
      </fieldset>

      <fieldset className="filter">
        <legend>Region</legend>
        {filters.regions.map((f) => (
          <label key={f.value} className="filter__option">
            <input
              type="checkbox"
              checked={props.regions.includes(f.value)}
              onChange={() => onToggle('region', f.value)}
            />
            <span>{f.value}</span>
            <small className="muted">{f.count}</small>
          </label>
        ))}
      </fieldset>

      <fieldset className="filter">
        <legend>Genre</legend>
        <select className="input" value={props.genre} onChange={(e) => onUpdate({ genre: e.target.value })}>
          <option value="">All genres</option>
          {filters.genres.map((f) => (
            <option key={f.value} value={f.value}>
              {f.value} ({f.count})
            </option>
          ))}
        </select>
      </fieldset>

      {/* Remount when the URL changes so the inputs show the applied values. */}
      <PriceFilter
        key={`${props.minPrice ?? ''}-${props.maxPrice ?? ''}`}
        minCents={props.minPrice}
        maxCents={props.maxPrice}
        bounds={filters.price}
        onApply={(minPrice, maxPrice) => onUpdate({ minPrice, maxPrice })}
      />
    </>
  )
}

const centsToEuros = (cents?: number) => (cents === undefined ? '' : String(cents / 100))

function eurosToCents(value: string): string | undefined {
  const n = Number(value.replace(',', '.'))
  return value.trim() === '' || !Number.isFinite(n) || n < 0 ? undefined : String(Math.round(n * 100))
}

function PriceFilter(props: {
  minCents?: number
  maxCents?: number
  bounds: Filters['price']
  onApply: (minPrice?: string, maxPrice?: string) => void
}) {
  const [min, setMin] = useState(centsToEuros(props.minCents))
  const [max, setMax] = useState(centsToEuros(props.maxCents))

  function submit(e: FormEvent) {
    e.preventDefault()
    props.onApply(eurosToCents(min), eurosToCents(max))
  }

  const placeholder = (cents?: number) => (cents === undefined ? '' : String(Math.floor(cents / 100)))

  return (
    <form onSubmit={submit}>
      <fieldset className="filter">
        <legend>Price (€)</legend>
        <div className="filter__price">
          <input
            className="input"
            inputMode="decimal"
            aria-label="Minimum price in euros"
            placeholder={placeholder(props.bounds.minCents) || 'Min'}
            value={min}
            onChange={(e) => setMin(e.target.value)}
          />
          <span className="muted">-</span>
          <input
            className="input"
            inputMode="decimal"
            aria-label="Maximum price in euros"
            placeholder={props.bounds.maxCents === undefined ? 'Max' : String(Math.ceil(props.bounds.maxCents / 100))}
            value={max}
            onChange={(e) => setMax(e.target.value)}
          />
          <button type="submit" className="btn btn--ghost btn--sm">
            Go
          </button>
        </div>
      </fieldset>
    </form>
  )
}
