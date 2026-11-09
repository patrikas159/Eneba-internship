import { useQuery } from '@tanstack/react-query'
import { useEffect, useId, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { api, formatPrice } from '../api/client'
import { useDebounce } from '../hooks/useDebounce'

/**
 * Global search: debounced typo-tolerant suggestions, Enter opens the store results.
 * The header remounts it on every navigation (key = location.key), which resets the text
 * to the current store query and closes the list.
 */
export function SearchBox() {
  const navigate = useNavigate()
  const location = useLocation()
  const [params] = useSearchParams()
  const [text, setText] = useState(() => (location.pathname === '/store' ? (params.get('q') ?? '') : ''))
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(-1)
  const rootRef = useRef<HTMLFormElement>(null)
  const listId = useId()

  const term = useDebounce(text.trim(), 200)
  const suggest = useQuery({
    queryKey: ['suggest', term],
    queryFn: () => api.suggest(term),
    enabled: term.length >= 2,
    staleTime: 60_000,
    placeholderData: (prev) => prev,
  })
  const items = term.length >= 2 ? (suggest.data?.items ?? []) : []

  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      if (!rootRef.current?.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (active >= 0 && items[active]) {
      navigate(`/product/${items[active].slug}`)
    } else {
      const q = text.trim()
      navigate(q ? `/store?q=${encodeURIComponent(q)}` : '/store')
    }
    setOpen(false)
    setActive(-1)
  }

  const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setOpen(true)
      setActive((i) => Math.min(i + 1, items.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setActive((i) => Math.max(i - 1, -1))
    } else if (e.key === 'Escape') {
      setOpen(false)
      setActive(-1)
    }
  }

  const showList = open && term.length >= 2

  return (
    <form className="search" role="search" onSubmit={submit} ref={rootRef}>
      <input
        className="search__input"
        type="search"
        placeholder="Search games, gift cards..."
        aria-label="Search"
        role="combobox"
        aria-expanded={showList}
        aria-controls={listId}
        aria-autocomplete="list"
        value={text}
        onChange={(e) => {
          setText(e.target.value)
          setOpen(true)
          setActive(-1)
        }}
        onFocus={() => setOpen(true)}
        onKeyDown={onKeyDown}
      />
      <button className="search__button" type="submit" aria-label="Search">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
          <circle cx="11" cy="11" r="7" />
          <path d="m20 20-3.5-3.5" />
        </svg>
      </button>
      {showList && (
        <ul className="search__list" id={listId} role="listbox">
          {items.length === 0 && !suggest.isFetching && <li className="search__empty">No matches for “{term}”</li>}
          {items.map((p, i) => (
            <li
              key={p.id}
              role="option"
              aria-selected={i === active}
              className={`search__item${i === active ? ' is-active' : ''}`}
              onMouseEnter={() => setActive(i)}
              onMouseDown={(e) => {
                e.preventDefault()
                navigate(`/product/${p.slug}`)
                setOpen(false)
              }}
            >
              {p.coverUrl ? <img src={p.coverUrl} alt="" /> : <span className="search__noimg" />}
              <span className="search__name">
                {p.name}
                <small>
                  {p.platform} · {p.region}
                </small>
              </span>
              {p.priceCents !== null && <span className="search__price">{formatPrice(p.priceCents, p.currency)}</span>}
            </li>
          ))}
        </ul>
      )}
    </form>
  )
}
