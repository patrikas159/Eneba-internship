import type { ReactNode } from 'react'

export function Field({
  label,
  error,
  children,
}: {
  label: string
  error?: string
  children: ReactNode
}) {
  return (
    <label className={`field${error ? ' field--invalid' : ''}`}>
      <span>{label}</span>
      {children}
      {error && <small className="field__error">{error}</small>}
    </label>
  )
}
