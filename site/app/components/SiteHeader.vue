<script setup lang="ts">
const mediaUrl = useMediaUrl()
const open = ref(false)
const route = useRoute()
watch(() => route.fullPath, () => { open.value = false })
const { data: siteContent } = await useSiteContentData()
const items = computed(() => siteContent.value?.navigation.filter(item => item.location === 'header') || [])
const settings = computed(() => siteContent.value?.settings || {})
const isContact = (url: string) => url === '/contato'
</script>

<template>
  <header class="site-header">
    <div class="container header-inner">
      <NuxtLink to="/" class="brand" aria-label="Fábio Santos, início">
        <img class="brand-logo" :src="mediaUrl(settings.header_logo_url || '/brand/fbsantos-logo-primary.svg')" :alt="settings.header_logo_alt || 'FBSantos'" width="188" height="46">
      </NuxtLink>
      <button class="nav-toggle" type="button" :aria-expanded="open" aria-label="Abrir menu" @click="open = !open"><span /><span /></button>
      <nav class="main-nav" :class="{ 'is-open': open }" aria-label="Navegação principal">
        <template v-for="item in items" :key="item.id">
          <a v-if="Number(item.is_external) === 1" :href="item.resolved_url || item.url || '#'" target="_blank" rel="noopener noreferrer">{{ item.label }} ↗</a>
          <NuxtLink v-else :to="item.resolved_url || item.url || '/'" :class="{ active: route.path === (item.resolved_url || item.url), 'nav-contact': isContact(item.resolved_url || item.url || '') }">{{ item.label }}</NuxtLink>
        </template>
      </nav>
    </div>
  </header>
</template>
