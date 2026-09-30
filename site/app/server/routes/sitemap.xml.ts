export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const base = 'https://fbsantos.com.br'
  const pages: Array<{ slug: string; updated_at?: string }> = []

  try {
    const result = await $fetch<{ data: Array<{ slug: string; updated_at?: string }> }>(
      `${config.apiBaseInternal}/public/pages`,
      { timeout: 4000 }
    )
    pages.push(...result.data)
  } catch {
    pages.push({ slug: 'home' })
  }

  setHeader(event, 'content-type', 'application/xml; charset=utf-8')
  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${pages.map((page) => {
  const path = page.slug === 'home' ? '/' : `/${page.slug}`
  const lastmod = page.updated_at ? `<lastmod>${new Date(page.updated_at).toISOString()}</lastmod>` : ''
  return `  <url><loc>${base}${path}</loc>${lastmod}<changefreq>${page.slug === 'home' ? 'weekly' : 'monthly'}</changefreq><priority>${page.slug === 'home' ? '1.0' : '0.8'}</priority></url>`
}).join('\n')}
</urlset>`
})
