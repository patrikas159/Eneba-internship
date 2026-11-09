import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { useCart } from '../hooks/useCart'
import { SearchBox } from './SearchBox'

export function Header() {
  const { user, logout } = useAuth()
  const { count } = useCart()
  const navigate = useNavigate()
  const location = useLocation()

  return (
    <header className="header">
      <div className="header__inner container">
        <Link to="/" className="logo" aria-label="Home">
          <span className="logo__mark">e</span>
          <span className="logo__text">neba</span>
        </Link>
        <nav className="header__nav">
          <NavLink to="/store">Store</NavLink>
        </nav>
        <SearchBox key={location.key} />
        <div className="header__actions">
          <Link to="/cart" className="cart-link" aria-label={`Cart, ${count} items`}>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
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
    </header>
  )
}
