import type { SiteContentPayload } from '~/types/content'

export async function useSiteContentData() {
  const api = usePublicApi()
  return useAsyncData<SiteContentPayload>('site-editorial-content', () => api('/public/site-content'))
}

export async function usePageData(slug: string) {
  const api = usePublicApi()
  return useAsyncData(`page:${slug}`, () => api(`/public/page/${encodeURI(slug)}`))
}
