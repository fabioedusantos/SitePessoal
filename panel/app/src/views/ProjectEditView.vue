<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ImageUploadField from '../components/ImageUploadField.vue'
import RichTextEditor from '../components/RichTextEditor.vue'
import { api } from '../lib/api'

interface Option { id: number; label: string }
interface ChildRow { id?: number; sort_order?: number; [key: string]: any }

type ChildResource = 'project-links' | 'project-media' | 'project-sections'

const route = useRoute()
const router = useRouter()
const routeId = computed(() => route.params.id ? Number(route.params.id) : null)
const isNew = computed(() => !routeId.value)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const savedMessage = ref('')
const projectPageId = ref<number | null>(null)

const organizations = ref<Option[]>([])
const experiences = ref<Option[]>([])
const educationOptions = ref<Option[]>([])
const certificationOptions = ref<Option[]>([])
const technologies = ref<Option[]>([])

const form = reactive<Record<string, any>>({
  organization_id: '',
  experience_id: '',
  name: '',
  slug: '',
  period_label: '',
  context: '',
  participation: '',
  solution: '',
  architecture: '',
  challenges: '',
  results: '',
  repository_url: '',
  external_url: '',
  cover_image_url: '',
  featured: false,
  sort_order: 0,
  status: 'draft'
})

const links = ref<ChildRow[]>([])
const media = ref<ChildRow[]>([])
const sections = ref<ChildRow[]>([])
const selectedTechnologyIds = ref<number[]>([])
const selectedExperienceIds = ref<number[]>([])
const selectedEducationIds = ref<number[]>([])
const selectedCertificationIds = ref<number[]>([])

const originalIds = reactive<Record<ChildResource, Set<number>>>({
  'project-links': new Set<number>(),
  'project-media': new Set<number>(),
  'project-sections': new Set<number>()
})
const originalTechnologyRelations = ref<Record<number, number>>({})
const originalContextRelations = ref<Record<string, number>>({})

const publicProjectUrl = computed(() => {
  const base = import.meta.env.VITE_SITE_URL || (import.meta.env.DEV ? 'http://localhost:3000' : 'https://fbsantos.com.br')
  return form.slug ? `${base.replace(/\/$/, '')}/projetos/${form.slug}` : ''
})

function numeric(value: unknown, fallback = 0) {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : fallback
}

function normalizeBoolean(value: unknown) {
  return value === true || value === 1 || value === '1'
}

function normalizeRows(rows: ChildRow[]) {
  return [...rows]
    .sort((a, b) => numeric(a.sort_order) - numeric(b.sort_order) || numeric(a.id) - numeric(b.id))
    .map((row, index) => ({ ...row, sort_order: (index + 1) * 10 }))
}

async function loadAll(resource: string) {
  return api<{ data: Record<string, any>[] }>(`/admin/${resource}`).then(result => result.data)
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [organizationRows, experienceRows, educationRows, certificationRows, technologyRows] = await Promise.all([
      loadAll('organizations'), loadAll('experiences'), loadAll('education'), loadAll('certifications'), loadAll('technologies')
    ])

    const orgNames = Object.fromEntries(organizationRows.map(item => [Number(item.id), String(item.name || '')]))
    organizations.value = organizationRows.map(item => ({ id: Number(item.id), label: String(item.name || `#${item.id}`) }))
    experiences.value = experienceRows.map(item => ({
      id: Number(item.id),
      label: `${item.role_title || `#${item.id}`}${orgNames[Number(item.organization_id)] ? ` · ${orgNames[Number(item.organization_id)]}` : ''}`
    }))
    educationOptions.value = educationRows.map(item => ({ id: Number(item.id), label: `${item.course || `#${item.id}`} · ${item.institution || 'Instituição não informada'}` }))
    certificationOptions.value = certificationRows.map(item => ({ id: Number(item.id), label: `${item.name || `#${item.id}`} · ${item.issuer || 'Emissor não informado'}` }))
    technologies.value = technologyRows.map(item => ({ id: Number(item.id), label: String(item.name || `#${item.id}`) }))

    if (!routeId.value) return

    const [projectResult, linkRows, mediaRows, sectionRows, technologyRelations, contextRelations, pageRows] = await Promise.all([
      api<{ data: Record<string, any> }>(`/admin/projects/${routeId.value}`),
      loadAll('project-links'), loadAll('project-media'), loadAll('project-sections'),
      loadAll('project-technologies'), loadAll('project-relations'), loadAll('pages')
    ])

    const projectPage = pageRows.find(row => row.entity_type === 'project' && Number(row.entity_id) === routeId.value)
    projectPageId.value = projectPage ? Number(projectPage.id) : null

    const project = projectResult.data
    Object.keys(form).forEach(key => {
      if (key === 'featured') form[key] = normalizeBoolean(project[key])
      else form[key] = project[key] ?? (key === 'sort_order' ? 0 : '')
    })

    links.value = normalizeRows(linkRows.filter(row => Number(row.project_id) === routeId.value))
    media.value = normalizeRows(mediaRows.filter(row => Number(row.project_id) === routeId.value))
    sections.value = normalizeRows(sectionRows.filter(row => Number(row.project_id) === routeId.value).map(row => ({
      ...row,
      is_visible: normalizeBoolean(row.is_visible),
      items_text: (() => {
        try {
          const parsed = typeof row.items === 'string' ? JSON.parse(row.items) : row.items
          return Array.isArray(parsed) ? parsed.join('\n') : ''
        } catch { return String(row.items || '') }
      })()
    })))
    links.value = links.value.map(row => ({ ...row, is_visible: normalizeBoolean(row.is_visible) }))
    media.value = media.value.map(row => ({ ...row, is_visible: normalizeBoolean(row.is_visible) }))

    for (const resource of ['project-links', 'project-media', 'project-sections'] as ChildResource[]) {
      originalIds[resource].clear()
    }
    links.value.forEach(row => row.id && originalIds['project-links'].add(Number(row.id)))
    media.value.forEach(row => row.id && originalIds['project-media'].add(Number(row.id)))
    sections.value.forEach(row => row.id && originalIds['project-sections'].add(Number(row.id)))

    const techForProject = technologyRelations.filter(row => Number(row.project_id) === routeId.value)
    selectedTechnologyIds.value = techForProject.map(row => Number(row.technology_id))
    originalTechnologyRelations.value = Object.fromEntries(techForProject.map(row => [Number(row.technology_id), Number(row.id)]))

    const relationsForProject = contextRelations.filter(row => Number(row.project_id) === routeId.value && normalizeBoolean(row.is_visible))
    selectedExperienceIds.value = relationsForProject.filter(row => row.entity_type === 'experience').map(row => Number(row.entity_id))
    selectedEducationIds.value = relationsForProject.filter(row => row.entity_type === 'education').map(row => Number(row.entity_id))
    selectedCertificationIds.value = relationsForProject.filter(row => row.entity_type === 'certification').map(row => Number(row.entity_id))
    originalContextRelations.value = Object.fromEntries(relationsForProject.map(row => [`${row.entity_type}:${Number(row.entity_id)}`, Number(row.id)]))
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao carregar o case.'
  } finally {
    loading.value = false
  }
}

function slugifyName() {
  if (form.slug || !form.name) return
  form.slug = String(form.name)
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
}

function addLink() {
  links.value.push({ label: '', url: '', kind: 'external', is_visible: true, sort_order: (links.value.length + 1) * 10 })
}

function addMedia() {
  media.value.push({ media_url: '', alt_text: '', caption: '', kind: 'image', is_visible: true, sort_order: (media.value.length + 1) * 10 })
}

function addSection() {
  sections.value.push({ eyebrow: '', title: '', body: '', items_text: '', is_visible: true, sort_order: (sections.value.length + 1) * 10 })
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

function projectPayload() {
  return {
    organization_id: form.organization_id || '',
    experience_id: form.experience_id || '',
    name: String(form.name || '').trim(),
    slug: String(form.slug || '').trim(),
    period_label: form.period_label || '',
    context: form.context || '',
    participation: form.participation || '',
    solution: form.solution || '',
    architecture: form.architecture || '',
    challenges: form.challenges || '',
    results: form.results || '',
    repository_url: form.repository_url || '',
    external_url: form.external_url || '',
    cover_image_url: form.cover_image_url || '',
    featured: form.featured ? 1 : 0,
    sort_order: numeric(form.sort_order),
    status: form.status || 'draft'
  }
}

async function syncCollection(resource: ChildResource, rows: ChildRow[], projectId: number, payloadFor: (row: ChildRow, index: number) => Record<string, any>) {
  const currentIds = new Set<number>()

  for (let index = 0; index < rows.length; index += 1) {
    const row = rows[index]
    const body = payloadFor(row, index)
    if (row.id) {
      await api(`/admin/${resource}/${row.id}`, { method: 'PUT', body: JSON.stringify(body) })
      currentIds.add(Number(row.id))
    } else {
      const result = await api<{ id: number }>(`/admin/${resource}`, { method: 'POST', body: JSON.stringify({ ...body, project_id: projectId }) })
      row.id = result.id
      currentIds.add(result.id)
    }
  }

  for (const oldId of [...originalIds[resource]]) {
    if (!currentIds.has(oldId)) await api(`/admin/${resource}/${oldId}`, { method: 'DELETE' })
  }

  originalIds[resource].clear()
  currentIds.forEach(id => originalIds[resource].add(id))
}

async function syncTechnologyRelations(selected: number[], originals: Record<number, number>, projectId: number) {
  const selectedSet = new Set(selected.map(Number))

  for (const value of selectedSet) {
    const sortOrder = (selected.indexOf(value) + 1) * 10
    if (!originals[value]) {
      const result = await api<{ id: number }>('/admin/project-technologies', {
        method: 'POST',
        body: JSON.stringify({ project_id: projectId, technology_id: value, sort_order: sortOrder })
      })
      originals[value] = result.id
    } else {
      await api(`/admin/project-technologies/${originals[value]}`, {
        method: 'PUT',
        body: JSON.stringify({ project_id: projectId, technology_id: value, sort_order: sortOrder })
      })
    }
  }

  for (const [valueText, relationId] of Object.entries({ ...originals })) {
    const value = Number(valueText)
    if (!selectedSet.has(value)) {
      await api(`/admin/project-technologies/${relationId}`, { method: 'DELETE' })
      delete originals[value]
    }
  }
}

async function syncContextRelations(projectId: number) {
  const desired = [
    ...selectedExperienceIds.value.map(entityId => ({ entity_type: 'experience', entity_id: Number(entityId) })),
    ...selectedEducationIds.value.map(entityId => ({ entity_type: 'education', entity_id: Number(entityId) })),
    ...selectedCertificationIds.value.map(entityId => ({ entity_type: 'certification', entity_id: Number(entityId) }))
  ]
  const desiredKeys = new Set(desired.map(item => `${item.entity_type}:${item.entity_id}`))

  for (let index = 0; index < desired.length; index += 1) {
    const item = desired[index]
    const key = `${item.entity_type}:${item.entity_id}`
    const payload = {
      project_id: projectId,
      entity_type: item.entity_type,
      entity_id: item.entity_id,
      relation_label: '',
      sort_order: (index + 1) * 10,
      is_visible: 1
    }

    if (originalContextRelations.value[key]) {
      await api(`/admin/project-relations/${originalContextRelations.value[key]}`, { method: 'PUT', body: JSON.stringify(payload) })
    } else {
      const result = await api<{ id: number }>('/admin/project-relations', { method: 'POST', body: JSON.stringify(payload) })
      originalContextRelations.value[key] = result.id
    }
  }

  for (const [key, relationId] of Object.entries({ ...originalContextRelations.value })) {
    if (!desiredKeys.has(key)) {
      await api(`/admin/project-relations/${relationId}`, { method: 'DELETE' })
      delete originalContextRelations.value[key]
    }
  }
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
  if (!String(form.name || '').trim()) return 'Informe o nome do case.'
  if (!String(form.slug || '').trim()) return 'Informe o slug do case.'
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(String(form.slug).trim())) return 'O slug deve usar apenas letras minúsculas, números e hífens.'
  if (!isValidUrl(form.repository_url)) return 'Informe uma URL válida no campo Repositório.'
  if (!isValidUrl(form.external_url)) return 'Informe uma URL válida no campo Link externo.'

  for (let index = 0; index < links.value.length; index += 1) {
    const item = links.value[index]
    if (!String(item.label || '').trim()) return `Informe o nome do link ${index + 1}.`
    if (!String(item.url || '').trim()) return `Informe a URL do link ${index + 1}.`
    if (!isValidUrl(item.url)) return `A URL do link ${index + 1} não é válida.`
  }

  for (let index = 0; index < media.value.length; index += 1) {
    const item = media.value[index]
    if (!String(item.media_url || '').trim()) return `Informe a imagem ou mídia ${index + 1}.`
    if (!String(item.alt_text || '').trim()) return `Informe o texto alternativo da mídia ${index + 1}.`
  }

  for (let index = 0; index < sections.value.length; index += 1) {
    if (!String(sections.value[index].title || '').trim()) return `Informe o título da seção adicional ${index + 1}.`
  }

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
    let projectId = routeId.value
    if (projectId) {
      await api(`/admin/projects/${projectId}`, { method: 'PUT', body: JSON.stringify(projectPayload()) })
    } else {
      const result = await api<{ id: number }>('/admin/projects', { method: 'POST', body: JSON.stringify(projectPayload()) })
      projectId = result.id
    }

    links.value = normalizeRows(links.value)
    media.value = normalizeRows(media.value)
    sections.value = normalizeRows(sections.value)

    await syncCollection('project-links', links.value, projectId, (row, index) => ({
      project_id: projectId,
      label: row.label,
      url: row.url,
      kind: row.kind || 'external',
      sort_order: (index + 1) * 10,
      is_visible: row.is_visible ? 1 : 0
    }))

    await syncCollection('project-media', media.value, projectId, (row, index) => ({
      project_id: projectId,
      media_url: row.media_url,
      alt_text: row.alt_text,
      caption: row.caption || '',
      kind: row.kind || 'image',
      sort_order: (index + 1) * 10,
      is_visible: row.is_visible ? 1 : 0
    }))

    await syncCollection('project-sections', sections.value, projectId, (row, index) => ({
      project_id: projectId,
      eyebrow: row.eyebrow || '',
      title: row.title,
      body: row.body || '',
      items: String(row.items_text || '').split(/\r?\n/).map((item: string) => item.trim()).filter(Boolean),
      sort_order: (index + 1) * 10,
      is_visible: row.is_visible ? 1 : 0
    }))

    await syncTechnologyRelations(selectedTechnologyIds.value, originalTechnologyRelations.value, projectId)
    const primaryExperienceId = Number(form.experience_id || 0)
    if (primaryExperienceId && !selectedExperienceIds.value.includes(primaryExperienceId)) {
      selectedExperienceIds.value.push(primaryExperienceId)
    }
    await syncContextRelations(projectId)

    savedMessage.value = 'Case salvo com sucesso. Todas as informações relacionadas foram atualizadas.'
    if (isNew.value) {
      await router.replace(`/projects/${projectId}`)
    } else {
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao salvar o case.'
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
        <RouterLink class="back-link" to="/projects">← Voltar para cases e projetos</RouterLink>
        <p class="overline">Case completo</p>
        <h1>{{ isNew ? 'Novo case' : `Editar ${form.name || 'case'}` }}</h1>
        <p>Configure a apresentação, conteúdo, links, imagens, tecnologias e relações do case em uma única tela.</p>
      </div>
      <RouterLink v-if="!isNew && projectPageId" class="secondary-button link-button" :to="`/pages/${projectPageId}`">Editar layout da página</RouterLink>
      <a v-if="!isNew && publicProjectUrl && form.status === 'published'" class="secondary-button link-button" :href="publicProjectUrl" target="_blank" rel="noopener">Ver página pública ↗</a>
    </header>

    <div v-if="error" class="alert" role="alert">{{ error }}</div>
    <div v-if="savedMessage" class="success" role="status">{{ savedMessage }}</div>

    <div v-if="loading" class="editor-loading" aria-live="polite">
      <div class="editor-loading-line" />
      <div class="editor-loading-block" />
      <div class="editor-loading-block short" />
    </div>

    <form v-else class="resource-editor-form project-unified-form" novalidate @submit.prevent="save">
      <section class="editor-section">
        <header class="editor-section-heading">
          <div><p class="section-kicker">Identificação</p><h2>Informações principais</h2><p>Nome, endereço da página, vínculo profissional e período.</p></div>
        </header>
        <div class="form-grid">
          <div class="form-field"><label class="field-label" for="project-name">Nome *</label><input id="project-name" v-model="form.name" required @blur="slugifyName"></div>
          <div class="form-field is-code-field"><label class="field-label" for="project-slug">Slug *</label><input id="project-slug" v-model="form.slug" required placeholder="contaokay"><small class="field-help">Usado em /projetos/slug.</small></div>
          <div class="form-field"><label class="field-label" for="project-org">Organização</label><select id="project-org" v-model="form.organization_id"><option value="">Sem organização específica</option><option v-for="item in organizations" :key="item.id" :value="item.id">{{ item.label }}</option></select></div>
          <div class="form-field"><label class="field-label" for="project-exp">Experiência principal</label><select id="project-exp" v-model="form.experience_id"><option value="">Sem experiência principal</option><option v-for="item in experiences" :key="item.id" :value="item.id">{{ item.label }}</option></select></div>
          <div class="form-field"><label class="field-label" for="project-period">Período</label><input id="project-period" v-model="form.period_label" placeholder="2025 — atual"></div>
          <div class="form-field"><label class="field-label" for="project-status">Publicação</label><select id="project-status" v-model="form.status"><option value="draft">Rascunho</option><option value="published">Publicado</option></select></div>
        </div>
      </section>

      <section class="editor-section home-preview-config">
        <header class="editor-section-heading">
          <div><p class="section-kicker">Cards e listagens</p><h2>Como este case é apresentado resumidamente</h2><p>A capa, o nome, o período e o contexto abaixo compõem a apresentação resumida usada nos cards do site. A presença na Home é escolhida na edição da Página inicial.</p></div>
        </header>
        <div class="form-grid">
          <div class="form-field wide"><label class="field-label">Imagem de capa</label><ImageUploadField v-model="form.cover_image_url" scope="project-cover" preset="showcase" /><small class="field-help">Use uma imagem que represente o produto ou case sem depender de texto pequeno.</small></div>
          <div class="form-field wide"><label class="field-label">Resumo / contexto</label><RichTextEditor v-model="form.context" :min-height="150" placeholder="Explique de forma objetiva o problema, produto ou contexto do case…" /><small class="field-help">Este texto também abre a página detalhada. Negrito, itálico e sublinhado são permitidos.</small></div>
          <div class="form-field is-toggle-field">
            <div class="toggle-copy"><span class="field-label">Priorizar na listagem geral</span><small class="field-help">Altera a prioridade na página geral de projetos. Não controla a Home; a seleção da página inicial é feita na própria seção.</small></div>
            <label class="switch"><input v-model="form.featured" type="checkbox"><span class="switch-track" aria-hidden="true"><span /></span><span class="switch-state">{{ form.featured ? 'Ativo' : 'Inativo' }}</span></label>
          </div>
          <div class="form-field"><label class="field-label" for="project-order">Ordem geral</label><input id="project-order" v-model.number="form.sort_order" type="number"><small class="field-help">Usada apenas quando o site precisa desempatar/ordenar cases. Menor aparece primeiro.</small></div>
        </div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading"><div><p class="section-kicker">Página detalhada</p><h2>Narrativa do case</h2><p>Preencha somente o que realmente fizer sentido. Blocos sem conteúdo não precisam ser usados.</p></div></header>
        <div class="form-grid narrative-grid">
          <div class="form-field wide"><label class="field-label">Minha participação</label><RichTextEditor v-model="form.participation" :min-height="150" placeholder="O que você fez, liderou, decidiu ou implementou…" /></div>
          <div class="form-field wide"><label class="field-label">Solução</label><RichTextEditor v-model="form.solution" :min-height="150" placeholder="Como a solução foi construída…" /></div>
          <div class="form-field wide"><label class="field-label">Arquitetura</label><RichTextEditor v-model="form.architecture" :min-height="150" placeholder="Arquitetura, integrações, serviços e decisões técnicas…" /></div>
          <div class="form-field wide"><label class="field-label">Desafios</label><RichTextEditor v-model="form.challenges" :min-height="150" placeholder="Principais desafios reais do projeto…" /></div>
          <div class="form-field wide"><label class="field-label">Resultados</label><RichTextEditor v-model="form.results" :min-height="150" placeholder="Resultados comprováveis, sem inventar números…" /></div>
        </div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading inline-heading"><div><p class="section-kicker">Links e recursos</p><h2>Destinos do case</h2><p>Site oficial, lojas, documentação, vídeos e outros recursos aparecem na página detalhada.</p></div><button class="secondary-button" type="button" @click="addLink">+ Adicionar link</button></header>
        <div class="form-grid compact-main-links">
          <div class="form-field"><label class="field-label">URL principal</label><input v-model="form.external_url" type="url" placeholder="https://…"><small class="field-help">Link principal opcional do projeto.</small></div>
          <div class="form-field"><label class="field-label">Repositório</label><input v-model="form.repository_url" type="url" placeholder="https://github.com/…"><small class="field-help">Somente quando houver repositório público.</small></div>
        </div>
        <div v-if="links.length" class="repeatable-list">
          <article v-for="(item, index) in links" :key="item.id || `new-link-${index}`" class="repeatable-card compact-card">
            <header class="repeatable-card-header"><strong>Link {{ index + 1 }}</strong><div class="row-actions"><button type="button" :disabled="index === 0" title="Mover para cima" @click="moveRow(links, index, -1)">↑</button><button type="button" :disabled="index === links.length - 1" title="Mover para baixo" @click="moveRow(links, index, 1)">↓</button><button class="danger-text" type="button" @click="removeRow(links, index)">Remover</button></div></header>
            <div class="form-grid">
              <div class="form-field"><label class="field-label">Nome *</label><input v-model="item.label" required placeholder="Site oficial"></div>
              <div class="form-field"><label class="field-label">Tipo</label><select v-model="item.kind"><option value="website">Site</option><option value="google-play">Google Play</option><option value="app-store">App Store</option><option value="repository">Repositório</option><option value="documentation">Documentação</option><option value="youtube">YouTube</option><option value="video">Vídeo</option><option value="external">Outro link</option></select></div>
              <div class="form-field wide"><label class="field-label">URL *</label><input v-model="item.url" required type="url" placeholder="https://…"></div>
              <div class="form-field is-toggle-field"><div class="toggle-copy"><span class="field-label">Visível</span></div><label class="switch"><input v-model="item.is_visible" type="checkbox"><span class="switch-track"><span /></span><span class="switch-state">{{ item.is_visible ? 'Sim' : 'Não' }}</span></label></div>
            </div>
          </article>
        </div>
        <div v-else class="inline-empty-state">Nenhum link adicional. Você pode deixar esta área vazia.</div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading inline-heading"><div><p class="section-kicker">Imagens e vídeos</p><h2>Galeria do case</h2><p>Faça os uploads diretamente aqui. A ordem visual é controlada pelos botões de mover.</p></div><button class="secondary-button" type="button" @click="addMedia">+ Adicionar mídia</button></header>
        <div v-if="media.length" class="repeatable-list media-repeatable-list">
          <article v-for="(item, index) in media" :key="item.id || `new-media-${index}`" class="repeatable-card">
            <header class="repeatable-card-header"><strong>Mídia {{ index + 1 }}</strong><div class="row-actions"><button type="button" :disabled="index === 0" @click="moveRow(media, index, -1)">↑</button><button type="button" :disabled="index === media.length - 1" @click="moveRow(media, index, 1)">↓</button><button class="danger-text" type="button" @click="removeRow(media, index)">Remover</button></div></header>
            <div class="form-grid">
              <div class="form-field wide"><label class="field-label">Imagem ou mídia *</label><ImageUploadField v-model="item.media_url" :scope="`project-media-${routeId || 'new'}-${index}`" preset="free" allow-external required /></div>
              <div class="form-field"><label class="field-label">Tipo</label><select v-model="item.kind"><option value="image">Imagem</option><option value="screenshot">Screenshot</option><option value="youtube">YouTube</option><option value="video">Vídeo</option></select></div>
              <div class="form-field"><label class="field-label">Texto alternativo *</label><input v-model="item.alt_text" required placeholder="Descreva o que aparece na imagem"></div>
              <div class="form-field wide"><label class="field-label">Legenda</label><textarea v-model="item.caption" rows="3" placeholder="Legenda opcional para contextualizar a mídia"></textarea></div>
              <div class="form-field is-toggle-field"><div class="toggle-copy"><span class="field-label">Visível</span></div><label class="switch"><input v-model="item.is_visible" type="checkbox"><span class="switch-track"><span /></span><span class="switch-state">{{ item.is_visible ? 'Sim' : 'Não' }}</span></label></div>
            </div>
          </article>
        </div>
        <div v-else class="inline-empty-state">Nenhuma mídia cadastrada. O case pode funcionar apenas com a capa.</div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading inline-heading"><div><p class="section-kicker">Conteúdo extra</p><h2>Seções adicionais</h2><p>Crie blocos livres quando o case precisar de informações além da narrativa padrão.</p></div><button class="secondary-button" type="button" @click="addSection">+ Adicionar seção</button></header>
        <div v-if="sections.length" class="repeatable-list">
          <article v-for="(item, index) in sections" :key="item.id || `new-section-${index}`" class="repeatable-card">
            <header class="repeatable-card-header"><strong>Seção {{ index + 1 }}</strong><div class="row-actions"><button type="button" :disabled="index === 0" @click="moveRow(sections, index, -1)">↑</button><button type="button" :disabled="index === sections.length - 1" @click="moveRow(sections, index, 1)">↓</button><button class="danger-text" type="button" @click="removeRow(sections, index)">Remover</button></div></header>
            <div class="form-grid">
              <div class="form-field"><label class="field-label">Chamada curta</label><input v-model="item.eyebrow" placeholder="Ex.: Decisão técnica"></div>
              <div class="form-field"><label class="field-label">Título *</label><input v-model="item.title" required></div>
              <div class="form-field wide"><label class="field-label">Texto</label><RichTextEditor v-model="item.body" :min-height="130" /></div>
              <div class="form-field wide"><label class="field-label">Itens</label><textarea v-model="item.items_text" rows="5" placeholder="Um item por linha"></textarea></div>
              <div class="form-field is-toggle-field"><div class="toggle-copy"><span class="field-label">Visível</span></div><label class="switch"><input v-model="item.is_visible" type="checkbox"><span class="switch-track"><span /></span><span class="switch-state">{{ item.is_visible ? 'Sim' : 'Não' }}</span></label></div>
            </div>
          </article>
        </div>
        <div v-else class="inline-empty-state">Nenhuma seção adicional. Não é obrigatório criar blocos extras.</div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading"><div><p class="section-kicker">Relacionamentos</p><h2>Tecnologias utilizadas</h2><p>Marque somente tecnologias efetivamente utilizadas neste case.</p></div></header>
        <div class="choice-grid">
          <label v-for="item in technologies" :key="item.id" class="choice-chip"><input v-model="selectedTechnologyIds" type="checkbox" :value="item.id"><span>{{ item.label }}</span></label>
        </div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading"><div><p class="section-kicker">Relações com a trajetória</p><h2>Contextos relacionados ao case</h2><p>Associe somente o que realmente possui relação com este projeto. Pode ser uma experiência profissional, uma formação acadêmica ou um curso/certificação.</p></div></header>

        <div class="relationship-group">
          <div class="relationship-group-heading"><strong>Experiências profissionais</strong><span>Empregos, contratos ou atuações em que o projeto foi desenvolvido ou aplicado.</span></div>
          <div class="choice-grid choice-grid-large">
            <label v-for="item in experiences" :key="item.id" class="choice-chip"><input v-model="selectedExperienceIds" type="checkbox" :value="item.id"><span>{{ item.label }}</span></label>
          </div>
        </div>

        <div class="relationship-group">
          <div class="relationship-group-heading"><strong>Formação acadêmica</strong><span>Faculdade, pós-graduação ou outra formação formal relacionada ao projeto.</span></div>
          <div class="choice-grid choice-grid-large">
            <label v-for="item in educationOptions" :key="item.id" class="choice-chip"><input v-model="selectedEducationIds" type="checkbox" :value="item.id"><span>{{ item.label }}</span></label>
          </div>
        </div>

        <div class="relationship-group">
          <div class="relationship-group-heading"><strong>Cursos e certificações</strong><span>Qualificações, aperfeiçoamentos ou certificados que tenham relação real com o case.</span></div>
          <div class="choice-grid choice-grid-large">
            <label v-for="item in certificationOptions" :key="item.id" class="choice-chip"><input v-model="selectedCertificationIds" type="checkbox" :value="item.id"><span>{{ item.label }}</span></label>
          </div>
        </div>

        <p class="field-help relationship-empty-note">Se nenhum item for associado, a seção “Trajetória relacionada” não será exibida na página pública do case.</p>
      </section>

      <div class="form-actions form-actions-sticky">
        <div class="form-actions-copy"><strong>{{ isNew ? 'Cadastrar o case completo' : 'Salvar todas as alterações' }}</strong><span>Uma única ação atualiza o projeto e todos os conteúdos relacionados desta tela.</span></div>
        <div class="form-actions-buttons"><RouterLink class="secondary-button link-button" to="/projects">Cancelar</RouterLink><button class="primary-button" type="submit" :disabled="saving">{{ saving ? 'Salvando tudo…' : 'Salvar case' }}</button></div>
      </div>
    </form>
  </div>
</template>
