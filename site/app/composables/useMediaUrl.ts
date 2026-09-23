/**
 * Os uploads são armazenados no backend como caminhos /uploads/...
 * Em desenvolvimento a API pública geralmente está em outra origem (porta 8080).
 * Em produção, com API relativa, o nginx do site encaminha /uploads/ ao backend.
 * URLs absolutas e recursos estáticos do site permanecem inalterados.
 */
export function resolveMediaUrl(value: string | null | undefined, publicApiBase: string | null | undefined): string {
  const url = String(value || '').trim()
  if (!url.startsWith('/uploads/')) return url

  const apiBase = String(publicApiBase || '').trim()
  if (!/^https?:\/\//i.test(apiBase)) return url

  try {
    return `${new URL(apiBase).origin}${url}`
  } catch {
    return url
  }
}

export function useMediaUrl() {
  const apiBase = useRuntimeConfig().public.apiBase
  return (url?: string | null) => resolveMediaUrl(url, String(apiBase || ''))
}
