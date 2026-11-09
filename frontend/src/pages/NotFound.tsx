import { Link } from 'react-router-dom'

export default function NotFound() {
  return (
    <section className="empty-state">
      <h1>Page not found</h1>
      <Link to="/" className="btn btn--primary">
        Back to the homepage
      </Link>
    </section>
  )
}
