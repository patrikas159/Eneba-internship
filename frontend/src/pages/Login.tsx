import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import { Field } from '../components/AuthForm'
import { fieldErrors, safeNext } from '../utils'
import { ErrorMessage } from '../components/Status'
import { useAuth } from '../hooks/useAuth'

export default function Login() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const next = safeNext(params.get('next'))
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')

  if (user && !login.isSuccess) return <Navigate to={next} replace />

  function submit(e: FormEvent) {
    e.preventDefault()
    login.mutate({ email, password }, { onSuccess: () => navigate(next, { replace: true }) })
  }

  const errors = fieldErrors(login.error)
  return (
    <section className="auth">
      <h1>Log in</h1>
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
        <Field label="Password" error={errors.password}>
          <input
            className="input"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </Field>
        {login.isError && Object.keys(errors).length === 0 && <ErrorMessage error={login.error} />}
        <button type="submit" className="btn btn--primary btn--block" disabled={login.isPending}>
          {login.isPending ? 'Logging in...' : 'Log in'}
        </button>
      </form>
      <p className="muted">
        New here? <Link to={`/register?next=${encodeURIComponent(next)}`}>Create an account</Link>
      </p>
    </section>
  )
}
