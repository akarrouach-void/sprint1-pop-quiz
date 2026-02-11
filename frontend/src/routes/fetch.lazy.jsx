import { createLazyFileRoute } from '@tanstack/react-router'
import { useEffect } from "react"

export const Route = createLazyFileRoute('/fetch')({
  component: Fetch,
})

function Fetch() {
  useEffect(() => {
    fetch(`${import.meta.env.VITE_API_URL}/fetch`)
    .then(response => response.json())
    .then(data => console.log(data))
  },[])

  return <div className="p-2">Hello from fetch!</div>
}