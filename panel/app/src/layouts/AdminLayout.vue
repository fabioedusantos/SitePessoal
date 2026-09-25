<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { resources } from '../config/resources'
import { useAuth } from '../lib/auth'
import { api } from '../lib/api'

interface CmsPage {
  id: number
  title: string
  slug: string
  page_kind: string
  status: string
  sort_order: number
}

const router = useRouter()
const { clearSession } = useAuth()
const siteUrl = import.meta.env.VITE_SITE_URL || (import.meta.env.DEV ? 'http://localhost:3000' : 'https://fbsantos.com.br')
const pages = ref<CmsPage[]>([])

const contentKeys = ['experiences', 'organizations', 'projects', 'technologies', 'education', 'certifications', 'publications', 'evaluation-boards']

const primaryPages = computed(() => pages.value
  .filter(page => page.page_kind !== 'project')
  .sort((a, b) => Number(a.sort_order) - Number(b.sort_order) || Number(a.id) - Number(b.id)))

const contentItems = computed(() => contentKeys.map(key => resources[key]).filter(Boolean))

function pageLabel(page: CmsPage) {
  return page.slug === 'home' ? 'Início' : page.title
}

async function loadPages() {
  try {
    const result = await api<{ data: CmsPage[] }>('/admin/pages')
    pages.value = result.data || []
  } catch {
    pages.value = [
      { id: 0, title: 'Página inicial', slug: 'home', page_kind: 'home', status: 'published', sort_order: 10 },
      { id: 0, title: 'Trajetória', slug: 'trajetoria', page_kind: 'standard', status: 'published', sort_order: 20 },
      { id: 0, title: 'Projetos', slug: 'projetos', page_kind: 'standard', status: 'published', sort_order: 30 },
      { id: 0, title: 'Sobre', slug: 'sobre', page_kind: 'standard', status: 'published', sort_order: 40 },
      { id: 0, title: 'Formação', slug: 'formacao', page_kind: 'standard', status: 'published', sort_order: 50 },
      { id: 0, title: 'Palestras', slug: 'palestras', page_kind: 'standard', status: 'published', sort_order: 60 },
      { id: 0, title: 'Contato', slug: 'contato', page_kind: 'standard', status: 'published', sort_order: 70 }
    ]
  }
}

async function signOut() {
  try {
    await api('/admin/logout', { method: 'POST' })
  } finally {
    clearSession()
    router.push('/login')
  }
}

onMounted(loadPages)
</script>

<template>
  <div class="admin-shell">
    <aside class="sidebar">
      <RouterLink to="/" class="cms-brand">
        <img class="cms-brand-icon" src="/favicon.svg" alt="">
        <span class="cms-brand-copy"><strong>FBSantos</strong><small>CONTENT STUDIO</small></span>
      </RouterLink>

      <nav class="side-nav" aria-label="Administração do site">
        <div class="nav-group page-centric-nav">
          <p>Páginas do site</p>
          <RouterLink v-for="page in primaryPages" :key="page.slug" :to="`/pagina/${page.slug}`" class="page-nav-link">
            <span>{{ pageLabel(page) }}</span>
            <small v-if="page.status !== 'published'">Rascunho</small>
          </RouterLink>
        </div>

        <details class="advanced-nav content-source-nav">
          <summary>Conteúdo</summary>
          <p class="advanced-nav-help">Edite as entidades reais do site. Tecnologias, mídias e relacionamentos ficam dentro do conteúdo ao qual pertencem.</p>
          <div class="nav-group advanced-nav-links">
            <RouterLink v-for="item in contentItems" :key="item.key" :to="`/${item.key}`">
              {{ item.label }}
            </RouterLink>
          </div>
        </details>

        <details class="advanced-nav site-config-nav">
          <summary>Configurações do site</summary>
          <div class="nav-group advanced-nav-links">
            <RouterLink to="/apresentacao">Apresentação profissional</RouterLink>
            <RouterLink to="/configuracoes-site">Cabeçalho, rodapé e navegação</RouterLink>
          </div>
        </details>

      </nav>

      <div class="sidebar-footer">
        <RouterLink to="/analytics">Google Analytics</RouterLink>
        <RouterLink to="/emails">Envio de e-mails</RouterLink>
        <RouterLink to="/">Visão geral</RouterLink>
        <a :href="siteUrl" target="_blank" rel="noopener">Abrir site <span aria-hidden="true">↗</span></a>
        <button type="button" @click="signOut">Sair do painel</button>
      </div>
    </aside>

    <main class="admin-main">
      <RouterView />
    </main>
  </div>
</template>
