const apiBaseInternal = process.env.NUXT_API_BASE_INTERNAL || 'http://localhost:8080/api/v1'
const backendOrigin = new URL(apiBaseInternal).origin

export default defineNuxtConfig({
  compatibilityDate: '2025-01-01',
  devtools: { enabled: process.env.NODE_ENV !== 'production' },
  ssr: true,
  css: ['~/assets/css/main.css', '~/assets/css/brand-theme.css'],
  runtimeConfig: {
    apiBaseInternal,
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || '/api/v1',
      // Chave pública fornecida em runtime pelo Docker (NUXT_PUBLIC_HCAPTCHA_SITE_KEY).
      // Nunca incorporar a chave de teste ao build de produção.
      hcaptchaSiteKey: '',
      gaMeasurementId: process.env.NUXT_PUBLIC_GA_MEASUREMENT_ID || ''
    }
  },
  routeRules: {
    '/uploads/**': { proxy: `${backendOrigin}/uploads/**` }
  },
  app: {
    pageTransition: { name: 'page', mode: 'out-in' },
    head: {
      htmlAttrs: { lang: 'pt-BR' },
      titleTemplate: '%s',
      meta: [
        { name: 'theme-color', content: '#f7fbff' },
        { name: 'color-scheme', content: 'light' },
        { name: 'author', content: 'Fábio Eduardo dos Santos' },
        { name: 'robots', content: 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' }
      ],
      link: [
        { rel: 'icon', href: '/favicon.ico?v=20261002', sizes: 'any' },
        { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg?v=20261002' },
        { rel: 'icon', type: 'image/png', sizes: '32x32', href: '/favicon-32x32.png?v=20261002' },
        { rel: 'icon', type: 'image/png', sizes: '16x16', href: '/favicon-16x16.png?v=20261002' },
        { rel: 'icon', type: 'image/png', sizes: '512x512', href: '/favicon.png?v=20261002' },
        { rel: 'apple-touch-icon', sizes: '180x180', href: '/brand/apple-touch-icon.png?v=20261002' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap' }
      ]
    }
  },
})
