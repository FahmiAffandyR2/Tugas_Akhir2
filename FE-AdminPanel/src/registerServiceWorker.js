export default function registerServiceWorker() {
  if (!('serviceWorker' in navigator) || process.env.NODE_ENV !== 'production') {
    return
  }

  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/service-worker.js')
      .then(registration => {
        registration.addEventListener('updatefound', () => {
          const worker = registration.installing
          if (!worker) return

          worker.addEventListener('statechange', () => {
            if (worker.state === 'installed' && navigator.serviceWorker.controller) {
              worker.postMessage({ type: 'SKIP_WAITING' })
            }
          })
        })
      })
      .catch(error => {
        console.error('Service worker registration failed', error)
      })
  })

  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (window.__ezbusRefreshing) return
    window.__ezbusRefreshing = true
    window.location.reload()
  })
}
