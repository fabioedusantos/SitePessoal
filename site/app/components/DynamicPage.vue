<script setup lang="ts">
import type { PageBuilderPayload } from '~/types/content'
const props = defineProps<{ payload: PageBuilderPayload }>()
const page = computed(() => props.payload.page)
useSeoMeta({
  title: () => page.value.seo_title || page.value.title,
  description: () => page.value.seo_description || '',
  ogTitle: () => page.value.seo_title || page.value.title,
  ogDescription: () => page.value.seo_description || '',
  ogImage: () => page.value.og_image_url || 'https://fbsantos.com.br/brand/og-fbsantos.png',
  robots: () => Number(page.value.noindex) === 1 ? 'noindex,nofollow' : 'index,follow'
})
useHead(() => ({ link: page.value.canonical_url ? [{ rel:'canonical', href:page.value.canonical_url }] : [] }))
</script>

<template>
  <div class="dynamic-page">
    <DynamicSection v-for="section in payload.sections" :key="section.id" :section="section" />
  </div>
</template>
