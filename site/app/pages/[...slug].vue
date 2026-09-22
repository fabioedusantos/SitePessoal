<script setup lang="ts">
import type { PageBuilderPayload } from '~/types/content'

const route = useRoute()
const api = usePublicApi()
const slug = computed(() => {
  const value = route.params.slug
  return Array.isArray(value) ? value.join('/') : String(value || '').replace(/^\/+|\/+$/g, '')
})

const { data, error } = await useAsyncData<PageBuilderPayload>(
  () => `page:${slug.value}`,
  () => api(`/public/page/${encodeURI(slug.value)}`),
  { watch: [slug] }
)

if (error.value || !data.value) {
  const source = error.value as any
  const status = Number(source?.statusCode || source?.status || source?.response?.status || 0)
  if (status === 404) {
    throw createError({ statusCode: 404, message: 'Página não encontrada.' })
  }
  throw createError({
    statusCode: 503,
    message: 'Não foi possível carregar esta página. Verifique a conexão com a API.'
  })
}

const payload = computed(() => data.value as PageBuilderPayload)
</script>

<template>
  <main class="page-route-root">
    <DynamicPage :payload="payload" />
  </main>
</template>
