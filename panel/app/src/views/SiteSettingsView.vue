<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { api } from '../lib/api'
import ImageUploadField from '../components/ImageUploadField.vue'

interface CmsPage {
  id: number
  title: string
  slug: string
  page_kind: string
  status: string
  sort_order: number
}

interface NavigationItem {
  id?: number
  location: 'header' | 'footer'
  label: string
  page_id: number | ''
  url: string
  is_external: boolean
  sort_order: number
  is_visible: boolean
}

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const form = reactive({
  id: 1,
  header_logo_url: '', header_logo_alt: '', footer_logo_url: '', footer_logo_alt: '',
  footer_lead: '', footer_title: '', footer_body: ''
})
const pages = ref<CmsPage[]>([])
const headerNavigation = ref<NavigationItem[]>([])
const footerNavigation = ref<NavigationItem[]>([])
const originalNavigationIds = ref(new Set<number>())

const selectablePages = computed(() => pages.value
  .filter(page => page.page_kind !== 'project')
  .sort((a, b) => Number(a.sort_order) - Number(b.sort_order) || Number(a.id) - Number(b.id)))

function normalizeBoolean(value: unknown) {
  return value === true || value === 1 || value === '1'
}

function normalizeNavigation(rows: Record<string, any>[], location: NavigationItem['location']): NavigationItem[] {
  return rows
    .filter(row => row.location === location)
    .sort((a, b) => Number(a.sort_order) - Number(b.sort_order) || Number(a.id) - Number(b.id))
    .map((row, index) => ({
      id: Number(row.id),
      location,
      label: String(row.label || ''),
      page_id: row.page_id ? Number(row.page_id) : ('' as const),
      url: String(row.url || ''),
      is_external: normalizeBoolean(row.is_external),
      sort_order: (index + 1) * 10,
      is_visible: normalizeBoolean(row.is_visible)
    }))
}

function addNavigation(location: 'header' | 'footer') {
  const target = location === 'header' ? headerNavigation : footerNavigation
  target.value.push({
    location,
    label: '',
    page_id: '',
    url: '',
    is_external: false,
    sort_order: (target.value.length + 1) * 10,
    is_visible: true
  })
}

function resequence(items: NavigationItem[]) {
  items.forEach((item, index) => { item.sort_order = (index + 1) * 10 })
}

function moveNavigation(location: 'header' | 'footer', index: number, direction: -1 | 1) {
  const target = location === 'header' ? headerNavigation : footerNavigation
  const next = index + direction
  if (next < 0 || next >= target.value.length) return
  const [item] = target.value.splice(index, 1)
  target.value.splice(next, 0, item)
  resequence(target.value)
}

function removeNavigation(location: 'header' | 'footer', index: number) {
  const target = location === 'header' ? headerNavigation : footerNavigation
  target.value.splice(index, 1)
  resequence(target.value)
}

function isValidExternalUrl(value: string) {
  try {
    const url = new URL(value)
    return url.protocol === 'http:' || url.protocol === 'https:'
  } catch {
    return false
  }
}

function validateNavigation() {
  const allItems = [...headerNavigation.value, ...footerNavigation.value]
  for (const item of allItems) {
    const locationLabel = item.location === 'header' ? 'cabeçalho' : 'rodapé'
    if (!item.label.trim()) return `Informe o nome de todos os links do ${locationLabel}.`
    if (item.is_external && !item.url.trim()) return `Informe a URL externa de “${item.label}”.`
    if (item.is_external && !isValidExternalUrl(item.url.trim())) return `Informe uma URL válida para “${item.label}”.`
    if (!item.is_external && !item.page_id) return `Escolha a página interna de “${item.label}”.`
  }
  return ''
}

function navigationPayload(item: NavigationItem, index: number) {
  return {
    location: item.location,
    label: item.label.trim(),
    page_id: item.is_external ? '' : item.page_id,
    url: item.is_external ? item.url.trim() : '',
    is_external: item.is_external ? 1 : 0,
    sort_order: (index + 1) * 10,
    is_visible: item.is_visible ? 1 : 0
  }
}

async function syncNavigation() {
  const currentIds = new Set<number>()
  const allItems = [...headerNavigation.value, ...footerNavigation.value]

  for (const item of allItems) {
    if (!item.label.trim()) throw new Error('Informe o rótulo de todos os itens de navegação.')
    if (item.is_external && !item.url.trim()) throw new Error(`Informe a URL externa de “${item.label}”.`)
    if (!item.is_external && !item.page_id) throw new Error(`Escolha a página interna de “${item.label}”.`)
  }

  for (const location of ['header', 'footer'] as const) {
    const items = location === 'header' ? headerNavigation.value : footerNavigation.value
    for (let index = 0; index < items.length; index += 1) {
      const item = items[index]
      const payload = navigationPayload(item, index)
      if (item.id) {
        await api(`/admin/site-navigation/${item.id}`, { method: 'PUT', body: JSON.stringify(payload) })
        currentIds.add(item.id)
      } else {
        const created = await api<{ id: number }>('/admin/site-navigation', { method: 'POST', body: JSON.stringify(payload) })
        item.id = created.id
        currentIds.add(created.id)
      }
    }
  }

  for (const id of originalNavigationIds.value) {
    if (!currentIds.has(id)) await api(`/admin/site-navigation/${id}`, { method: 'DELETE' })
  }
  originalNavigationIds.value = currentIds
}

onMounted(async () => {
  try {
    const [settingsResult, navigationResult, pageResult] = await Promise.all([
      api<{ data: Record<string, any>[] }>('/admin/site-settings'),
      api<{ data: Record<string, any>[] }>('/admin/site-navigation'),
      api<{ data: CmsPage[] }>('/admin/pages')
    ])

    const row = settingsResult.data[0]
    if (row) Object.assign(form, row, { id: Number(row.id) })
    pages.value = pageResult.data || []
    headerNavigation.value = normalizeNavigation(navigationResult.data || [], 'header')
    footerNavigation.value = normalizeNavigation(navigationResult.data || [], 'footer')
    originalNavigationIds.value = new Set((navigationResult.data || []).map(row => Number(row.id)))
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao carregar configurações.'
  } finally {
    loading.value = false
  }
})

async function save() {
  error.value = ''
  success.value = ''
  const validationError = validateNavigation()
  if (validationError) {
    error.value = validationError
    window.scrollTo({ top: 0, behavior: 'smooth' })
    return
  }

  saving.value = true
  try {
    await api(`/admin/site-settings/${form.id}`, { method: 'PUT', body: JSON.stringify({
      header_logo_url: form.header_logo_url || null,
      header_logo_alt: form.header_logo_alt || null,
      footer_logo_url: form.footer_logo_url || null,
      footer_logo_alt: form.footer_logo_alt || null,
      footer_lead: form.footer_lead || null,
      footer_title: form.footer_title || null,
      footer_body: form.footer_body || null
    }) })
    await syncNavigation()
    success.value = 'Cabeçalho, rodapé e navegação atualizados.'
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao salvar configurações.'
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="resource-edit-page">
    <header class="page-title">
      <div>
        <p class="overline">Site</p>
        <h1>Cabeçalho, rodapé e navegação</h1>
        <p>Elementos globais do site ficam juntos aqui. Não é necessário editar tabelas técnicas separadamente.</p>
      </div>
      <button class="primary-button" type="button" :disabled="saving || loading" @click="save">{{ saving ? 'Salvando…' : 'Salvar' }}</button>
    </header>

    <div v-if="error" class="alert" role="alert">{{ error }}</div>
    <div v-if="success" class="success-message">{{ success }}</div>
    <div v-if="loading" class="builder-loading">Carregando…</div>

    <template v-else>
      <section class="admin-panel site-settings-panel">
        <div class="form-section-heading"><div><p class="overline">Cabeçalho</p><h2>Marca principal</h2><p>Logo e links exibidos na navegação principal.</p></div></div>
        <div class="form-grid site-settings-brand-grid">
          <div class="form-field"><span class="field-label">Logo do cabeçalho</span><ImageUploadField v-model="form.header_logo_url" scope="site-header-logo" preset="brand-logo" /></div>
          <label class="form-field"><span class="field-label">Texto alternativo</span><input v-model="form.header_logo_alt" type="text" placeholder="Ex.: FBSantos, site profissional de Fábio Santos"></label>
        </div>

        <div class="inline-heading site-settings-navigation-heading">
          <div><p class="section-kicker">Navegação</p><h3>Links do cabeçalho</h3><p>Escolha uma página do CMS ou informe um endereço externo.</p></div>
          <button class="secondary-button" type="button" @click="addNavigation('header')">+ Adicionar item</button>
        </div>
        <div v-if="headerNavigation.length" class="repeatable-list">
          <article v-for="(item, index) in headerNavigation" :key="item.id || `header-${index}`" class="repeatable-card">
            <header class="repeatable-card-header">
              <strong>Item {{ index + 1 }}</strong>
              <div class="row-actions"><button type="button" :disabled="index === 0" @click="moveNavigation('header', index, -1)">↑</button><button type="button" :disabled="index === headerNavigation.length - 1" @click="moveNavigation('header', index, 1)">↓</button><button class="danger-text" type="button" @click="removeNavigation('header', index)">Remover</button></div>
            </header>
            <div class="form-grid">
              <label class="form-field"><span class="field-label">Rótulo *</span><input v-model="item.label" type="text" required></label>
              <div class="form-field is-toggle-field"><div class="toggle-copy"><span class="field-label">Destino</span><small class="field-help">Ative para apontar para outro site.</small></div><label class="switch"><input v-model="item.is_external" type="checkbox"><span class="switch-track"><span /></span><span class="switch-state">{{ item.is_external ? 'Externo' : 'Página interna' }}</span></label></div>
              <label v-if="!item.is_external" class="form-field wide"><span class="field-label">Página *</span><select v-model="item.page_id" required><option value="">Selecione</option><option v-for="page in selectablePages" :key="page.id" :value="page.id">{{ page.title }} · /{{ page.slug === 'home' ? '' : page.slug }}</option></select></label>
              <label v-else class="form-field wide"><span class="field-label">URL externa *</span><input v-model="item.url" type="url" placeholder="https://…" required></label>
              <div class="form-field is-toggle-field"><div class="toggle-copy"><span class="field-label">Visível</span></div><label class="switch"><input v-model="item.is_visible" type="checkbox"><span class="switch-track"><span /></span><span class="switch-state">{{ item.is_visible ? 'Sim' : 'Não' }}</span></label></div>
            </div>
          </article>
        </div>
        <div v-else class="inline-empty-state">Nenhum item no cabeçalho.</div>
      </section>

      <section class="admin-panel site-settings-panel">
        <div class="form-section-heading"><div><p class="overline">Rodapé</p><h2>Identidade e mensagem</h2><p>Conteúdo institucional e links do rodapé.</p></div></div>
        <div class="form-grid site-settings-brand-grid">
          <div class="form-field"><span class="field-label">Logo do rodapé</span><ImageUploadField v-model="form.footer_logo_url" scope="site-footer-logo" preset="brand-logo" /></div>
          <label class="form-field"><span class="field-label">Texto alternativo</span><input v-model="form.footer_logo_alt" type="text" placeholder="Ex.: FBSantos, Fábio Santos Desenvolvimentos"></label>
          <label class="form-field wide"><span class="field-label">Texto principal</span><textarea v-model="form.footer_lead" rows="4" /></label>
          <label class="form-field"><span class="field-label">Título/meta</span><input v-model="form.footer_title" type="text"></label>
          <label class="form-field"><span class="field-label">Texto final</span><input v-model="form.footer_body" type="text"></label>
        </div>

        <div class="inline-heading site-settings-navigation-heading">
          <div><p class="section-kicker">Navegação</p><h3>Links do rodapé</h3><p>Mantenha aqui os atalhos e referências globais exibidos no fim das páginas.</p></div>
          <button class="secondary-button" type="button" @click="addNavigation('footer')">+ Adicionar item</button>
        </div>
        <div v-if="footerNavigation.length" class="repeatable-list">
          <article v-for="(item, index) in footerNavigation" :key="item.id || `footer-${index}`" class="repeatable-card">
            <header class="repeatable-card-header">
              <strong>Item {{ index + 1 }}</strong>
              <div class="row-actions"><button type="button" :disabled="index === 0" @click="moveNavigation('footer', index, -1)">↑</button><button type="button" :disabled="index === footerNavigation.length - 1" @click="moveNavigation('footer', index, 1)">↓</button><button class="danger-text" type="button" @click="removeNavigation('footer', index)">Remover</button></div>
            </header>
            <div class="form-grid">
              <label class="form-field"><span class="field-label">Rótulo *</span><input v-model="item.label" type="text" required></label>
              <div class="form-field is-toggle-field"><div class="toggle-copy"><span class="field-label">Destino</span><small class="field-help">Ative para apontar para outro site.</small></div><label class="switch"><input v-model="item.is_external" type="checkbox"><span class="switch-track"><span /></span><span class="switch-state">{{ item.is_external ? 'Externo' : 'Página interna' }}</span></label></div>
              <label v-if="!item.is_external" class="form-field wide"><span class="field-label">Página *</span><select v-model="item.page_id" required><option value="">Selecione</option><option v-for="page in selectablePages" :key="page.id" :value="page.id">{{ page.title }} · /{{ page.slug === 'home' ? '' : page.slug }}</option></select></label>
              <label v-else class="form-field wide"><span class="field-label">URL externa *</span><input v-model="item.url" type="url" placeholder="https://…" required></label>
              <div class="form-field is-toggle-field"><div class="toggle-copy"><span class="field-label">Visível</span></div><label class="switch"><input v-model="item.is_visible" type="checkbox"><span class="switch-track"><span /></span><span class="switch-state">{{ item.is_visible ? 'Sim' : 'Não' }}</span></label></div>
            </div>
          </article>
        </div>
        <div v-else class="inline-empty-state">Nenhum item no rodapé.</div>
      </section>
    </template>
  </div>
</template>
