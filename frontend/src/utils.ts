import { ApiError } from './api/client'

export function formatDate(iso: string): string {
  return new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
}

/** Only same-site paths are allowed as a post-login redirect (no open redirects). */
export function safeNext(next: string | null): string {
  return next && next.startsWith('/') && !next.startsWith('//') ? next : '/'
}

/** Per-field messages from a 422/409 response (`fields: {email: '...'}`). */
export function fieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError)) return {}
  const fields = error.body.fields
  return fields && typeof fields === 'object' ? (fields as Record<string, string>) : {}
}
