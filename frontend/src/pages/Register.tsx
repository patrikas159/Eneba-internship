import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import { Field } from '../components/AuthForm'
import { fieldErrors, safeNext } from '../utils'
import { ErrorMessage } from '../components/Status'
import { useAuth } from '../hooks/useAuth'

export default function Register() {
  const { user, register } = useAuth()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const next = safeNext(params.get('next'))
  const [email, setEmail] = useState('')
  const [displayName, setDisplayName] = useState('')
  const [password, setPassword] = useState('')

  if (user && !register.isSuccess) return <Navigate to={next} replace />

  function submit(e: FormEvent) {
    e.preventDefault()
    register.mutate(
      { email, password, displayName: displayName.trim() || undefined },
      { onSuccess: () => navigate(next, { replace: true }) },
    )
  }

  const errors = fieldErrors(register.error)
  return (
    <section className="auth">
      <h1>Create account</h1>
      <form className="auth__form" onSubmit={submit} noValidate>
        <Field label="Email" error={errors.email}>
          <input
            className="input"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </Field>
        <Field label="Display name (optional)" error={errors.displayName}>
          <input
            className="input"
            autoComplete="nickname"
            maxLength={80}
            value={displayName}
            onChange={(e) => setDisplayName(e.target.value)}
          />
        </Field>
        <Field label="Password (at least 8 characters)" error={errors.password}>
          <input
            className="input"
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </Field>
        {register.isError && Object.keys(errors).length === 0 && <ErrorMessage error={register.error} />}
        <button type="submit" className="btn btn--primary btn--block" disabled={register.isPending}>
          {register.isPending ? 'Creating account...' : 'Create account'}
        </button>
      </form>
      <p className="muted">
        Already have an account? <Link to={`/login?next=${encodeURIComponent(next)}`}>Log in</Link>
      </p>
    </section>
  )
}
