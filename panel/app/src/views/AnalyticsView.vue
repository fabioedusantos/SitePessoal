<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '../lib/api'

type MetricValue = { value: number; previous: number; change_percent: number | null }
type ReportRow = { dimensions: Record<string, string>; metrics: Record<string, number> }
type TableReport = { rows: ReportRow[]; row_count: number }
interface AnalyticsPayload {
  configured: boolean
  message?: string
  detail?: string
  period?: { label: string }
  summary?: Record<string, MetricValue>
  daily?: TableReport
  pages?: TableReport
  channels?: TableReport
  countries?: TableReport
  devices?: TableReport
  generated_at?: string
}

const data = ref<AnalyticsPayload | null>(null)
const loading = ref(true)
const error = ref('')

const labels: Record<string, string> = {
  activeUsers: 'Usuários ativos',
  newUsers: 'Novos usuários',
  sessions: 'Sessões',
  screenPageViews: 'Visualizações',
  engagedSessions: 'Sessões engajadas',
  engagementRate: 'Taxa de engajamento',
  averageSessionDuration: 'Duração média'
}

const summaryOrder = ['activeUsers', 'newUsers', 'sessions', 'screenPageViews', 'engagedSessions', 'engagementRate', 'averageSessionDuration']
const summaryCards = computed(() => summaryOrder
  .filter(key => data.value?.summary?.[key])
  .map(key => ({ key, label: labels[key], ...data.value!.summary![key] })))

const dailyMax = computed(() => Math.max(1, ...(data.value?.daily?.rows || []).map(row => Number(row.metrics.screenPageViews || 0))))

function formatMetric(key: string, value: number) {
  if (key === 'engagementRate') return `${(value * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`
  if (key === 'averageSessionDuration') {
    const total = Math.max(0, Math.round(value))
    const minutes = Math.floor(total / 60)
    const seconds = total % 60
    return minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`
  }
  return Math.round(value).toLocaleString('pt-BR')
}

function changeText(value: number | null) {
  if (value === null) return 'sem base anterior'
  if (value === 0) return 'estável vs. período anterior'
  return `${value > 0 ? '+' : ''}${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}% vs. período anterior`
}

function formatDate(value: string) {
  if (!/^\d{8}$/.test(value)) return value
  return `${value.slice(6, 8)}/${value.slice(4, 6)}`
}

function generatedAt(value?: string) {
  if (!value) return ''
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '' : date.toLocaleString('pt-BR')
}

onMounted(async () => {
  try {
    data.value = await api<AnalyticsPayload>('/admin/analytics')
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao carregar o Google Analytics.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="analytics-page">
    <header class="page-title dashboard-title">
      <div>
        <p class="overline">Google Analytics 4</p>
        <h1>Métricas do site</h1>
        <p>Visão consolidada de aquisição, audiência e conteúdo diretamente no CMS.</p>
      </div>
      <span v-if="data?.generated_at" class="analytics-updated">Atualizado em {{ generatedAt(data.generated_at) }}</span>
    </header>

    <div v-if="error" class="alert" role="alert">{{ error }}</div>
    <div v-else-if="loading" class="analytics-loading">Carregando métricas do Google Analytics…</div>

    <section v-else-if="data && !data.configured" class="admin-panel analytics-setup">
      <div>
        <p class="overline">Configuração necessária</p>
        <h2>Conecte a propriedade GA4.</h2>
      </div>
      <p>{{ data.message }} Configure <code>GA_PROPERTY_ID</code>, <code>GA_SERVICE_ACCOUNT_EMAIL</code> e <code>GA_SERVICE_ACCOUNT_PRIVATE_KEY_B64</code> no backend de produção.</p>
    </section>

    <template v-else-if="data?.configured">
      <section class="analytics-summary" :aria-label="data.period?.label || 'Resumo do período'">
        <article v-for="item in summaryCards" :key="item.key" class="metric-card analytics-metric">
          <span>{{ item.label }}</span>
          <strong>{{ formatMetric(item.key, item.value) }}</strong>
          <small :class="{ positive: (item.change_percent || 0) > 0, negative: (item.change_percent || 0) < 0 }">
            {{ changeText(item.change_percent) }}
          </small>
        </article>
      </section>

      <section class="analytics-panel">
        <div class="analytics-panel-heading">
          <div><p class="overline">Evolução</p><h2>Visualizações por dia</h2></div>
          <p>{{ data.period?.label }}</p>
        </div>
        <div class="analytics-bars" role="img" aria-label="Visualizações por dia">
          <div v-for="row in data.daily?.rows || []" :key="row.dimensions.date" class="analytics-bar-item">
            <div class="analytics-bar-track">
              <span :style="{ height: `${Math.max(3, (Number(row.metrics.screenPageViews || 0) / dailyMax) * 100)}%` }" />
            </div>
            <strong>{{ Math.round(row.metrics.screenPageViews || 0) }}</strong>
            <small>{{ formatDate(row.dimensions.date) }}</small>
          </div>
        </div>
      </section>

      <div class="analytics-grid">
        <section class="analytics-panel analytics-table-panel">
          <div class="analytics-panel-heading"><div><p class="overline">Conteúdo</p><h2>Páginas mais vistas</h2></div></div>
          <div class="table-scroll">
            <table class="analytics-table"><thead><tr><th>Página</th><th>Visualizações</th><th>Usuários</th></tr></thead><tbody>
              <tr v-for="row in data.pages?.rows || []" :key="`${row.dimensions.pagePath}-${row.dimensions.pageTitle}`">
                <td><strong>{{ row.dimensions.pageTitle || row.dimensions.pagePath }}</strong><small>{{ row.dimensions.pagePath }}</small></td>
                <td>{{ Math.round(row.metrics.screenPageViews || 0).toLocaleString('pt-BR') }}</td>
                <td>{{ Math.round(row.metrics.activeUsers || 0).toLocaleString('pt-BR') }}</td>
              </tr>
            </tbody></table>
          </div>
        </section>

        <section class="analytics-panel analytics-table-panel">
          <div class="analytics-panel-heading"><div><p class="overline">Aquisição</p><h2>Canais de acesso</h2></div></div>
          <div class="table-scroll">
            <table class="analytics-table"><thead><tr><th>Canal</th><th>Sessões</th><th>Engajadas</th></tr></thead><tbody>
              <tr v-for="row in data.channels?.rows || []" :key="row.dimensions.sessionDefaultChannelGroup">
                <td><strong>{{ row.dimensions.sessionDefaultChannelGroup || 'Não identificado' }}</strong></td>
                <td>{{ Math.round(row.metrics.sessions || 0).toLocaleString('pt-BR') }}</td>
                <td>{{ Math.round(row.metrics.engagedSessions || 0).toLocaleString('pt-BR') }}</td>
              </tr>
            </tbody></table>
          </div>
        </section>

        <section class="analytics-panel analytics-table-panel">
          <div class="analytics-panel-heading"><div><p class="overline">Público</p><h2>Países</h2></div></div>
          <div class="table-scroll">
            <table class="analytics-table"><thead><tr><th>País</th><th>Usuários</th><th>Sessões</th></tr></thead><tbody>
              <tr v-for="row in data.countries?.rows || []" :key="row.dimensions.country">
                <td><strong>{{ row.dimensions.country || 'Não identificado' }}</strong></td>
                <td>{{ Math.round(row.metrics.activeUsers || 0).toLocaleString('pt-BR') }}</td>
                <td>{{ Math.round(row.metrics.sessions || 0).toLocaleString('pt-BR') }}</td>
              </tr>
            </tbody></table>
          </div>
        </section>

        <section class="analytics-panel analytics-table-panel">
          <div class="analytics-panel-heading"><div><p class="overline">Tecnologia</p><h2>Dispositivos</h2></div></div>
          <div class="table-scroll">
            <table class="analytics-table"><thead><tr><th>Dispositivo</th><th>Usuários</th><th>Sessões</th></tr></thead><tbody>
              <tr v-for="row in data.devices?.rows || []" :key="row.dimensions.deviceCategory">
                <td><strong>{{ row.dimensions.deviceCategory || 'Não identificado' }}</strong></td>
                <td>{{ Math.round(row.metrics.activeUsers || 0).toLocaleString('pt-BR') }}</td>
                <td>{{ Math.round(row.metrics.sessions || 0).toLocaleString('pt-BR') }}</td>
              </tr>
            </tbody></table>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
