import { ApiError } from '../api/client'

export function Spinner() {
  return <div className="spinner" role="status" aria-label="Loading" />
}

export function ErrorMessage({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  const message = error instanceof ApiError || error instanceof Error ? error.message : 'Something went wrong.'
  return (
    <div className="notice notice--error" role="alert">
      <span>{message}</span>
      {onRetry && (
        <button className="btn btn--ghost btn--sm" onClick={onRetry}>
          Try again
        </button>
      )}
    </div>
  )
}
