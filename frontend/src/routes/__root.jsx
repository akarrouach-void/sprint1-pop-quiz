import { createRootRoute, Link, Outlet } from '@tanstack/react-router'
import { TanStackRouterDevtools } from '@tanstack/router-devtools'

export const Route = createRootRoute({
  component: () => (
    <>
      <div className="p-2 flex gap-2">
        <Link to="/" className="[&.active]:font-bold">
          Home
        </Link>{' '}
        <Link to="/fetch" className="[&.active]:font-bold">
          Fetch
        </Link>
        {' '}
        <Link to="/users" className="[&.active]:font-bold">
          Users
        </Link>
        {' '}
        <Link to="/security" className="[&.active]:font-bold">
          Security
        </Link>
      </div>
      <hr />
      <Outlet />
      <TanStackRouterDevtools />
    </>
  ),
})