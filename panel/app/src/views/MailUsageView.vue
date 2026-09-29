<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '../lib/api'

type UsageRow = {
  date: string
  reserved: number
  sent: number
  failed: number
  skipped: number
  pending: number
  owner_sent: number
  confirmation_sent: number
}
type ContactAttention = { id: number; name: string; email: string; subject: string; message: string; status: 'pending' | 'failed'; created_at: string }
type MailUsage = {
  month: string
  current_month: string
  today: string
  timezone: string
  limit: number
  today_reserved: number
  rolling_reserved: number
  available: number
  pending_contacts: number
  attention_count: number
  attention: ContactAttention[]
  totals: Omit<UsageRow, 'date'>
  daily: UsageRow[]
  note: string
}

const usage = ref<MailUsage | null>(null)
const month = ref('')
const loading = ref(true)
const error = ref('')
let requestSequence = 0

const limit = computed(() => usage.value?.limit || 450)
const usePercent = computed(() => Math.min(100, Math.round((Math.max(usage.value?.today_reserved || 0, usage.value?.rolling_reserved || 0) / limit.value) * 100)))
const daily = computed<UsageRow[]>(() => {
  const current = usage.value
  if (!current) return []
  const [year, mon] = current.month.split('-').map(Number)
  const last = new Date(year, mon, 0).getDate()
  const end = current.month === current.current_month ? Number(current.today.slice(8)) : last
  const indexed = new Map(current.daily.map(row => [row.date, row]))
  return Array.from({ length: end }, (_, dayIndex) => {
    const date = `${current.month}-${String(dayIndex + 1).padStart(2, '0')}`
    return indexed.get(date) || { date, reserved: 0, sent: 0, failed: 0, skipped: 0, pending: 0, owner_sent: 0, confirmation_sent: 0 }
  })
})
const maxDay = computed(() => Math.max(1, ...daily.value.map(row => row.reserved)))
const monthTitle = computed(() => month.value
  ? new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${month.value}-01T12:00:00Z`))
  : '')
const isCurrentMonth = computed(() => month.value === usage.value?.current_month)

async function loadMonth(selected = '') {
  const sequence = ++requestSequence
  loading.value = true
  error.value = ''
  try {
    const result = await api<MailUsage>(`/admin/mail-usage${selected ? `?month=${encodeURIComponent(selected)}` : ''}`)
    if (sequence !== requestSequence) return
    usage.value = result
    month.value = result.month
  } catch (e) {
    if (sequence !== requestSequence) return
    error.value = e instanceof Error ? e.message : 'Não foi possível carregar o relatório de e-mails.'
  } finally {
    if (sequence === requestSequence) loading.value = false
  }
}

function shiftMonth(direction: -1 | 1) {
  if (!month.value) return
  const [year, mon] = month.value.split('-').map(Number)
  const shifted = new Date(Date.UTC(year, mon - 1 + direction, 1)).toISOString().slice(0, 7)
  if (shifted > (usage.value?.current_month || '') || shifted < '2020-01') return
  void loadMonth(shifted)
}

function onMonthChange(event: Event) {
  const value = (event.target as HTMLInputElement).value
  if (/^\d{4}-(0[1-9]|1[0-2])$/.test(value)) void loadMonth(value)
}

function number(value: number) { return value.toLocaleString('pt-BR') }
function dateLabel(date: string) { return date.slice(8) + '/' + date.slice(5, 7) }

onMounted(() => { void loadMonth() })
</script>

<template>
  <div class="resource-edit-page mail-report-page">
    <header class="page-title">
      <div>
        <p class="overline">Comunicação</p>
        <h1>Envio de e-mails</h1>
        <p>Acompanhe o consumo do SMTP, os contatos recebidos e a reserva de envios para respeitar o limite da sua conta.</p>
      </div>
      <button class="secondary-button" :disabled="loading" type="button" @click="loadMonth(month)">Atualizar</button>
    </header>

    <div v-if="error" class="alert" role="alert">{{ error }} <button class="secondary-button" type="button" @click="loadMonth(month)">Tentar novamente</button></div>
    <div v-if="loading && !usage" class="analytics-loading">Carregando relatório de envios…</div>

    <template v-if="usage">
      <section class="analytics-panel mail-quota-panel" aria-label="Limite de e-mails">
        <div class="mail-quota-heading">
          <div><p class="overline">Proteção de envio</p><h2>Limite de {{ number(limit) }} e-mails</h2><p>Reserva por dia e nas últimas 24 horas ({{ usage.timezone }}).</p></div>
          <div class="mail-remaining"><strong>{{ number(usage.available) }}</strong><span>envios disponíveis agora</span></div>
        </div>
        <div class="mail-progress-track" role="progressbar" :aria-valuenow="usePercent" aria-valuemin="0" aria-valuemax="100" aria-label="Consumo da quota de e-mails"><span :style="{ width: `${usePercent}%` }" /></div>
        <div class="mail-limit-details"><span>Hoje: <strong>{{ number(usage.today_reserved) }}</strong> / {{ number(limit) }}</span><span>Últimas 24 horas: <strong>{{ number(usage.rolling_reserved) }}</strong> / {{ number(limit) }}</span></div>
        <p class="mail-note">Cada formulário normalmente reserva 2 envios: notificação para você e confirmação ao visitante. A reserva continua consumida em caso de falha, para evitar ultrapassar o limite após uma resposta SMTP incerta.</p>
      </section>

      <div class="mail-month-heading">
        <div><p class="overline">Histórico</p><h2>Resumo de {{ monthTitle }}</h2></div>
        <div class="mail-month-navigation">
          <button class="secondary-button" type="button" :disabled="loading || month <= '2020-01'" aria-label="Mês anterior" @click="shiftMonth(-1)">←</button>
          <label class="mail-month-field"><span>Selecionar mês</span><input type="month" :value="month" min="2020-01" :max="usage.current_month" :disabled="loading" @change="onMonthChange"></label>
          <button class="secondary-button" type="button" :disabled="loading || isCurrentMonth" aria-label="Mês seguinte" @click="shiftMonth(1)">→</button>
        </div>
      </div>
      <section class="analytics-summary" aria-label="Totais do mês">
        <article class="metric-card analytics-metric"><span>Envios confirmados pelo SMTP</span><strong>{{ number(usage.totals.sent) }}</strong><small>{{ number(usage.totals.owner_sent) }} notificações + {{ number(usage.totals.confirmation_sent) }} confirmações</small></article>
        <article class="metric-card analytics-metric"><span>Quota reservada no mês</span><strong>{{ number(usage.totals.reserved) }}</strong><small>Inclui falhas e reservas não concluídas</small></article>
        <article class="metric-card analytics-metric"><span>Falhas de envio</span><strong>{{ number(usage.totals.failed) }}</strong><small>{{ number(usage.totals.skipped) }} não enviados + {{ number(usage.totals.pending) }} em andamento</small></article>
        <article class="metric-card analytics-metric"><span>Contatos com envio pendente ou falho</span><strong>{{ number(usage.attention_count) }}</strong><small>{{ number(usage.pending_contacts) }} aguardando notificação</small></article>
      </section>

      <section class="analytics-panel">
        <div class="analytics-panel-heading"><div><p class="overline">Consumo diário</p><h2>Envios por dia</h2></div><p>Quantidade reservada por dia</p></div>
        <div class="analytics-bars" role="img" :aria-label="`Gráfico de consumo de e-mails em ${monthTitle}`">
          <div v-for="day in daily" :key="day.date" class="analytics-bar-item">
            <div class="analytics-bar-track"><span :style="{ height: `${day.reserved ? Math.max(4, (day.reserved / maxDay) * 100) : 0}%` }" /></div>
            <strong>{{ number(day.reserved) }}</strong><small>{{ dateLabel(day.date) }}</small>
          </div>
        </div>
      </section>

      <section class="analytics-panel mail-daily-table">
        <div class="analytics-panel-heading"><div><p class="overline">Detalhamento</p><h2>Registro dia a dia</h2></div><p>Contagem por e-mail, não por contato</p></div>
        <div class="table-scroll">
          <table><thead><tr><th>Dia</th><th>Reservados</th><th>Enviados</th><th>Notificações</th><th>Confirmações</th><th>Falhas</th><th>Não enviados</th><th>Pendentes</th></tr></thead>
            <tbody><tr v-for="day in [...daily].reverse()" :key="day.date"><td><strong>{{ dateLabel(day.date) }}</strong></td><td>{{ number(day.reserved) }}</td><td>{{ number(day.sent) }}</td><td>{{ number(day.owner_sent) }}</td><td>{{ number(day.confirmation_sent) }}</td><td>{{ number(day.failed) }}</td><td>{{ number(day.skipped) }}</td><td>{{ number(day.pending) }}</td></tr></tbody>
          </table>
        </div>
      </section>
      <section v-if="usage.attention_count" class="analytics-panel mail-attention-panel">
        <div class="analytics-panel-heading"><div><p class="overline">Atenção necessária</p><h2>Contatos sem notificação confirmada</h2></div><p>{{ number(usage.attention_count) }} registros ({{ usage.attention.length }} mais recentes abaixo)</p></div>
        <p class="mail-note">As mensagens permanecem no banco mesmo quando o envio de e-mails é bloqueado ou falha. Verifique os contatos abaixo e responda manualmente, se necessário.</p>
        <div class="mail-attention-list">
          <details v-for="contact in usage.attention" :key="contact.id" class="mail-attention-item">
            <summary><strong>{{ contact.subject }}</strong><span>{{ contact.name }} · {{ contact.email }}</span><small>{{ contact.status === 'pending' ? 'Pendente' : 'Falha' }} · {{ contact.created_at }}</small></summary>
            <p class="mail-attention-message">{{ contact.message }}</p>
            <a :href="`mailto:${contact.email}`">Responder por e-mail ↗</a>
          </details>
        </div>
      </section>
      <p class="mail-note mail-bottom-note">{{ usage.note }} “Enviado” significa aceito pelo servidor SMTP, não confirmação de entrega na caixa de entrada. O relatório começa a contabilizar novos envios após a implantação desta versão.</p>
    </template>
  </div>
</template>
