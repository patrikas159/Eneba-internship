import { Link } from 'react-router-dom'
import { siDiscord, siFacebook, siInstagram, siReddit, siX } from 'simple-icons'
import { useAuth } from '../hooks/useAuth'
import { CATALOG_LINKS, REPO_URL } from '../links'

const YEAR = new Date().getFullYear()

/** Eneba's real social profiles, taken from the eneba.com footer. */
const SOCIALS = [
  { name: 'Facebook', href: 'https://www.facebook.com/enebaglobal/', icon: siFacebook },
  { name: 'X', href: 'https://x.com/eneba_games', icon: siX },
  { name: 'Instagram', href: 'https://www.instagram.com/enebagames/', icon: siInstagram },
  { name: 'Discord', href: 'https://discord.gg/enebagames', icon: siDiscord },
  { name: 'Reddit', href: 'https://www.reddit.com/r/Enebagames/', icon: siReddit },
] as const

const external = { target: '_blank', rel: 'noopener noreferrer' } as const

export function Footer() {
  const { user } = useAuth()

  return (
    <footer className="footer">
      <div className="footer__grid container">
        <div className="footer__brand">
          <Link to="/" className="logo" aria-label="Eneba home">
            <span className="logo__mark">e</span>
            <span className="logo__text">neba</span>
          </Link>
          <p className="footer__blurb">
            An Eneba-style digital key marketplace built as an internship demo. Payments are simulated
            and keys are not real.
          </p>
        </div>

        <nav className="footer__col" aria-labelledby="footer-shop">
          <h2 id="footer-shop" className="footer__heading">Shop</h2>
          <ul>
            {CATALOG_LINKS.map((link) => (
              <li key={link.label}>
                <Link to={link.to}>{link.label}</Link>
              </li>
            ))}
          </ul>
        </nav>

        <nav className="footer__col" aria-labelledby="footer-account">
          <h2 id="footer-account" className="footer__heading">Account</h2>
          <ul>
            <li><Link to="/cart">Cart</Link></li>
            <li><Link to="/orders">My orders</Link></li>
            {!user && <li><Link to="/login">Log in</Link></li>}
            {!user && <li><Link to="/register">Create account</Link></li>}
          </ul>
        </nav>

        <nav className="footer__col" aria-labelledby="footer-about">
          <h2 id="footer-about" className="footer__heading">About</h2>
          <ul>
            <li><Link to="/about">About us</Link></li>
            <li><a href={REPO_URL} {...external}>Source code on GitHub</a></li>
          </ul>
        </nav>

        <div className="footer__col">
          <h2 className="footer__heading">Follow us</h2>
          <ul className="footer__socials">
            {SOCIALS.map(({ name, href, icon }) => (
              <li key={name}>
                <a href={href} {...external} className="footer__social" aria-label={`Eneba on ${name}`}>
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                    <path d={icon.path} />
                  </svg>
                </a>
              </li>
            ))}
          </ul>
        </div>
      </div>

      <div className="footer__bottom">
        <div className="container">
          © {YEAR} Internship demo project. Not affiliated with or operated by Eneba.
        </div>
      </div>
    </footer>
  )
}
