<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { resources, type FieldConfig, type SortRule } from '../config/resources'
import { api } from '../lib/api'

const route = useRoute()
const router = useRouter()
const rows = ref<Record<string, any>[]>([])
const loading = ref(false)
const error = ref('')
const query = ref('')
const lookups = reactive<Record<string, Record<string, string>>>({})
const sortRules = ref<SortRule[]>([])
const collator = new Intl.Collator('pt-BR', { numeric: true, sensitivity: 'base' })

const resource = computed(() => String(route.params.resource || ''))
const config = computed(() => resources[resource.value])

const displayColumns = computed(() => {
  if (!config.value) return []
  const columns = [...config.value.columns]
  const hasOrder = config.value.fields.some(field => field.key === 'sort_order')
  if (hasOrder && !columns.some(column => column.key === 'sort_order')) {
    columns.push({ key: 'sort_order', label: 'Ordem' })
  }
  return columns
})

const filteredRows = computed(() => {
  const term = query.value.trim().toLocaleLowerCase('pt-BR')
  if (!term) return rows.value

  return rows.value.filter(row => displayColumns.value.some(column => {
    const rendered = renderValue(row[column.key], column.key)
    return String(rendered).toLocaleLowerCase('pt-BR').includes(term)
  }))
})

const sortedRows = computed(() => {
  if (!sortRules.value.length) return filteredRows.value

  return filteredRows.value
    .map((row, index) => ({ row, index }))
    .sort((a, b) => {
      for (const rule of sortRules.value) {
        const result = compareValues(a.row[rule.key], b.row[rule.key], rule.key)
        if (result !== 0) return rule.direction === 'asc' ? result : -result
      }
      return a.index - b.index
    })
    .map(item => item.row)
})

function initialSort(): SortRule[] {
  if (!config.value) return []
  if (config.value.defaultSort?.length) return config.value.defaultSort.map(rule => ({ ...rule }))
  if (config.value.fields.some(field => field.key === 'sort_order')) {
    return [{ key: 'sort_order', direction: 'asc' }]
  }
  return []
}

async function loadLookup(field: FieldConfig) {
  if (!field.optionsResource) return
  const result = await api<{ data: Record<string, any>[] }>(`/admin/${field.optionsResource}`)
  lookups[field.key] = Object.fromEntries(result.data.map(item => [String(item.id), String(item[field.optionLabel || 'name'] || item.title || item.name || item.role_title || item.section_key || `#${item.id}`)]))
}

async function load() {
  if (!config.value) {
    router.replace('/')
    return
  }

  loading.value = true
  error.value = ''
  query.value = ''
  sortRules.value = initialSort()
  Object.keys(lookups).forEach(key => delete lookups[key])

  try {
    const lookupFields = config.value.fields.filter(field =>
      field.optionsResource && displayColumns.value.some(column => column.key === field.key)
    )
    const result = await api<{ data: Record<string, any>[] }>(`/admin/${resource.value}`)
    await Promise.all(lookupFields.map(loadLookup))
    rows.value = result.data
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao carregar dados.'
  } finally {
    loading.value = false
  }
}

async function remove(row: Record<string, any>) {
  if (!config.value) return
  const title = row[config.value.titleField] || `#${row.id}`
  if (!confirm(`Excluir “${title}”?\n\nEsta ação não pode ser desfeita.`)) return

  try {
    await api(`/admin/${resource.value}/${row.id}`, { method: 'DELETE' })
    await load()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao excluir.'
  }
}

function fieldFor(key: string) {
  return config.value?.fields.find(field => field.key === key)
}

function renderValue(value: any, key: string) {
  const field = fieldFor(key)
  if (field?.options) {
    return field.options.find(option => String(option.value) === String(value))?.label ?? value ?? '—'
  }
  if (lookups[key]) return lookups[key][String(value)] || (value ? `#${value}` : '—')
  if (field?.type === 'date' && value) {
    const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)
    if (!Number.isNaN(date.getTime())) return new Intl.DateTimeFormat('pt-BR').format(date)
  }
  if (value === 1 || value === true || value === '1') return 'Sim'
  if (value === 0 || value === false || value === '0') return 'Não'
  return value ?? '—'
}

function compareValues(a: any, b: any, key: string) {
  const aEmpty = a === null || a === undefined || a === ''
  const bEmpty = b === null || b === undefined || b === ''
  if (aEmpty && bEmpty) return 0
  if (aEmpty) return 1
  if (bEmpty) return -1

  const field = fieldFor(key)
  if (field?.type === 'number' || key === 'sort_order') {
    return Number(a) - Number(b)
  }
  if (field?.type === 'date') {
    return new Date(String(a)).getTime() - new Date(String(b)).getTime()
  }

  return collator.compare(String(renderValue(a, key)), String(renderValue(b, key)))
}

function toggleSort(key: string) {
  const current = sortRules.value.find(rule => rule.key === key)
  sortRules.value = [{ key, direction: current?.direction === 'asc' ? 'desc' : 'asc' }]
}

function sortDirection(key: string) {
  return sortRules.value.find(rule => rule.key === key)?.direction || ''
}

function sortPriority(key: string) {
  const index = sortRules.value.findIndex(rule => rule.key === key)
  return index >= 0 ? index + 1 : 0
}

function sortLabel(key: string, label: string) {
  const direction = sortDirection(key)
  if (!direction) return `Ordenar por ${label}`
  return `${label}: ordenação ${direction === 'asc' ? 'crescente' : 'decrescente'}. Clique para inverter.`
}

function isBooleanColumn(key: string) {
  return fieldFor(key)?.type === 'checkbox'
}

function statusClass(value: any, key: string) {
  if (isBooleanColumn(key)) return Number(value) === 1 || value === true || value === '1' ? 'positive' : 'neutral'
  if (key === 'status') return String(value) === 'published' ? 'positive' : 'warning'
  return ''
}

watch(resource, load, { immediate: true })

function newResourceLabel() {
  const adjective = config.value?.gender === 'feminine' ? 'Nova' : 'Novo'
  return `${adjective} ${config.value?.singular.toLowerCase() ?? 'registro'}`
}
</script>

<template>
  <div v-if="config" class="resource-list-page">
    <header class="page-title">
      <div>
        <p class="overline">Conteúdo</p>
        <h1>{{ config.label }}</h1>
        <p>Cadastre, revise e controle o que aparece no site. Clique no título de qualquer coluna para ordenar a tabela.</p>
      </div>
      <RouterLink class="primary-button link-button" :to="`/${resource}/novo`">+ {{ newResourceLabel() }}</RouterLink>
    </header>

    <div v-if="error" class="alert" role="alert">{{ error }}</div>

    <section class="table-card">
      <div class="table-toolbar">
        <div class="table-count">
          <strong>{{ rows.length }}</strong>
          <span>{{ rows.length === 1 ? 'registro' : 'registros' }}</span>
        </div>
        <label class="search-field">
          <span class="sr-only">Buscar em {{ config.label }}</span>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.2-4.2m2.2-5.3A7.5 7.5 0 1 1 4 11.5a7.5 7.5 0 0 1 15 0Z"/></svg>
          <input v-model="query" type="search" :placeholder="`Buscar em ${config.label.toLowerCase()}…`">
          <button v-if="query" type="button" aria-label="Limpar busca" @click="query = ''">×</button>
        </label>
      </div>

      <div v-if="loading" class="table-loading" aria-live="polite">
        <div v-for="index in 5" :key="index" class="table-loading-row" />
      </div>
      <div v-else-if="rows.length === 0" class="empty-state">
        <strong>Nenhum registro cadastrado.</strong>
        <span>Crie o primeiro item para começar a alimentar esta área do site.</span>
        <RouterLink class="secondary-button link-button" :to="`/${resource}/novo`">Criar primeiro registro</RouterLink>
      </div>
      <div v-else-if="sortedRows.length === 0" class="empty-state">
        <strong>Nada encontrado.</strong>
        <span>Tente outro termo ou limpe a busca.</span>
        <button class="secondary-button" type="button" @click="query = ''">Limpar busca</button>
      </div>
      <div v-else class="table-scroll">
        <table>
          <thead>
            <tr>
              <th v-for="column in displayColumns" :key="column.key" :aria-sort="sortDirection(column.key) === 'asc' ? 'ascending' : sortDirection(column.key) === 'desc' ? 'descending' : 'none'">
                <button
                  class="table-sort-button"
                  type="button"
                  :aria-label="sortLabel(column.key, column.label)"
                  @click="toggleSort(column.key)"
                >
                  <span>{{ column.label }}</span>
                  <span class="sort-indicator" :class="{ active: sortDirection(column.key) }" aria-hidden="true">
                    {{ sortDirection(column.key) === 'asc' ? '↑' : sortDirection(column.key) === 'desc' ? '↓' : '↕' }}
                  </span>
                  <small v-if="sortPriority(column.key) > 1" class="sort-priority">{{ sortPriority(column.key) }}</small>
                </button>
              </th>
              <th class="actions-column">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in sortedRows" :key="row.id">
              <td v-for="column in displayColumns" :key="column.key">
                <span
                  v-if="isBooleanColumn(column.key) || column.key === 'status'"
                  class="table-badge"
                  :class="statusClass(row[column.key], column.key)"
                >{{ renderValue(row[column.key], column.key) }}</span>
                <span v-else>{{ renderValue(row[column.key], column.key) }}</span>
              </td>
              <td class="actions">
                <RouterLink class="table-action edit" :to="`/${resource}/${row.id}`">Editar</RouterLink>
                <button class="table-action delete" type="button" @click="remove(row)">Excluir</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
