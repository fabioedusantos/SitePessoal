const ALLOWED_INLINE_TAGS = ['strong', 'em', 'u'] as const

function escapeHtml(value: string) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
}

/**
 * Renderiza apenas a formatação mínima produzida pelo CMS.
 * Todo o restante continua escapado, evitando que HTML arbitrário salvo no banco seja executado.
 */
export function safeRichText(value?: string | null) {
  if (!value) return ''

  let html = escapeHtml(value)
  for (const tag of ALLOWED_INLINE_TAGS) {
    html = html
      .replace(new RegExp(`&lt;${tag}&gt;`, 'gi'), `<${tag}>`)
      .replace(new RegExp(`&lt;\\/${tag}&gt;`, 'gi'), `</${tag}>`)
  }

  return html
    .replace(/&lt;br\s*\/?&gt;/gi, '<br>')
    .replace(/\r?\n/g, '<br>')
}

/** Remove somente as marcações permitidas pelo editor para contextos sem HTML, como SEO/Schema.org. */
export function plainRichText(value?: string | null) {
  if (!value) return ''
  return value
    .replace(/<\/?(?:strong|em|u)>/gi, '')
    .replace(/<br\s*\/?>/gi, ' ')
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}
