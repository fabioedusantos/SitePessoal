<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '../lib/api'

const counts = ref<Record<string, number>>({})
const loading = ref(true)
const error = ref('')

const labels: Record<string, string> = {
  experiences: 'Experiências',
  organizations: 'Organizações',
  projects: 'Projetos',
  technologies: 'Tecnologias',
  education: 'Formações',
  certifications: 'Certificações',
  publications: 'Publicações',
  evaluation_boards: 'Bancas avaliadoras',
  pages: 'Páginas'
}

const routes: Record<string, string> = {
  experiences: '/experiences',
  organizations: '/organizations',
  projects: '/projects',
  technologies: '/technologies',
  education: '/education',
  certifications: '/certifications',
  publications: '/publications',
  evaluation_boards: '/evaluation-boards',
  pages: '/pages'
}

const metrics = computed(() => Object.entries(counts.value).map(([key, value]) => ({
  key,
  label: labels[key] || key,
  value,
  route: routes[key]
})))

const quickLinks = [
  { to: '/pagina/home', label: 'Início', text: 'Hero, organizações, indicadores, trajetória, projetos, tecnologias e chamadas da Home.' },
  { to: '/pagina/trajetoria', label: 'Trajetória', text: 'Monte a página de carreira e escolha todas ou apenas algumas experiências para cada seção.' },
  { to: '/pagina/projetos', label: 'Projetos', text: 'Defina a apresentação geral dos cases e quais projetos aparecem em cada bloco.' },
  { to: '/pagina/sobre', label: 'Sobre', text: 'Organize a narrativa pessoal e profissional em seções livres.' },
  { to: '/pagina/formacao', label: 'Formação', text: 'Combine formação, certificações, docência e reconhecimentos.' },
  { to: '/pagina/palestras', label: 'Palestras', text: 'Organize palestras, publicações e apresentações da forma mais adequada.' },
  { to: '/pagina/contato', label: 'Contato', text: 'Edite abertura, chamadas e formulário de contato.' }
]

onMounted(async () => {
  try {
    const result = await api<{ counts: Record<string, number> }>('/admin/dashboard')
    counts.value = result.counts
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao carregar indicadores.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="dashboard-page">
    <header class="page-title dashboard-title">
      <div>
        <p class="overline">Visão geral</p>
        <h1>Conteúdo do site</h1>
        <p>O painel é organizado pelas páginas públicas. Abra uma página, escolha as seções e defina o que cada uma exibe.</p>
      </div>
      <div class="dashboard-actions">
        <RouterLink class="secondary-button link-button" to="/analytics">Ver Google Analytics</RouterLink>
        <RouterLink class="secondary-button link-button" to="/pagina/home">Editar página inicial</RouterLink>
      </div>
    </header>

    <div v-if="error" class="alert" role="alert">{{ error }}</div>

    <section class="metric-grid" aria-label="Resumo do conteúdo">
      <RouterLink
        v-for="metric in metrics"
        :key="metric.key"
        class="metric-card"
        :class="{ 'is-link': metric.route }"
        :to="metric.route || '/'"
      >
        <span>{{ metric.label }}</span>
        <strong>{{ metric.value }}</strong>
        <small v-if="metric.route">Gerenciar →</small>
      </RouterLink>
      <article v-if="loading" class="metric-card muted">
        <span>Carregando</span>
        <strong>…</strong>
      </article>
    </section>

    <section class="dashboard-section">
      <div class="dashboard-section-heading">
        <div>
          <p class="overline">Atalhos</p>
          <h2>Páginas do site</h2>
        </div>
        <p>O fluxo normal começa por aqui. Cadastros e relacionamentos ficam como fonte de conteúdo e não como navegação principal do CMS.</p>
      </div>
      <div class="quick-link-grid">
        <RouterLink v-for="item in quickLinks" :key="item.to" :to="item.to" class="quick-link-card">
          <div>
            <strong>{{ item.label }}</strong>
            <p>{{ item.text }}</p>
          </div>
          <span aria-hidden="true">→</span>
        </RouterLink>
      </div>
    </section>

    <section class="admin-panel">
      <div>
        <p class="overline">Gestão editorial</p>
        <h2>Uma base única para o site.</h2>
      </div>
      <p>
        As entidades mantêm os dados reais da carreira. As páginas apenas escolhem como esses dados são apresentados, em quais seções, em que ordem e com qual aparência. Assim o CMS fica flexível sem transformar o site em um editor de CSS livre.
      </p>
    </section>
  </div>
</template>
