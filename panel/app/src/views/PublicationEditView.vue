<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ImageUploadField from '../components/ImageUploadField.vue'
import RichTextEditor from '../components/RichTextEditor.vue'
import { api } from '../lib/api'

interface Option { id: number; label: string }
interface ChildRow { id?: number; sort_order?: number; [key: string]: any }
type ChildResource = 'publication-media' | 'publication-sections' | 'publication-projects'

const route = useRoute()
const router = useRouter()
const routeId = computed(() => route.params.id ? Number(route.params.id) : null)
const isNew = computed(() => !routeId.value)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const savedMessage = ref('')
const projects = ref<Option[]>([])

const form = reactive<Record<string, any>>({
  title: '', slug: '', kind: '', organization: '', event_name: '', location: '', published_date: '',
  summary: '', description: '', external_url: '', sort_order: 0, is_visible: true
})

const media = ref<ChildRow[]>([])
const sections = ref<ChildRow[]>([])
const relatedProjects = ref<ChildRow[]>([])
const originalIds = reactive<Record<ChildResource, Set<number>>>({
  'publication-media': new Set<number>(),
  'publication-sections': new Set<number>(),
  'publication-projects': new Set<number>()
})

function numeric(value: unknown, fallback = 0) {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : fallback
}

function normalizeBoolean(value: unknown) {
  return value === true || value === 1 || value === '1'
}

function normalizeRows(rows: ChildRow[]): ChildRow[] {
  return [...rows]
    .sort((a, b) => numeric(a.sort_order) - numeric(b.sort_order) || numeric(a.id) - numeric(b.id))
    .map((row, index) => ({ ...row, sort_order: (index + 1) * 10 }))
}

async function loadAll(resource: string) {
  return api<{ data: Record<string, any>[] }>(`/admin/${resource}`).then(result => result.data || [])
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const projectRows = await loadAll('projects')
    projects.value = projectRows
      .sort((a, b) => numeric(a.sort_order) - numeric(b.sort_order) || String(a.name || '').localeCompare(String(b.name || ''), 'pt-BR'))
      .map(item => ({ id: Number(item.id), label: String(item.name || `#${item.id}`) }))

    if (!routeId.value) return

    const [publicationResult, mediaRows, sectionRows, projectRelations] = await Promise.all([
      api<{ data: Record<string, any> }>(`/admin/publications/${routeId.value}`),
      loadAll('publication-media'),
      loadAll('publication-sections'),
      loadAll('publication-projects')
    ])

    const publication = publicationResult.data
    Object.keys(form).forEach(key => {
      form[key] = key === 'is_visible' ? normalizeBoolean(publication[key]) : (publication[key] ?? (key === 'sort_order' ? 0 : ''))
    })

    media.value = normalizeRows(mediaRows.filter(row => Number(row.publication_id) === routeId.value))
      .map(row => ({ ...row, is_visible: normalizeBoolean(row.is_visible) }))
    sections.value = normalizeRows(sectionRows.filter(row => Number(row.publication_id) === routeId.value))
      .map(row => ({
        ...row,
        is_visible: normalizeBoolean(row.is_visible),
        items_text: (() => {
          try {
            const parsed = typeof row.items === 'string' ? JSON.parse(row.items) : row.items
            return Array.isArray(parsed) ? parsed.join('\n') : ''
          } catch { return String(row.items || '') }
        })()
      }))
    relatedProjects.value = normalizeRows(projectRelations.filter(row => Number(row.publication_id) === routeId.value))
      .map(row => ({ ...row, is_visible: normalizeBoolean(row.is_visible), project_id: Number(row.project_id) }))

    for (const resource of Object.keys(originalIds) as ChildResource[]) originalIds[resource].clear()
    media.value.forEach(row => row.id && originalIds['publication-media'].add(Number(row.id)))
    sections.value.forEach(row => row.id && originalIds['publication-sections'].add(Number(row.id)))
    relatedProjects.value.forEach(row => row.id && originalIds['publication-projects'].add(Number(row.id)))
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao carregar a publicação.'
  } finally {
    loading.value = false
  }
}

function slugifyTitle() {
  if (form.slug || !form.title) return
  form.slug = String(form.title)
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
}

function addMedia() {
  media.value.push({ title: '', media_type: 'youtube', media_url: '', embed_url: '', is_visible: true, sort_order: (media.value.length + 1) * 10 })
}

function addSection() {
  sections.value.push({ eyebrow: '', title: '', body: '', items_text: '', is_visible: true, sort_order: (sections.value.length + 1) * 10 })
}

function addRelatedProject() {
  relatedProjects.value.push({ project_id: '', relation_label: '', is_visible: true, sort_order: (relatedProjects.value.length + 1) * 10 })
}

function resequence(collection: ChildRow[]) {
  collection.forEach((item, index) => { item.sort_order = (index + 1) * 10 })
}

function removeRow(collection: ChildRow[], index: number) {
  collection.splice(index, 1)
  resequence(collection)
}

function moveRow(collection: ChildRow[], index: number, direction: -1 | 1) {
  const target = index + direction
  if (target < 0 || target >= collection.length) return
  const [item] = collection.splice(index, 1)
  collection.splice(target, 0, item)
  resequence(collection)
}

function publicationPayload() {
  return {
    title: String(form.title || '').trim(),
    slug: String(form.slug || '').trim(),
    kind: String(form.kind || '').trim(),
    organization: form.organization || '',
    event_name: form.event_name || '',
    location: form.location || '',
    published_date: form.published_date || '',
    summary: form.summary || '',
    description: form.description || '',
    external_url: form.external_url || '',
    sort_order: numeric(form.sort_order),
    is_visible: form.is_visible ? 1 : 0
  }
}

async function syncCollection(resource: ChildResource, rows: ChildRow[], publicationId: number, payloadFor: (row: ChildRow, index: number) => Record<string, any>) {
  const currentIds = new Set<number>()
  for (let index = 0; index < rows.length; index += 1) {
    const row = rows[index]
    const payload = { ...payloadFor(row, index), publication_id: publicationId }
    if (row.id) {
      await api(`/admin/${resource}/${row.id}`, { method: 'PUT', body: JSON.stringify(payload) })
      currentIds.add(Number(row.id))
    } else {
      const created = await api<{ id: number }>(`/admin/${resource}`, { method: 'POST', body: JSON.stringify(payload) })
      row.id = created.id
      currentIds.add(created.id)
    }
  }
  for (const oldId of [...originalIds[resource]]) {
    if (!currentIds.has(oldId)) await api(`/admin/${resource}/${oldId}`, { method: 'DELETE' })
  }
  originalIds[resource].clear()
  currentIds.forEach(id => originalIds[resource].add(id))
}

function isValidUrl(value: unknown) {
  const text = String(value || '').trim()
  if (!text) return true
  try {
    const url = new URL(text)
    return url.protocol === 'http:' || url.protocol === 'https:'
  } catch {
    return false
  }
}

function validateBeforeSave() {
  if (!String(form.title || '').trim()) return 'Informe o título da publicação.'
  if (!String(form.slug || '').trim()) return 'Informe o slug da publicação.'
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(String(form.slug).trim())) return 'O slug deve usar apenas letras minúsculas, números e hífens.'
  if (!String(form.kind || '').trim()) return 'Informe o tipo da publicação.'
  if (!isValidUrl(form.external_url)) return 'Informe uma URL válida em Referência externa.'

  for (let index = 0; index < media.value.length; index += 1) {
    const item = media.value[index]
    if (!String(item.media_url || '').trim()) return `Informe a mídia / URL do item ${index + 1}.`
    if (item.embed_url && !isValidUrl(item.embed_url)) return `A URL de embed da mídia ${index + 1} não é válida.`
  }

  for (let index = 0; index < sections.value.length; index += 1) {
    if (!String(sections.value[index].title || '').trim()) return `Informe o título da seção adicional ${index + 1}.`
  }

  if (relatedProjects.value.some(row => !Number(row.project_id))) return 'Escolha um projeto em todas as relações adicionadas.'
  const relatedProjectIds = relatedProjects.value.map(row => Number(row.project_id))
  if (new Set(relatedProjectIds).size !== relatedProjectIds.length) return 'O mesmo projeto não pode ser relacionado mais de uma vez à publicação.'

  return ''
}

async function save() {
  savedMessage.value = ''
  error.value = ''
  const validationError = validateBeforeSave()
  if (validationError) {
    error.value = validationError
    window.scrollTo({ top: 0, behavior: 'smooth' })
    return
  }

  saving.value = true
  try {
    let publicationId = routeId.value
    if (publicationId) {
      await api(`/admin/publications/${publicationId}`, { method: 'PUT', body: JSON.stringify(publicationPayload()) })
    } else {
      const created = await api<{ id: number }>('/admin/publications', { method: 'POST', body: JSON.stringify(publicationPayload()) })
      publicationId = created.id
    }

    media.value = normalizeRows(media.value)
    sections.value = normalizeRows(sections.value)
    relatedProjects.value = normalizeRows(relatedProjects.value)

    await syncCollection('publication-media', media.value, publicationId, (row, index) => ({
      title: row.title || '', media_type: row.media_type || 'youtube', media_url: row.media_url || '', embed_url: row.embed_url || '',
      sort_order: (index + 1) * 10, is_visible: row.is_visible ? 1 : 0
    }))
    await syncCollection('publication-sections', sections.value, publicationId, (row, index) => ({
      eyebrow: row.eyebrow || '', title: row.title || '', body: row.body || '',
      items: String(row.items_text || '').split(/\r?\n/).map((item: string) => item.trim()).filter(Boolean),
      sort_order: (index + 1) * 10, is_visible: row.is_visible ? 1 : 0
    }))
    await syncCollection('publication-projects', relatedProjects.value, publicationId, (row, index) => ({
      project_id: Number(row.project_id), relation_label: row.relation_label || '', sort_order: (index + 1) * 10, is_visible: row.is_visible ? 1 : 0
    }))

    savedMessage.value = 'Publicação salva com mídias, seções e projetos relacionados.'
    if (isNew.value) await router.replace(`/publications/${publicationId}`)
    else window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao salvar a publicação.'
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="project-editor-page">
    <header class="page-title page-title-editor project-editor-title">
      <div>
        <RouterLink class="back-link" to="/publications">← Voltar para publicações</RouterLink>
        <p class="overline">Publicações e apresentações</p>
        <h1>{{ isNew ? 'Nova publicação' : `Editar ${form.title || 'publicação'}` }}</h1>
        <p>Conteúdo principal, mídias, seções e cases relacionados ficam reunidos nesta única tela.</p>
      </div>
      <span class="edit-status-badge">{{ isNew ? 'Novo registro' : `Registro #${routeId}` }}</span>
    </header>

    <div v-if="error" class="alert" role="alert">{{ error }}</div>
    <div v-if="savedMessage" class="success-message">{{ savedMessage }}</div>
    <div v-if="loading" class="editor-loading"><div class="editor-loading-line"/><div class="editor-loading-block"/><div class="editor-loading-block short"/></div>

    <form v-else class="resource-editor-form" novalidate @submit.prevent="save">
      <section class="editor-section">
        <header class="editor-section-heading"><div><p class="section-kicker">Informações principais</p><p>Identifique a palestra, artigo, apresentação ou publicação e informe quando e onde aconteceu.</p></div></header>
        <div class="form-grid">
          <div class="form-field wide"><label class="field-label">Título *</label><input v-model="form.title" required @blur="slugifyTitle"></div>
          <div class="form-field"><label class="field-label">Slug *</label><input v-model="form.slug" required placeholder="nome-da-publicacao"></div>
          <div class="form-field"><label class="field-label">Tipo *</label><input v-model="form.kind" required placeholder="Palestra, webinar, artigo…"></div>
          <div class="form-field"><label class="field-label">Organização</label><input v-model="form.organization"></div>
          <div class="form-field"><label class="field-label">Evento</label><input v-model="form.event_name"></div>
          <div class="form-field"><label class="field-label">Local</label><input v-model="form.location"></div>
          <div class="form-field"><label class="field-label">Data</label><input v-model="form.published_date" type="date"></div>
          <div class="form-field"><label class="field-label">Ordem</label><input v-model.number="form.sort_order" type="number"></div>
          <div class="form-field is-toggle-field"><div class="toggle-copy"><span class="field-label">Visível</span></div><label class="switch"><input v-model="form.is_visible" type="checkbox"><span class="switch-track"><span /></span><span class="switch-state">{{ form.is_visible ? 'Sim' : 'Não' }}</span></label></div>
        </div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading"><div><p class="section-kicker">Narrativa</p><p>Descreva o contexto, o conteúdo apresentado e por que ele é relevante na sua trajetória.</p></div></header>
        <div class="form-grid">
          <div class="form-field wide"><label class="field-label">Resumo</label><RichTextEditor v-model="form.summary" :min-height="120" /></div>
          <div class="form-field wide"><label class="field-label">Descrição completa</label><RichTextEditor v-model="form.description" :min-height="190" /></div>
          <div class="form-field wide"><label class="field-label">Referência externa</label><input v-model="form.external_url" type="url" placeholder="https://…"><small class="field-help">Use quando existir uma página externa complementar à publicação.</small></div>
        </div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading inline-heading"><div><p class="section-kicker">Mídias</p><h2>Vídeos e referências</h2><p>Cadastre YouTube, vídeos ou imagens diretamente na publicação.</p></div><button class="secondary-button" type="button" @click="addMedia">+ Adicionar mídia</button></header>
        <div v-if="media.length" class="repeatable-list media-repeatable-list">
          <article v-for="(item, index) in media" :key="item.id || `media-${index}`" class="repeatable-card">
            <header class="repeatable-card-header"><strong>Mídia {{ index + 1 }}</strong><div class="row-actions"><button type="button" :disabled="index===0" @click="moveRow(media,index,-1)">↑</button><button type="button" :disabled="index===media.length-1" @click="moveRow(media,index,1)">↓</button><button class="danger-text" type="button" @click="removeRow(media,index)">Remover</button></div></header>
            <div class="form-grid">
              <div class="form-field"><label class="field-label">Título</label><input v-model="item.title"></div>
              <div class="form-field"><label class="field-label">Tipo</label><select v-model="item.media_type"><option value="youtube">YouTube</option><option value="video">Vídeo</option><option value="image">Imagem</option></select></div>
              <div class="form-field wide"><label class="field-label">Mídia / URL *</label><ImageUploadField v-model="item.media_url" :scope="`publication-media-${routeId || 'new'}-${index}`" preset="free" allow-external required /></div>
              <div class="form-field wide"><label class="field-label">URL de embed</label><input v-model="item.embed_url" type="url" placeholder="Opcional; o YouTube também é detectado pela URL acima"></div>
              <div class="form-field is-toggle-field"><div class="toggle-copy"><span class="field-label">Visível</span></div><label class="switch"><input v-model="item.is_visible" type="checkbox"><span class="switch-track"><span /></span><span class="switch-state">{{ item.is_visible ? 'Sim' : 'Não' }}</span></label></div>
            </div>
          </article>
        </div>
        <div v-else class="inline-empty-state">Nenhuma mídia cadastrada.</div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading inline-heading"><div><p class="section-kicker">Conteúdo complementar</p><h2>Seções adicionais</h2><p>Blocos opcionais que aprofundam o conteúdo da publicação.</p></div><button class="secondary-button" type="button" @click="addSection">+ Adicionar seção</button></header>
        <div v-if="sections.length" class="repeatable-list">
          <article v-for="(item,index) in sections" :key="item.id || `section-${index}`" class="repeatable-card">
            <header class="repeatable-card-header"><strong>Seção {{ index + 1 }}</strong><div class="row-actions"><button type="button" :disabled="index===0" @click="moveRow(sections,index,-1)">↑</button><button type="button" :disabled="index===sections.length-1" @click="moveRow(sections,index,1)">↓</button><button class="danger-text" type="button" @click="removeRow(sections,index)">Remover</button></div></header>
            <div class="form-grid">
              <div class="form-field"><label class="field-label">Chamada curta</label><input v-model="item.eyebrow"></div>
              <div class="form-field"><label class="field-label">Título *</label><input v-model="item.title" required></div>
              <div class="form-field wide"><label class="field-label">Texto</label><RichTextEditor v-model="item.body" :min-height="130" /></div>
              <div class="form-field wide"><label class="field-label">Itens</label><textarea v-model="item.items_text" rows="5" placeholder="Um item por linha"></textarea></div>
              <div class="form-field is-toggle-field"><div class="toggle-copy"><span class="field-label">Visível</span></div><label class="switch"><input v-model="item.is_visible" type="checkbox"><span class="switch-track"><span /></span><span class="switch-state">{{ item.is_visible ? 'Sim' : 'Não' }}</span></label></div>
            </div>
          </article>
        </div>
        <div v-else class="inline-empty-state">Nenhuma seção complementar cadastrada.</div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading inline-heading"><div><p class="section-kicker">Cases relacionados</p><h2>Projetos citados ou apresentados</h2><p>Associe os cases que realmente fizeram parte desta palestra ou publicação.</p></div><button class="secondary-button" type="button" @click="addRelatedProject">+ Relacionar projeto</button></header>
        <div v-if="relatedProjects.length" class="repeatable-list">
          <article v-for="(item,index) in relatedProjects" :key="item.id || `related-${index}`" class="repeatable-card">
            <header class="repeatable-card-header"><strong>Projeto relacionado {{ index + 1 }}</strong><div class="row-actions"><button type="button" :disabled="index===0" @click="moveRow(relatedProjects,index,-1)">↑</button><button type="button" :disabled="index===relatedProjects.length-1" @click="moveRow(relatedProjects,index,1)">↓</button><button class="danger-text" type="button" @click="removeRow(relatedProjects,index)">Remover</button></div></header>
            <div class="form-grid">
              <div class="form-field"><label class="field-label">Projeto *</label><select v-model="item.project_id" required><option value="">Selecione</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.label }}</option></select></div>
              <div class="form-field"><label class="field-label">Descrição da relação</label><input v-model="item.relation_label" placeholder="Ex.: Case apresentado durante a palestra"></div>
              <div class="form-field is-toggle-field"><div class="toggle-copy"><span class="field-label">Visível</span></div><label class="switch"><input v-model="item.is_visible" type="checkbox"><span class="switch-track"><span /></span><span class="switch-state">{{ item.is_visible ? 'Sim' : 'Não' }}</span></label></div>
            </div>
          </article>
        </div>
        <div v-else class="inline-empty-state">Nenhum projeto relacionado.</div>
      </section>

      <div class="form-actions form-actions-sticky">
        <div class="form-actions-copy"><strong>{{ isNew ? 'Cadastrar a publicação completa' : 'Salvar todas as alterações' }}</strong><span>Uma única ação atualiza a publicação e seus conteúdos relacionados.</span></div>
        <div class="form-actions-buttons"><RouterLink class="secondary-button link-button" to="/publications">Cancelar</RouterLink><button class="primary-button" type="submit" :disabled="saving">{{ saving ? 'Salvando tudo…' : 'Salvar publicação' }}</button></div>
      </div>
    </form>
  </div>
</template>
