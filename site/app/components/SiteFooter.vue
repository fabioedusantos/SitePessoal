<script setup lang="ts">
const mediaUrl = useMediaUrl()
const { data: siteContent } = await useSiteContentData()
const links = computed(() => siteContent.value?.navigation.filter(item => item.location === 'footer') || [])
const settings = computed(() => siteContent.value?.settings || {})
</script>

<template>
  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <img class="footer-logo" :src="mediaUrl(settings.footer_logo_url || '/brand/fbsantos-logo-complete-light.svg')" :alt="settings.footer_logo_alt || 'FBSantos, Fábio Santos Desenvolvimentos'" width="300" height="74">
        <p>{{ settings.footer_lead || 'Engenharia de software, liderança, produto e educação conectados por uma trajetória prática.' }}</p>
      </div>
      <div class="footer-links">
        <template v-for="item in links" :key="item.id">
          <a v-if="Number(item.is_external) === 1" :href="item.resolved_url || item.url || '#'" target="_blank" rel="noopener noreferrer">{{ item.label }} ↗</a>
          <NuxtLink v-else :to="item.resolved_url || item.url || '/'">{{ item.label }}</NuxtLink>
        </template>
      </div>
      <div class="footer-meta">
        <span>{{ settings.footer_title || 'FBSantos.com.br' }}</span>
        <span>© {{ new Date().getFullYear() }}</span>
        <span>{{ settings.footer_body || 'Feito para contar uma carreira em evolução.' }}</span>
      </div>
    </div>
  </footer>
</template>
