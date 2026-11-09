import { Link } from 'react-router-dom'
import { REPO_URL } from '../links'

const FEATURES = [
  'Store with genre, platform and price filters, sorting and paging',
  'Header search with autocomplete that tolerates typos',
  'Product pages comparing offers from several sellers',
  'Cart, checkout with a simulated payment and an instant digital key',
  'Sign up, sign in and order history',
]

const STACK = [
  ['Frontend', 'React 19, TypeScript, Vite, React Router, TanStack Query, plain CSS design tokens'],
  ['Backend', 'PHP 8.5, Symfony 7.4, Doctrine ORM 3.7, REST/JSON on FrankenPHP'],
  ['Database', 'PostgreSQL 18 with pg_trgm for fuzzy search'],
  ['Hosting', 'Render (app) and Neon (database), both on free tiers'],
] as const

export default function About() {
  return (
    <article className="about">
      <h1>About this project</h1>
      <p className="about__lead">
        This site is an internship assignment: a digital game key marketplace that looks and works like
        eneba.com. It is a demo, not a real shop.
      </p>

      <div className="about__notice" role="note">
        <strong>Nothing here is for sale.</strong> Payments are simulated (any card number works, except{' '}
        <code>4000 0000 0000 0002</code>, which is always declined) and the keys you receive are not real.
      </div>

      <section className="about__section">
        <h2>What you can try</h2>
        <ul className="about__list">
          {FEATURES.map((feature) => (
            <li key={feature}>{feature}</li>
          ))}
        </ul>
      </section>

      <section className="about__section">
        <h2>How it is built</h2>
        <dl className="about__stack">
          {STACK.map(([term, detail]) => (
            <div key={term}>
              <dt>{term}</dt>
              <dd>{detail}</dd>
            </div>
          ))}
        </dl>
        <p className="muted">
          Game names, covers and artwork come from the IGDB API, fetched once and stored with the seed data.
        </p>
      </section>

      <div className="about__actions">
        <Link to="/store" className="btn btn--primary">Browse the store</Link>
        <a href={REPO_URL} target="_blank" rel="noopener noreferrer" className="btn btn--ghost">
          View the source on GitHub
        </a>
      </div>
    </article>
  )
}
