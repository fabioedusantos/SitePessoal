export function usePublicApi() {
  const config = useRuntimeConfig()
  const baseURL = import.meta.server ? config.apiBaseInternal : config.public.apiBase

  return $fetch.create({
    baseURL,
    retry: 1,
    timeout: 8000
  })
}
