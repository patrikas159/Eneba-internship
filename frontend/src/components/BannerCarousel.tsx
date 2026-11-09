import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import type { Banner } from '../api/client'

const INTERVAL_MS = 6000

export function BannerCarousel({ banners }: { banners: Banner[] }) {
  const [index, setIndex] = useState(0)
  const [paused, setPaused] = useState(false)

  useEffect(() => {
    if (paused || banners.length < 2) return
    const t = setInterval(() => setIndex((i) => (i + 1) % banners.length), INTERVAL_MS)
    return () => clearInterval(t)
  }, [paused, banners.length])

  if (banners.length === 0) return null
  const current = banners[index % banners.length]

  return (
    <section
      className="carousel"
      aria-roledescription="carousel"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
    >
      <Link to={current.linkUrl} className="carousel__slide" key={current.id}>
        <img src={current.imageUrl} alt="" />
        <div className="carousel__text">
          <h2>{current.title}</h2>
          {current.subtitle && <p>{current.subtitle}</p>}
          <span className="btn btn--primary">Shop now</span>
        </div>
      </Link>
      {banners.length > 1 && (
        <div className="carousel__dots">
          {banners.map((b, i) => (
            <button
              key={b.id}
              className={i === index ? 'is-active' : ''}
              aria-label={`Show banner ${i + 1}: ${b.title}`}
              aria-current={i === index}
              onClick={() => setIndex(i)}
            />
          ))}
        </div>
      )}
    </section>
  )
}
