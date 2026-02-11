import { createLazyFileRoute } from '@tanstack/react-router'

export const Route = createLazyFileRoute('/security')({
  component: Security,
})

function Security() {
  return <img src="https://images.unsplash.com/photo-1711950901044-fa6215a9c59b?q=80&w=2970&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="" />
}