<script setup lang="ts">
import type { PageBuilderPayload } from '~/types/content'

const api = usePublicApi()
const { data, error } = await useAsyncData<PageBuilderPayload>('page:home', () => api('/public/page/home'))

if (error.value || !data.value) {
  const source = error.value as any
  const status = Number(source?.statusCode || source?.status || source?.response?.status || 0)
  if (status === 404) {
    throw createError({ statusCode: 404, message: 'Página inicial não encontrada.' })
  }
  throw createError({
    statusCode: 503,
    message: 'Não foi possível carregar a página inicial. Verifique a conexão com a API.'
  })
}

const payload = computed(() => data.value as PageBuilderPayload)
</script>

<template>
  <main class="page-route-root">
    <DynamicPage :payload="payload" />
  </main>
</template>
