import { Outlet } from 'react-router-dom'
import { Header } from './Header'

export function Layout() {
  return (
    <>
      <Header />
      <main className="main container">
        <Outlet />
      </main>
      <footer className="footer">
        <div className="container">Internship demo store. Payments are simulated; keys are not real.</div>
      </footer>
    </>
  )
}
