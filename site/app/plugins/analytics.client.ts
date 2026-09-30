declare global {
  interface Window {
    dataLayer?: unknown[][]
  }
}

export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig()
  const measurementId = String(config.public.gaMeasurementId || '').trim().toUpperCase()

  if (!/^G-[A-Z0-9]+$/.test(measurementId)) return

  window.dataLayer = window.dataLayer || []
  const gtag = (...args: unknown[]) => {
    window.dataLayer?.push(args)
  }

  gtag('js', new Date())
  gtag('config', measurementId, {
    send_page_view: false,
    transport_type: 'beacon'
  })

  const script = document.createElement('script')
  script.async = true
  script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`
  script.dataset.fbsantosAnalytics = 'true'
  document.head.appendChild(script)

  const trackPageView = () => {
    gtag('event', 'page_view', {
      page_title: document.title,
      page_location: window.location.href,
      page_path: `${window.location.pathname}${window.location.search}`
    })
  }

  nuxtApp.hook('page:finish', trackPageView)
})
