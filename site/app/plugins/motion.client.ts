export default defineNuxtPlugin((nuxtApp) => {
  if (import.meta.server || window.__FBSANTOS_MOTION__) return

  window.__FBSANTOS_MOTION__ = true

  const root = document.documentElement
  root.classList.add('motion-ready')

  const revealSelector = [
    '.page-section .section-heading',
    '.dynamic-section .section-heading',
    '.dynamic-section .dynamic-copy-block',
    '.dynamic-section .dynamic-image-card',
    '.dynamic-section .project-story-grid article',
    '.dynamic-section .resource-links a',
    '.dynamic-section .dynamic-contact-panel',
    '.page-section > .container > .split-section',
    '.split-sticky',
    '.proof-strip',
    '.stat',
    '.timeline-item',
    '.timeline-meta',
    '.timeline-content',
    '.timeline-org-logo',
    '.project-card',
    '.dynamic-section .project-story-grid article',
    '.dynamic-section .resource-links a',
    '.project-card-content > *',
    '.capability-card',
    '.engineering-foundations-copy',
    '.engineering-statement',
    '.engineering-pillars article',
    '.teaching-bridge',
    '.tech-group',
    '.info-card',
    '.info-card-logo',
    '.certification-logo',
    '.logo-cloud-item',
    '.product-spotlight',
    '.product-spotlight-copy > *',
    '.product-device',
    '.about-card',
    '.about-story-copy',
    '.about-story-card',
    '.about-portrait',
    '.human-photo',
    '.human-copy',
    '.personal-photo',
    '.personal-story > div:last-child',
    '.contact-panel',
    '.contact-direct-card',
    '.contact-form-intro',
    '.contact-topic-list li',
    '.contact-form',
    '.contact-form label',
    '.contact-form-footer',
    '.presentation-feature',
    '.presentation-copy > *',
    '.presentation-details-heading',
    '.presentation-topic-card',
    '.presentation-related > div:first-child',
    '.presentation-related-project',
    '.video-card',
    '.academic-board-stat',
    '.academic-board-year-label',
    '.academic-board-card',
    '.project-detail-nav',
    '.project-detail-section',
    '.project-tech-section',
    '.project-video-section',
    '.project-gallery-section',
    '.project-deep-dive-card',
    '.project-gallery-item',
    '.related-experience-item',
    '.related-experience-logo',
    '.project-hero-media',
    '.project-hero-grid > div:first-child > *',
    '.contact-hero-grid > *',
    '.about-hero-grid > *',
    '.project-external-links',
    '.section-action',
    '.knowledge-links',
    '.publication-card-link',
    '.error-box',
    '.site-footer .footer-grid > *'
  ].join(',')

  const revealFromLeftSelector = [
    '.split-sticky',
    '.project-detail-nav',
    '.contact-form-intro',
    '.human-copy',
    '.about-story-copy',
    '.academic-board-year-label'
  ].join(',')

  const revealFromRightSelector = [
    '.about-portrait',
    '.human-photo',
    '.personal-photo',
    '.contact-direct-card',
    '.project-hero-media'
  ].join(',')

  const revealScaleSelector = [
    '.logo-cloud-item',
    '.project-card',
    '.project-card-content > *',
    '.product-spotlight',
    '.product-device',
    '.project-gallery-item',
    '.video-card'
  ].join(',')

  const pointerSurfaceSelector = [
    '.project-card',
    '.project-card-content > *',
    '.capability-card',
    '.engineering-pillars article',
    '.tech-group',
    '.info-card',
    '.info-card-logo',
    '.certification-logo',
    '.about-card',
    '.about-story-card',
    '.contact-direct-card',
    '.academic-board-card',
    '.related-experience-item',
    '.related-experience-logo',
    '.project-deep-dive-card',
    '.presentation-feature',
    '.presentation-topic-card',
    '.presentation-related-project'
  ].join(',')

  const observer = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (!entry.isIntersecting) continue
      const element = entry.target as HTMLElement
      element.classList.add('is-visible')
      observer.unobserve(element)
    }
  }, {
    threshold: 0.1,
    rootMargin: '0px 0px -6% 0px'
  })

  const bindRevealElements = () => {
    const elements = Array.from(document.querySelectorAll<HTMLElement>(revealSelector))
    const siblingIndex = new Map<Element, number>()

    for (const element of elements) {
      if (element.dataset.revealBound === 'true') continue

      const parent = element.parentElement
      const index = parent ? (siblingIndex.get(parent) ?? 0) : 0
      if (parent) siblingIndex.set(parent, index + 1)

      element.dataset.revealBound = 'true'
      element.classList.add('reveal-on-scroll')
      element.style.setProperty('--reveal-delay', `${Math.min(index % 5, 4) * 55}ms`)

      if (element.matches(revealFromLeftSelector)) element.classList.add('reveal-from-left')
      else if (element.matches(revealFromRightSelector)) element.classList.add('reveal-from-right')
      else if (element.matches(revealScaleSelector)) element.classList.add('reveal-scale')

      observer.observe(element)
    }
  }

  const bindPointerSurfaces = () => {
    const surfaces = Array.from(document.querySelectorAll<HTMLElement>(pointerSurfaceSelector))

    for (const surface of surfaces) {
      if (surface.dataset.pointerBound === 'true') continue
      surface.dataset.pointerBound = 'true'
      surface.classList.add('motion-pointer-surface')

      surface.addEventListener('pointermove', (event) => {
        if (event.pointerType === 'touch') return
        const rect = surface.getBoundingClientRect()
        surface.style.setProperty('--pointer-x', `${event.clientX - rect.left}px`)
        surface.style.setProperty('--pointer-y', `${event.clientY - rect.top}px`)
      }, { passive: true })
    }
  }

  const normalizeImages = () => {
    const images = Array.from(document.querySelectorAll<HTMLImageElement>('img'))
    for (const image of images) {
      if (image.dataset.imageBound === 'true') continue
      image.dataset.imageBound = 'true'
      image.decoding = 'async'
      image.addEventListener('load', () => image.classList.add('is-image-loaded'), { once: true })
      image.addEventListener('error', () => image.classList.add('is-image-error'), { once: true })
      if (image.complete && image.naturalWidth > 0) image.classList.add('is-image-loaded')
    }
  }

  let scrollFrame = 0
  const updateScrollState = () => {
    scrollFrame = 0
    const scrollTop = window.scrollY || document.documentElement.scrollTop
    const scrollable = Math.max(document.documentElement.scrollHeight - window.innerHeight, 1)
    const progress = Math.min(Math.max(scrollTop / scrollable, 0), 1)

    root.style.setProperty('--scroll-progress', progress.toFixed(4))
    document.querySelector('.site-header')?.classList.toggle('is-scrolled', scrollTop > 18)
  }

  const onScroll = () => {
    if (scrollFrame) return
    scrollFrame = window.requestAnimationFrame(updateScrollState)
  }

  const refresh = () => {
    bindRevealElements()
    bindPointerSurfaces()
    normalizeImages()
    window.requestAnimationFrame(() => {
      updateScrollState()
      document.body.classList.add('site-loaded')
    })
  }

  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('resize', onScroll, { passive: true })

  // Não altere o DOM antes da hidratação do Vue/Nuxt terminar.
  // O page:finish pode ocorrer no carregamento inicial antes de app:mounted,
  // então ele é ignorado até que a aplicação esteja efetivamente montada.
  let appMounted = false

  nuxtApp.hook('app:mounted', () => {
    appMounted = true
    window.requestAnimationFrame(refresh)
  })

  nuxtApp.hook('page:finish', () => {
    if (!appMounted) return
    window.requestAnimationFrame(refresh)
  })
})

declare global {
  interface Window {
    __FBSANTOS_MOTION__?: boolean
  }
}
