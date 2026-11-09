import { useLayoutEffect, useRef } from 'react'
import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { useCart } from '../hooks/useCart'
import { CATALOG_LINKS } from '../links'
import { SearchBox } from './SearchBox'

export function Header() {
  const { user, logout } = useAuth()
  const { count } = useCart()
  const navigate = useNavigate()
  const location = useLocation()
  const headerRef = useRef<HTMLElement>(null)

  // The header is position: fixed, so publish its real height (it wraps on mobile) for the page offset.
  useLayoutEffect(() => {
    const el = headerRef.current
    if (!el) return
    const root = document.documentElement
    const sync = () => root.style.setProperty('--header-offset', `${el.offsetHeight}px`)
    sync()
    const observer = new ResizeObserver(sync)
    observer.observe(el)
    return () => observer.disconnect()
  }, [])

  const onStore = location.pathname === '/store'
  const sort = new URLSearchParams(location.search).get('sort')
  const activeSort = sort === 'discount' || sort === 'newest' ? sort : null

  return (
    <header className="header" ref={headerRef}>
      <div className="header__top">
        <div className="header__inner container">
          <Link to="/" className="logo" aria-label="Eneba home">
            <span className="logo__mark">e</span>
            <span className="logo__text">neba</span>
          </Link>
          <SearchBox key={location.key} />
          <div className="header__actions">
            <Link to="/cart" className="cart-link" aria-label={`Cart, ${count} items`}>
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                <path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6" />
                <circle cx="10" cy="20" r="1.4" />
                <circle cx="18" cy="20" r="1.4" />
              </svg>
              {count > 0 && <span className="badge">{count}</span>}
            </Link>
            {user ? (
              <div className="user-menu">
                <NavLink to="/orders">My orders</NavLink>
                <span className="user-menu__name" title={user.email}>
                  {user.displayName}
                </span>
                <button
                  className="btn btn--ghost btn--sm"
                  onClick={() => logout.mutate(undefined, { onSuccess: () => navigate('/') })}
                >
                  Log out
                </button>
              </div>
            ) : (
              user === null && (
                <Link to="/login" className="btn btn--primary btn--sm">
                  Log in
                </Link>
              )
            )}
          </div>
        </div>
      </div>
      <nav className="subnav" aria-label="Catalog">
        <ul className="subnav__links container">
          {CATALOG_LINKS.map((link) => {
            const active = onStore && activeSort === link.sort
            return (
              <li key={link.label}>
                <Link
                  to={link.to}
                  className={`subnav__link${active ? ' is-active' : ''}`}
                  aria-current={active ? 'page' : undefined}
                >
                  {link.label}
                </Link>
              </li>
            )
          })}
        </ul>
      </nav>
    </header>
  )
}
