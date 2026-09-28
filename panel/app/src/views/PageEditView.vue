<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../lib/api'
import ImageUploadField from '../components/ImageUploadField.vue'
import RichTextEditor from '../components/RichTextEditor.vue'

interface PageRow {
  id?: number
  title: string
  slug: string
  status: 'draft' | 'published'
  page_kind: string
  entity_type?: string | null
  entity_id?: number | null
  seo_title: string
  seo_description: string
  canonical_url: string
  og_image_url: string
  noindex: boolean
  show_in_sitemap: boolean
  sort_order: number
}

interface SectionItem {
  id?: number
  section_id?: number
  item_type: 'manual' | 'entity'
  entity_type?: string
  entity_id?: number
  eyebrow?: string
  title?: string
  subtitle?: string
  body?: string
  value_label?: string
  url?: string
  image_url?: string
  image_alt?: string
  settings?: Record<string, any>
  sort_order: number
  is_visible: boolean
}

interface SectionRow {
  id?: number
  page_id?: number
  section_type: string
  internal_name: string
  eyebrow: string
  title: string
  accent_title: string
  lead: string
  body: string
  image_url: string
  image_alt: string
  cta_label: string
  cta_url: string
  theme: string
  background_color: string
  text_color: string
  accent_color: string
  width_mode: string
  spacing_top: string
  spacing_bottom: string
  settings: Record<string, any>
  sort_order: number
  is_visible: boolean
  items: SectionItem[]
}

interface ProjectMediaOption {
  id: number
  project_id: number
  media_url: string
  alt_text: string
  caption: string
  kind: string
  sort_order: number
  is_visible: boolean
}

type EntityKey = 'project' | 'experience' | 'organization' | 'technology' | 'education' | 'certification' | 'publication' | 'evaluation_board'

const route = useRoute()
const router = useRouter()
const pageId = ref(0)
const requestedPageSlug = computed(() => String(route.params.slug || '').trim())
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const previewVersion = ref(0)
const newSectionType = ref('text')
const siteUrl = import.meta.env.VITE_SITE_URL || (import.meta.env.DEV ? 'http://localhost:3000' : 'https://fbsantos.com.br')
const apiBase = import.meta.env.VITE_API_BASE || 'http://localhost:8080/api/v1'
const apiOrigin = apiBase.replace(/\/api\/v1\/?$/, '')

const page = reactive<PageRow>({
  title: '', slug: '', status: 'draft', page_kind: 'standard', seo_title: '', seo_description: '', canonical_url: '', og_image_url: '', noindex: false, show_in_sitemap: true, sort_order: 0
})
const sections = ref<SectionRow[]>([])
const removedSectionIds = new Set<number>()
const removedItemIds = new Set<number>()
const entityOptions = reactive<Record<EntityKey, Array<{ id: number; label: string; meta?: string }>>>({
  project: [], experience: [], organization: [], technology: [], education: [], certification: [], publication: [], evaluation_board: []
})
const projectMediaOptions = ref<ProjectMediaOption[]>([])

const sectionTypes = [
  { value: 'hero', label: 'Hero principal', description: 'Abertura grande com título, destaque, imagem e chamadas.' },
  { value: 'page-hero', label: 'Abertura de página', description: 'Cabeçalho editorial para páginas internas.' },
  { value: 'text', label: 'Texto', description: 'Bloco editorial de texto.' },
  { value: 'text-image', label: 'Texto + imagem', description: 'Conteúdo editorial em duas colunas, com imagem preservada sem recorte.' },
  { value: 'video', label: 'Vídeo / YouTube', description: 'Incorpora um ou mais vídeos diretamente na página.' },
  { value: 'logo-cloud', label: 'Organizações / logos', description: 'Faixa de organizações selecionadas.' },
  { value: 'stats', label: 'Indicadores', description: 'Números e destaques curtos.' },
  { value: 'project-feature', label: 'Projeto em destaque', description: 'Apresenta um projeto/case em evidência.' },
  { value: 'projects-grid', label: 'Grade de projetos', description: 'Lista ou seleção de projetos.' },
  { value: 'timeline', label: 'Trajetória', description: 'Experiências em linha do tempo.' },
  { value: 'cards', label: 'Cards livres', description: 'Cards com título, texto ou imagem, e link interno ou externo opcional.' },
  { value: 'technologies', label: 'Tecnologias', description: 'Tecnologias cadastradas ou selecionadas.' },
  { value: 'education', label: 'Formação acadêmica', description: 'Formações cadastradas ou selecionadas.' },
  { value: 'certifications', label: 'Certificações', description: 'Certificações cadastradas ou selecionadas.' },
  { value: 'publications', label: 'Publicações / palestras', description: 'Publicações cadastradas ou selecionadas.' },
  { value: 'evaluation-boards', label: 'Bancas avaliadoras', description: 'Bancas e reconhecimentos.' },
  { value: 'gallery', label: 'Galeria de projeto', description: 'Usa as mídias vinculadas ao projeto da página.' },
  { value: 'links', label: 'Links de projeto', description: 'Usa links oficiais vinculados ao projeto.' },
  { value: 'project-hero', label: 'Hero de projeto', description: 'Abertura alimentada pelo projeto vinculado à página.' },
  { value: 'project-story', label: 'Detalhes do projeto', description: 'Contexto, participação, solução, arquitetura, desafios e resultados.' },
  { value: 'related-contexts', label: 'Trajetória relacionada', description: 'Experiências profissionais, formação e certificações relacionadas ao projeto.' },
  { value: 'cta', label: 'Chamada para ação', description: 'Bloco final com título, texto e botão.' },
  { value: 'contact', label: 'Formulário de contato', description: 'Formulário público de contato.' }
]


const sectionVisualPresets: Record<string, Array<{ value: string; label: string; description: string }>> = {
  hero: [
    { value: 'profile-card', label: 'Perfil com foto', description: 'Layout visual da Home, com foto, moldura e destaques.' },
    { value: 'simple', label: 'Hero simples', description: 'Título e texto sem elementos decorativos extras.' }
  ],
  'text-image': [
    { value: 'image-left', label: 'Imagem à esquerda', description: 'Visual editorial com a imagem inteira à esquerda e texto à direita.' },
    { value: 'image-right', label: 'Imagem à direita', description: 'Mantém a imagem inteira à direita e o conteúdo à esquerda.' }
  ],
  'project-feature': [
    { value: 'device-showcase', label: 'Vitrine com telas', description: 'Capa do projeto e até três telas em destaque. Ideal para apps e produtos digitais.' },
    { value: 'cover', label: 'Capa do projeto', description: 'Apresentação mais simples usando somente a imagem de capa.' }
  ],
  cards: [
    { value: 'cards', label: 'Cards', description: 'Grade de cards convencional.' },
    { value: 'pillars', label: 'Pilares editoriais', description: 'Destaques numerados com visual mais editorial.' }
  ],
  technologies: [
    { value: 'groups', label: 'Agrupar por categoria', description: 'Organiza automaticamente as tecnologias por categoria.' },
    { value: 'chips', label: 'Lista compacta', description: 'Exibe todas as tecnologias em uma nuvem de chips.' }
  ],
  education: [
    { value: 'cards', label: 'Cards compactos', description: 'Ideal para a Home, mantendo as formações lado a lado.' },
    { value: 'detailed', label: 'Formação detalhada', description: 'Valoriza a instituição, período e comprovante em cards horizontais maiores.' }
  ]
}

const entityTypeBySection: Record<string, EntityKey | null> = {
  'logo-cloud': 'organization',
  'project-feature': 'project',
  'projects-grid': 'project',
  timeline: 'experience',
  technologies: 'technology',
  education: 'education',
  certifications: 'certification',
  publications: 'publication',
  'evaluation-boards': 'evaluation_board'
}

const manualItemSections = new Set(['hero', 'stats', 'cards', 'text', 'text-image', 'gallery', 'links', 'video'])
const projectBoundSourceBySection: Record<string, string> = {
  'project-hero': 'project',
  'project-story': 'project',
  technologies: 'project-technologies',
  gallery: 'project-media',
  links: 'project-links',
  'related-contexts': 'project-relations'
}
const previewUrl = computed(() => {
  const path = page.slug === 'home' ? '/' : `/${page.slug}`
  return `${siteUrl}${path}?cms-preview=${previewVersion.value}`
})


function visualPresetOptions(section: SectionRow) {
  return sectionVisualPresets[section.section_type] || []
}

function defaultVisualPreset(section: SectionRow) {
  if (section.section_type === 'hero') return 'profile-card'
  if (section.section_type === 'text-image') return 'image-left'
  if (section.section_type === 'project-feature') return 'device-showcase'
  if (section.section_type === 'cards') return section.internal_name?.toLowerCase().includes('fundamento') ? 'pillars' : 'cards'
  if (section.section_type === 'technologies') return 'groups'
  if (section.section_type === 'education') return section.internal_name?.toLowerCase().includes('acadêmica') ? 'detailed' : 'cards'
  return ''
}

function visualPreset(section: SectionRow) {
  return String(section.settings?.variant || defaultVisualPreset(section))
}

function visualPresetDescription(section: SectionRow) {
  return visualPresetOptions(section).find(preset => preset.value === visualPreset(section))?.description || ''
}

function changeVisualPreset(section: SectionRow, event: Event) {
  section.settings = { ...section.settings, variant: (event.target as HTMLSelectElement).value }
}

function bool(value: unknown) { return value === true || value === 1 || value === '1' }
function num(value: unknown, fallback = 0) { const n = Number(value); return Number.isFinite(n) ? n : fallback }
function parseSettings(value: unknown): Record<string, any> {
  if (value && typeof value === 'object') return value as Record<string, any>
  if (typeof value !== 'string' || !value.trim()) return {}
  try { return JSON.parse(value) || {} } catch { return {} }
}

function cardContentMode(item: SectionItem): 'text' | 'image' {
  if (item.settings?.content_mode === 'image') return 'image'
  if (item.settings?.content_mode === 'text') return 'text'
  // Cards existentes continuam no modo texto. Imagens antigas sem texto são reconhecidas.
  const body = String(item.body || '').replace(/<[^>]+>/g, ' ').replace(/&nbsp;/gi, ' ').trim()
  return item.image_url && !body ? 'image' : 'text'
}

function setCardContentMode(item: SectionItem, mode: 'text' | 'image') {
  if (cardContentMode(item) === mode) return
  item.settings = { ...item.settings, content_mode: mode }
  if (mode === 'image') item.body = ''
  else {
    item.image_url = ''
    item.image_alt = ''
  }
}

function normalizeSection(row: Record<string, any>, items: Record<string, any>[]): SectionRow {
  return {
    id: row.id ? Number(row.id) : undefined,
    page_id: row.page_id ? Number(row.page_id) : undefined,
    section_type: String(row.section_type || 'text'), internal_name: String(row.internal_name || ''),
    eyebrow: String(row.eyebrow || ''), title: String(row.title || ''), accent_title: String(row.accent_title || ''),
    lead: String(row.lead || ''), body: String(row.body || ''), image_url: String(row.image_url || ''), image_alt: String(row.image_alt || ''),
    cta_label: String(row.cta_label || ''), cta_url: String(row.cta_url || ''), theme: String(row.theme || 'default'),
    background_color: String(row.background_color || ''), text_color: String(row.text_color || ''), accent_color: String(row.accent_color || ''),
    width_mode: String(row.width_mode || 'normal'), spacing_top: String(row.spacing_top || 'large'), spacing_bottom: String(row.spacing_bottom || 'large'),
    settings: parseSettings(row.settings), sort_order: num(row.sort_order), is_visible: bool(row.is_visible),
    items: items.filter(item => Number(item.section_id) === Number(row.id)).sort((a,b) => num(a.sort_order)-num(b.sort_order)).map(item => ({
      id: Number(item.id), section_id: Number(item.section_id), item_type: String(item.item_type || 'manual') as 'manual'|'entity',
      entity_type: item.entity_type || '', entity_id: item.entity_id ? Number(item.entity_id) : undefined,
      eyebrow: String(item.eyebrow || ''), title: String(item.title || ''), subtitle: String(item.subtitle || ''), body: String(item.body || ''),
      value_label: String(item.value_label || ''), url: String(item.url || ''), image_url: String(item.image_url || ''), image_alt: String(item.image_alt || ''),
      settings: parseSettings(item.settings), sort_order: num(item.sort_order), is_visible: bool(item.is_visible)
    }))
  }
}

async function loadResource(resource: string) {
  return (await api<{ data: Record<string, any>[] }>(`/admin/${resource}`)).data
}

async function resolvePageId() {
  const routeId = Number(route.params.id || 0)
  if (routeId > 0) {
    pageId.value = routeId
    return
  }

  if (!requestedPageSlug.value) {
    throw new Error('Página não informada.')
  }

  const rows = await loadResource('pages')
  const match = rows.find(row => String(row.slug) === requestedPageSlug.value)
  if (!match) {
    throw new Error(`A página “${requestedPageSlug.value}” não foi encontrada no CMS.`)
  }
  pageId.value = Number(match.id)
}

async function load() {
  loading.value = true; error.value = ''; success.value = ''
  try {
    await resolvePageId()
    const [pageResult, sectionRows, itemRows, projects, experiences, organizations, technologies, education, certifications, publications, boards, projectMediaRows] = await Promise.all([
      api<{ data: Record<string, any> }>(`/admin/pages/${pageId.value}`), loadResource('page-sections'), loadResource('page-section-items'),
      loadResource('projects'), loadResource('experiences'), loadResource('organizations'), loadResource('technologies'), loadResource('education'), loadResource('certifications'), loadResource('publications'), loadResource('evaluation-boards'), loadResource('project-media')
    ])
    const r = pageResult.data
    Object.assign(page, { ...r, id: Number(r.id), noindex: bool(r.noindex), show_in_sitemap: bool(r.show_in_sitemap), sort_order: num(r.sort_order) })
    sections.value = sectionRows.filter(row => Number(row.page_id) === pageId.value).map(row => normalizeSection(row, itemRows)).sort((a,b) => a.sort_order-b.sort_order)
    entityOptions.project = projects.filter(x => x.status === 'published').map(x => ({ id:Number(x.id), label:String(x.name), meta:String(x.period_label || '') }))
    const orgMap = Object.fromEntries(organizations.map(x => [Number(x.id), String(x.name)]))
    entityOptions.experience = experiences.map(x => ({ id:Number(x.id), label:String(x.role_title), meta:orgMap[Number(x.organization_id)] || '' }))
    entityOptions.organization = organizations.filter(x => x.slug !== 'fbsantos-desenvolvimentos').map(x => ({ id:Number(x.id), label:String(x.name) }))
    entityOptions.technology = technologies.map(x => ({ id:Number(x.id), label:String(x.name), meta:String(x.category || '') }))
    entityOptions.education = education.map(x => ({ id:Number(x.id), label:String(x.course), meta:String(x.institution || '') }))
    entityOptions.certification = certifications.map(x => ({ id:Number(x.id), label:String(x.name), meta:String(x.issuer || '') }))
    entityOptions.publication = publications.map(x => ({ id:Number(x.id), label:String(x.title), meta:String(x.kind || '') }))
    entityOptions.evaluation_board = boards.map(x => ({ id:Number(x.id), label:String(x.title), meta:String(x.institution || '') }))
    projectMediaOptions.value = projectMediaRows
      .filter(row => String(row.kind || 'image').toLowerCase() === 'image' && bool(row.is_visible) && String(row.media_url || '').trim())
      .map(row => ({
        id: Number(row.id), project_id: Number(row.project_id), media_url: String(row.media_url), alt_text: String(row.alt_text || ''),
        caption: String(row.caption || ''), kind: String(row.kind || 'image'), sort_order: num(row.sort_order), is_visible: bool(row.is_visible)
      }))
      .sort((a, b) => a.project_id - b.project_id || a.sort_order - b.sort_order || a.id - b.id)
  } catch (e) { error.value = e instanceof Error ? e.message : 'Falha ao carregar a página.' }
  finally { loading.value = false }
}

function sectionTypeLabel(type: string) { return sectionTypes.find(x => x.value === type)?.label || type }
function isProjectPage() { return page.entity_type === 'project' && Number(page.entity_id || 0) > 0 }
function isProjectBoundSection(section: SectionRow) { return isProjectPage() && Boolean(projectBoundSourceBySection[section.section_type]) }
function entityTypeFor(section: SectionRow) { return isProjectBoundSection(section) ? null : (entityTypeBySection[section.section_type] || null) }
function supportsEntitySource(section: SectionRow) { return Boolean(entityTypeFor(section)) }
function sourceMode(section: SectionRow) { return section.settings?.source === 'all' ? 'all' : 'selected' }
function selectedProjectId(section: SectionRow) {
  if (section.section_type !== 'project-feature') return 0
  const item = section.items.find(entry => entry.item_type === 'entity' && entry.entity_type === 'project') || section.items[0]
  return Number(item?.entity_id || section.settings?.entity_id || 0)
}
function projectFeatureMedia(section: SectionRow) {
  const projectId = selectedProjectId(section)
  return projectMediaOptions.value.filter(item => item.project_id === projectId)
}
function projectFeatureMediaIds(section: SectionRow) {
  const raw = Array.isArray(section.settings?.featured_media_ids) ? section.settings.featured_media_ids : []
  return [0, 1, 2].map(index => Number(raw[index] || 0))
}
function featureMediaId(section: SectionRow, slotIndex: number) {
  return projectFeatureMediaIds(section)[slotIndex] || 0
}
function featureMediaById(section: SectionRow, slotIndex: number) {
  const id = featureMediaId(section, slotIndex)
  return projectFeatureMedia(section).find(item => item.id === id) || null
}
function featureMediaUsedInOtherSlot(section: SectionRow, mediaId: number, slotIndex: number) {
  return projectFeatureMediaIds(section).some((id, index) => index !== slotIndex && id === mediaId)
}
function setFeatureMediaId(section: SectionRow, slotIndex: number, event: Event) {
  const ids = projectFeatureMediaIds(section)
  ids[slotIndex] = Number((event.target as HTMLSelectElement).value || 0)
  section.settings = { ...section.settings, featured_media_ids: ids }
}
function resetProjectFeatureMedia(section: SectionRow) {
  if (section.section_type !== 'project-feature') return
  section.settings = { ...section.settings, featured_media_ids: [] }
}
function mediaPreviewUrl(source: string) {
  const value = String(source || '').trim()
  if (!value) return ''
  if (/^https?:\/\//i.test(value)) return value
  if (value.startsWith('/uploads/')) return `${apiOrigin}${value}`
  if (value.startsWith('/')) return `${siteUrl}${value}`
  return value
}
function setSourceMode(section: SectionRow, mode: string) {
  if (mode === 'all') {
    section.settings = { ...section.settings, source: 'all', entity_type: entityTypeFor(section) }
    section.items.filter(item => item.id).forEach(item => removedItemIds.add(item.id!))
    section.items = []
  } else {
    const { source, ...rest } = section.settings || {}
    section.settings = { ...rest, entity_type: entityTypeFor(section) }
  }
}

function changeSourceMode(section: SectionRow, event: Event) {
  setSourceMode(section, (event.target as HTMLSelectElement).value)
}

function addSection(type = newSectionType.value) {
  const definition = sectionTypes.find(item => item.value === type)
  const section: SectionRow = { section_type:type, internal_name:definition?.label || 'Nova seção', eyebrow:'', title:'', accent_title:'', lead:'', body:'', image_url:'', image_alt:'', cta_label:'', cta_url:'', theme:'default', background_color:'', text_color:'', accent_color:'', width_mode:type === 'hero' || type === 'projects-grid' || type === 'project-feature' ? 'wide' : 'normal', spacing_top:'large', spacing_bottom:'large', settings:{}, sort_order:(sections.value.length+1)*10, is_visible:true, items:[] }
  const preset = defaultVisualPreset(section)
  if (preset) section.settings.variant = preset
  if (entityTypeBySection[type]) section.settings.entity_type = entityTypeBySection[type]
  sections.value.push(section)
}
function removeSection(index:number) {
  const s=sections.value[index]; if (!s) return
  if (s.id) removedSectionIds.add(s.id)
  sections.value.splice(index,1); resequenceSections()
}
function moveSection(index:number, direction:-1|1) {
  const target=index+direction; if (target<0 || target>=sections.value.length) return
  const [s]=sections.value.splice(index,1); sections.value.splice(target,0,s); resequenceSections()
}
function resequenceSections(){ sections.value.forEach((s,i)=>s.sort_order=(i+1)*10) }
function addManualItem(section:SectionRow){ section.items.push({ item_type:'manual', title:'Novo item', body:'', settings: section.section_type === 'cards' ? { content_mode:'text' } : {}, sort_order:(section.items.length+1)*10, is_visible:true }) }
function addEntityItem(section:SectionRow){ const type=entityTypeFor(section); if(!type)return; if(section.section_type==='project-feature' && section.items.length)return; section.items.push({ item_type:'entity', entity_type:type, sort_order:(section.items.length+1)*10, is_visible:true }) }
function removeItem(section:SectionRow,index:number){ const item=section.items[index]; if(item?.id) removedItemIds.add(item.id); section.items.splice(index,1); resequenceItems(section) }
function moveItem(section:SectionRow,index:number,direction:-1|1){ const target=index+direction; if(target<0||target>=section.items.length)return; const [item]=section.items.splice(index,1); section.items.splice(target,0,item); resequenceItems(section) }
function resequenceItems(section:SectionRow){ section.items.forEach((item,i)=>item.sort_order=(i+1)*10) }

function sectionPayload(section:SectionRow){
  return {
    page_id: pageId.value, section_type: section.section_type, internal_name: section.internal_name || null, eyebrow: section.eyebrow || null,
    title: section.title || null, accent_title: section.accent_title || null, lead: section.lead || null, body: section.body || null,
    image_url: section.image_url || null, image_alt: section.image_alt || null, cta_label: section.cta_label || null, cta_url: section.cta_url || null,
    theme: section.theme || 'default', background_color: section.theme==='custom' ? section.background_color || null : null,
    text_color: section.theme==='custom' ? section.text_color || null : null, accent_color: section.theme==='custom' ? section.accent_color || null : null,
    width_mode: section.width_mode || 'normal', spacing_top: section.spacing_top || 'large', spacing_bottom: section.spacing_bottom || 'large',
    settings: JSON.stringify(section.settings || {}), sort_order: section.sort_order, is_visible: section.is_visible ? 1 : 0
  }
}
function itemPayload(item:SectionItem,sectionId:number,sectionType:string){
  const isCard = sectionType === 'cards' && item.item_type === 'manual'
  const imageMode = isCard && cardContentMode(item) === 'image'
  return {
    section_id:sectionId,item_type:item.item_type,entity_type:item.entity_type||null,entity_id:item.entity_id||null,
    eyebrow:item.eyebrow||null,title:item.title||null,subtitle:item.subtitle||null,
    body:imageMode ? null : item.body||null,value_label:item.value_label||null,url:item.url?.trim()||null,
    image_url:isCard && !imageMode ? null : item.image_url||null,
    image_alt:isCard && !imageMode ? null : item.image_alt||null,
    settings:JSON.stringify(isCard ? { ...item.settings, content_mode: imageMode ? 'image' : 'text' } : item.settings||{}),
    sort_order:item.sort_order,is_visible:item.is_visible?1:0
  }
}

function isValidPagePath(value: string) {
  return value === 'home' || /^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/.test(value)
}

function isSafePageUrl(value: unknown) {
  const text = String(value || '').trim()
  if (!text) return true
  if ((text.startsWith('/') && !text.startsWith('//')) || text.startsWith('#')) return true
  try {
    const url = new URL(text)
    return ['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol)
  } catch {
    return false
  }
}

function validateBeforeSave() {
  if (!String(page.title || '').trim()) return 'Informe o título interno da página.'
  const slug = String(page.slug || '').trim()
  if (!slug) return 'Informe o caminho da página.'
  if (!isValidPagePath(slug)) return 'O caminho deve usar letras minúsculas, números, hífens e barras entre segmentos. Não use barra no início.'
  if (!['draft', 'published'].includes(String(page.status || ''))) return 'Selecione um status válido para a página.'
  if (page.canonical_url && !isSafePageUrl(page.canonical_url)) return 'Informe uma URL canônica válida.'

  for (let sectionIndex = 0; sectionIndex < sections.value.length; sectionIndex += 1) {
    const section = sections.value[sectionIndex]
    const sectionName = section.internal_name || section.title || `Seção ${sectionIndex + 1}`
    if (!section.section_type) return `Selecione o tipo de “${sectionName}”.`
    if (section.cta_url && !isSafePageUrl(section.cta_url)) return `Revise a URL do botão em “${sectionName}”.`
    if (section.theme === 'custom') {
      for (const value of [section.background_color, section.text_color, section.accent_color]) {
        if (value && !/^#[0-9a-f]{6}$/i.test(String(value))) return `Revise as cores personalizadas em “${sectionName}”. Use o formato #RRGGBB.`
      }
    }
    if (sourceMode(section) !== 'all') {
      for (let itemIndex = 0; itemIndex < section.items.length; itemIndex += 1) {
        const item = section.items[itemIndex]
        if (item.item_type === 'entity' && !Number(item.entity_id)) return `Selecione o conteúdo do item ${itemIndex + 1} em “${sectionName}”.`
        if (item.url && !isSafePageUrl(item.url)) return `Revise a URL do item ${itemIndex + 1} em “${sectionName}”.`
        if (section.section_type === 'cards' && item.item_type === 'manual') {
          if (cardContentMode(item) === 'image' && !String(item.image_url || '').trim()) return `Selecione uma imagem para o card ${itemIndex + 1} em “${sectionName}”.`
        }
      }
    }
  }
  return ''
}

async function save() {
  error.value=''; success.value=''
  const validationError = validateBeforeSave()
  if (validationError) {
    error.value = validationError
    window.scrollTo({ top: 0, behavior: 'smooth' })
    return
  }

  saving.value=true
  try {
    await api(`/admin/pages/${pageId.value}`, { method:'PUT', body:JSON.stringify({
      title:page.title,slug:page.slug,status:page.status,page_kind:page.page_kind,entity_type:page.entity_type||null,entity_id:page.entity_id||null,
      seo_title:page.seo_title||null,seo_description:page.seo_description||null,canonical_url:page.canonical_url||null,og_image_url:page.og_image_url||null,
      noindex:page.noindex?1:0,show_in_sitemap:page.show_in_sitemap?1:0,sort_order:page.sort_order
    })})
    // "Usar todos" is a dynamic source. Explicit relations left from a previous
    // selection would be stale and could make the public API return only that subset.
    for (const section of sections.value) {
      if (sourceMode(section) !== 'all') continue
      section.items.filter(item => item.id).forEach(item => removedItemIds.add(item.id!))
      section.items = []
    }
    for (const id of removedItemIds) await api(`/admin/page-section-items/${id}`, { method:'DELETE' })
    for (const id of removedSectionIds) await api(`/admin/page-sections/${id}`, { method:'DELETE' })
    removedItemIds.clear(); removedSectionIds.clear(); resequenceSections()
    for (const section of sections.value) {
      if (isProjectBoundSection(section)) {
        section.settings = { ...section.settings, entity_type: projectBoundSourceBySection[section.section_type], entity_id: Number(page.entity_id) }
      } else if (supportsEntitySource(section)) {
        section.settings = { ...section.settings, entity_type: entityTypeFor(section) }
      }
      let sectionId=section.id
      if (sectionId) await api(`/admin/page-sections/${sectionId}`, { method:'PUT', body:JSON.stringify(sectionPayload(section)) })
      else { const created=await api<{id:number}>('/admin/page-sections',{method:'POST',body:JSON.stringify(sectionPayload(section))}); sectionId=created.id; section.id=sectionId }
      if (sourceMode(section)==='all') continue
      resequenceItems(section)
      for (const item of section.items) {
        if (item.item_type==='entity') item.entity_type=entityTypeFor(section)||item.entity_type
        if (item.id) await api(`/admin/page-section-items/${item.id}`,{method:'PUT',body:JSON.stringify(itemPayload(item,sectionId!,section.section_type))})
        else { const created=await api<{id:number}>('/admin/page-section-items',{method:'POST',body:JSON.stringify(itemPayload(item,sectionId!,section.section_type))}); item.id=created.id; item.section_id=sectionId }
      }
    }
    success.value='Página salva.'; previewVersion.value++
  } catch(e){ error.value=e instanceof Error?e.message:'Falha ao salvar a página.' }
  finally{ saving.value=false }
}

watch(() => [route.params.id, route.params.slug], load, { immediate:true })
</script>

<template>
  <div class="page-builder-page">
    <header class="page-title builder-title">
      <div>
        <p class="overline">Construtor de página</p>
        <h1>{{ page.title || 'Página' }}</h1>
        <p>Monte a página por seções. Conteúdo, ordem, fonte de dados e aparência pertencem à própria página.</p>
      </div>
      <div class="builder-top-actions">
        <a class="secondary-button link-button" :href="previewUrl" target="_blank" rel="noopener">Pré-visualizar ↗</a>
        <button class="primary-button" type="button" :disabled="saving" @click="save">{{ saving ? 'Salvando…' : 'Salvar página' }}</button>
      </div>
    </header>

    <div v-if="error" class="alert" role="alert">{{ error }}</div>
    <div v-if="success" class="success-message">{{ success }}</div>
    <div v-if="loading" class="builder-loading">Carregando página…</div>

    <template v-else>
      <details class="admin-panel builder-page-settings">
        <summary class="builder-page-settings-summary">
          <span><strong>Configurações da página</strong><small>URL, publicação e SEO</small></span>
          <span class="settings-summary-path">{{ page.slug === 'home' ? '/' : `/${page.slug}` }}</span>
        </summary>
        <div class="builder-page-settings-content">
          <div class="form-grid two-columns">
            <label><span>Título interno</span><input v-model="page.title" type="text"></label>
            <label><span>Caminho</span><input v-model="page.slug" type="text"><small>Use <strong>home</strong> para a página inicial; nos demais casos, sem barra inicial.</small></label>
            <label><span>Status</span><select v-model="page.status"><option value="draft">Rascunho</option><option value="published">Publicado</option></select></label>
            <label><span>Ordem no painel</span><input v-model.number="page.sort_order" type="number"><small>Controla a posição desta página no menu do painel.</small></label>
          </div>
          <details class="builder-details">
            <summary>SEO e compartilhamento</summary>
            <div class="form-grid two-columns builder-details-content">
              <label><span>Title</span><input v-model="page.seo_title" type="text"></label>
              <label><span>Canonical</span><input v-model="page.canonical_url" type="text"></label>
              <label class="span-two"><span>Description</span><textarea v-model="page.seo_description" rows="4" /></label>
              <div class="span-two"><label class="field-label">Imagem Open Graph</label><ImageUploadField v-model="page.og_image_url" :scope="`page-${pageId}-og`" preset="open-graph" /></div>
              <label class="switch-field"><input v-model="page.noindex" type="checkbox"><span>Não indexar esta página</span></label>
              <label class="switch-field"><input v-model="page.show_in_sitemap" type="checkbox"><span>Exibir no sitemap.xml</span></label>
            </div>
          </details>
        </div>
      </details>

      <details class="admin-panel builder-preview">
        <summary>Pré-visualização da versão salva</summary>
        <p>O preview usa o próprio site. Depois de salvar, ele é atualizado automaticamente.</p>
        <div class="builder-preview-frame"><iframe :key="previewVersion" :src="previewUrl" title="Pré-visualização da página" loading="lazy" /></div>
      </details>

      <section class="builder-section-list">
        <div class="builder-section-list-heading">
          <div><p class="overline">Composição</p><h2>Seções da página</h2><p>Esta é a página. Escolha os blocos, a ordem, quais dados aparecem e o estilo visual de cada seção.</p></div>
          <div class="section-add-control">
            <select v-model="newSectionType" aria-label="Tipo da nova seção"><option v-for="type in sectionTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select>
            <button class="primary-button" type="button" @click="addSection(newSectionType)">+ Adicionar seção</button>
          </div>
        </div>

        <article v-for="(section,index) in sections" :key="section.id || `new-${index}`" class="builder-section-card">
          <div class="builder-section-toolbar">
            <div class="builder-section-identification"><span class="builder-drag">⋮⋮</span><div><strong>{{ section.internal_name || sectionTypeLabel(section.section_type) }}</strong><small>{{ sectionTypeLabel(section.section_type) }}</small></div></div>
            <div class="builder-toolbar-actions">
              <button type="button" :disabled="index===0" @click="moveSection(index,-1)">↑</button><button type="button" :disabled="index===sections.length-1" @click="moveSection(index,1)">↓</button>
              <label class="mini-switch"><input v-model="section.is_visible" type="checkbox"><span>{{ section.is_visible ? 'Visível' : 'Oculta' }}</span></label>
              <button class="danger-text" type="button" @click="removeSection(index)">Excluir</button>
            </div>
          </div>

          <div class="builder-section-body">
            <div class="form-grid two-columns">
              <label><span>Tipo de seção</span><select v-model="section.section_type"><option v-for="type in sectionTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select><small>{{ sectionTypes.find(x=>x.value===section.section_type)?.description }}</small></label>
              <label><span>Nome interno</span><input v-model="section.internal_name" type="text" placeholder="Ex.: Produto em destaque"><small>Serve apenas para identificar a seção no CMS.</small></label>
              <label><span>Chamada curta</span><input v-model="section.eyebrow" type="text"></label>
              <label><span>Título</span><input v-model="section.title" type="text"></label>
              <label v-if="['hero','page-hero'].includes(section.section_type)"><span>Trecho destacado</span><input v-model="section.accent_title" type="text"><small>Usado para o trecho em degradê quando o layout suportar.</small></label>
              <label class="span-two"><span>Texto de abertura</span><RichTextEditor v-model="section.lead" /></label>
              <label v-if="['text','text-image','cta'].includes(section.section_type)" class="span-two"><span>Conteúdo</span><RichTextEditor v-model="section.body" /></label>
              <div v-if="['hero','page-hero','text-image'].includes(section.section_type)" class="span-two"><label class="field-label">Imagem da seção</label><ImageUploadField v-model="section.image_url" :scope="`page-${pageId}-section-${section.id || index}`" preset="free" /><label><span>Texto alternativo</span><input v-model="section.image_alt" type="text"></label></div>
              <label><span>Texto do botão</span><input v-model="section.cta_label" type="text"></label>
              <label><span>Destino do botão</span><input v-model="section.cta_url" type="text"></label>
            </div>

            <div v-if="section.section_type === 'hero'" class="builder-items-block hero-presentation-settings">
              <div class="builder-subheading"><div><strong>Apresentação do Hero</strong><span>Complementos visuais opcionais. Se deixar vazio, o site usa o padrão FBSantos.</span></div></div>
              <div class="form-grid two-columns">
                <label><span>Segundo botão</span><input v-model="section.settings.secondary_cta_label" type="text" placeholder="Conhecer trajetória"></label>
                <label><span>Destino do segundo botão</span><input v-model="section.settings.secondary_cta_url" type="text" placeholder="/trajetoria"></label>
                <label><span>Selo principal</span><input v-model="section.settings.badge_title" type="text" placeholder="15+ anos"></label>
                <label><span>Complemento do selo</span><input v-model="section.settings.badge_subtitle" type="text" placeholder="construindo"></label>
                <label><span>Título sobre a foto</span><input v-model="section.settings.photo_caption_title" type="text" placeholder="Do código à estratégia."></label>
                <label><span>Texto sobre a foto</span><input v-model="section.settings.photo_caption_text" type="text" placeholder="Produto, engenharia, pessoas e operação."></label>
              </div>
            </div>

            <details class="builder-details appearance-details">
              <summary>Aparência da seção</summary>
              <div class="form-grid three-columns builder-details-content">
                <label v-if="visualPresetOptions(section).length" class="span-two"><span>Estilo visual</span><select :value="visualPreset(section)" @change="changeVisualPreset(section, $event)"><option v-for="preset in visualPresetOptions(section)" :key="preset.value" :value="preset.value">{{ preset.label }}</option></select><small>{{ visualPresetDescription(section) }}</small></label>
                <label><span>Tema</span><select v-model="section.theme"><option value="default">Padrão FBSantos</option><option value="light">Claro</option><option value="dark">Escuro</option><option value="brand">Azul FBSantos</option><option value="custom">Personalizado</option></select></label>
                <label><span>Largura</span><select v-model="section.width_mode"><option value="normal">Normal</option><option value="wide">Ampla</option><option value="full">Tela inteira</option></select></label>
                <div class="theme-recommendation"><strong>Recomendado</strong><span>Use “Padrão FBSantos” salvo quando quiser que o design acompanhe automaticamente a identidade do site.</span></div>
                <template v-if="section.theme==='custom'">
                  <label><span>Fundo</span><input v-model="section.background_color" type="color"></label>
                  <label><span>Texto</span><input v-model="section.text_color" type="color"></label>
                  <label><span>Destaque</span><input v-model="section.accent_color" type="color"></label>
                </template>
                <label><span>Espaço superior</span><select v-model="section.spacing_top"><option v-for="v in ['none','small','medium','large','xlarge']" :key="v" :value="v">{{ v }}</option></select></label>
                <label><span>Espaço inferior</span><select v-model="section.spacing_bottom"><option v-for="v in ['none','small','medium','large','xlarge']" :key="v" :value="v">{{ v }}</option></select></label>
              </div>
            </details>

            <div v-if="supportsEntitySource(section)" class="builder-items-block">
              <div class="builder-subheading"><div><strong>Conteúdo conectado</strong><span>Escolha se a seção usa todos os registros publicados ou uma seleção própria.</span></div><select :value="sourceMode(section)" @change="changeSourceMode(section, $event)"><option value="selected">Selecionar itens</option><option v-if="section.section_type!=='project-feature'" value="all">Usar todos</option></select></div>
              <template v-if="sourceMode(section)==='selected'">
                <div v-for="(item,itemIndex) in section.items" :key="item.id || itemIndex" class="builder-connected-item">
                  <span class="item-order">{{ itemIndex+1 }}</span>
                  <select v-model.number="item.entity_id" @change="resetProjectFeatureMedia(section)"><option :value="undefined">Selecione…</option><option v-for="option in entityOptions[entityTypeFor(section)!]" :key="option.id" :value="option.id">{{ option.label }}{{ option.meta ? ` · ${option.meta}` : '' }}</option></select>
                  <div class="inline-actions"><button type="button" @click="moveItem(section,itemIndex,-1)">↑</button><button type="button" @click="moveItem(section,itemIndex,1)">↓</button><button class="danger-text" type="button" @click="removeItem(section,itemIndex)">Remover</button></div>
                </div>
                <button v-if="section.section_type !== 'project-feature' || !section.items.length" class="secondary-button compact" type="button" @click="addEntityItem(section)">+ Adicionar {{ section.section_type==='project-feature' ? 'projeto' : 'item' }}</button>
              </template>

              <div v-if="section.section_type === 'project-feature' && sourceMode(section) === 'selected' && selectedProjectId(section)" class="builder-items-block project-feature-media-settings">
                <div class="builder-subheading">
                  <div><strong>Telas da vitrine</strong><span>Escolha as três imagens do projeto que terão destaque na Home. A ordem abaixo corresponde à ordem visual da vitrine.</span></div>
                </div>
                <div v-if="projectFeatureMedia(section).length" class="project-feature-media-grid">
                  <div v-for="slotIndex in 3" :key="slotIndex" class="project-feature-media-slot">
                    <strong>Tela {{ slotIndex }}</strong>
                    <div class="project-feature-media-preview">
                      <img v-if="featureMediaById(section, slotIndex - 1)" :src="mediaPreviewUrl(featureMediaById(section, slotIndex - 1)?.media_url || '')" :alt="featureMediaById(section, slotIndex - 1)?.alt_text || `Tela ${slotIndex}`">
                      <span v-else>Seleção automática da galeria</span>
                    </div>
                    <select :value="featureMediaId(section, slotIndex - 1)" @change="setFeatureMediaId(section, slotIndex - 1, $event)">
                      <option :value="0">Automática</option>
                      <option v-for="media in projectFeatureMedia(section)" :key="media.id" :value="media.id" :disabled="featureMediaUsedInOtherSlot(section, media.id, slotIndex - 1)">{{ media.caption || media.alt_text || `Imagem #${media.id}` }}</option>
                    </select>
                  </div>
                </div>
                <p v-else class="project-feature-media-note">Este projeto ainda não possui imagens visíveis na galeria. Cadastre as telas no case e elas aparecerão aqui para seleção.</p>
                <p v-if="projectFeatureMedia(section).length" class="project-feature-media-note">Deixe uma posição em “Automática” para o site completar com a próxima imagem disponível da galeria, sem repetir as já escolhidas.</p>
              </div>
            </div>

            <div v-else-if="manualItemSections.has(section.section_type) && !isProjectBoundSection(section)" class="builder-items-block">
              <div class="builder-subheading"><div><strong>Itens da seção</strong><span>Use para indicadores, cards e pequenos blocos internos.</span></div><button class="secondary-button compact" type="button" @click="addManualItem(section)">+ Adicionar item</button></div>
              <div v-for="(item,itemIndex) in section.items" :key="item.id || itemIndex" class="builder-manual-item">
                <div class="builder-manual-item-head"><strong>Item {{ itemIndex+1 }}</strong><div class="inline-actions"><button type="button" @click="moveItem(section,itemIndex,-1)">↑</button><button type="button" @click="moveItem(section,itemIndex,1)">↓</button><button class="danger-text" type="button" @click="removeItem(section,itemIndex)">Remover</button></div></div>
                <div v-if="section.section_type === 'gallery'" class="form-grid two-columns">
                  <div class="span-two"><label class="field-label">Imagem</label><ImageUploadField v-model="item.image_url" :scope="`page-${pageId}-gallery-${section.id || index}-${item.id || itemIndex}`" preset="free" /></div>
                  <label><span>Texto alternativo</span><input v-model="item.image_alt" type="text"></label>
                  <label><span>Legenda</span><input v-model="item.title" type="text"></label>
                </div>
                <div v-else-if="section.section_type === 'links'" class="form-grid two-columns">
                  <label><span>Rótulo</span><input v-model="item.title" type="text" placeholder="Ex.: Site oficial"></label>
                  <label><span>Tipo</span><input v-model="item.value_label" type="text" placeholder="Ex.: site, Play Store"></label>
                  <label class="span-two"><span>URL</span><input v-model="item.url" type="url" placeholder="https://..."></label>
                </div>
                <div v-else-if="section.section_type === 'video'" class="form-grid two-columns">
                  <label><span>Título do vídeo</span><input v-model="item.title" type="text" placeholder="Ex.: Apresentação do projeto"></label>
                  <label><span>URL do YouTube</span><input v-model="item.url" type="url" placeholder="https://www.youtube.com/watch?v=..."></label>
                  <label class="span-two"><span>Legenda / contexto</span><RichTextEditor v-model="item.body" /></label>
                </div>
                <div v-else-if="section.section_type === 'cards'" class="form-grid two-columns">
                  <label><span>Título do card</span><input v-model="item.title" type="text" placeholder="Ex.: GitHub"></label>
                  <label><span>Conteúdo do card</span><select :value="cardContentMode(item)" @change="setCardContentMode(item, ($event.target as HTMLSelectElement).value as 'text' | 'image')"><option value="text">Texto</option><option value="image">Imagem</option></select><small>O card usa texto ou imagem, nunca ambos. Trocar a opção remove o conteúdo anterior.</small></label>
                  <label class="span-two"><span>Marcador (opcional)</span><input v-model="item.value_label" type="text" placeholder="Ex.: 01"><small>Nos cards de texto, sem marcador informado, a numeração é automática.</small></label>
                  <div v-if="cardContentMode(item) === 'image'" class="span-two">
                    <label class="field-label">Imagem do card</label>
                    <ImageUploadField v-model="item.image_url" :scope="`page-${pageId}-card-${section.id || index}-${item.id || itemIndex}`" preset="free" />
                    <label><span>Texto alternativo da imagem</span><input v-model="item.image_alt" type="text" placeholder="Descreva a imagem para leitores de tela"></label>
                  </div>
                  <div v-else class="span-two"><label class="field-label">Texto do card</label><RichTextEditor v-model="item.body" /></div>
                  <label class="span-two"><span>Link do card (opcional)</span><input v-model="item.url" type="text" placeholder="/projetos ou https://github.com/..."><small>Use / para páginas internas. URLs externas com https:// abrem em uma nova aba. O card inteiro será clicável.</small></label>
                </div>
                <div v-else class="form-grid two-columns"><label><span>Valor/destaque</span><input v-model="item.value_label" type="text"></label><label><span>Título</span><input v-model="item.title" type="text"></label><label class="span-two"><span>Texto</span><RichTextEditor v-model="item.body" /></label></div>
              </div>
            </div>

            <div v-else-if="isProjectBoundSection(section)" class="builder-source-note"><strong>Fonte automática do case</strong><p>Esta seção usa o projeto vinculado à página e os dados relacionados cadastrados nele. Você controla aqui a posição, textos editoriais e aparência.</p></div>
          </div>
        </article>
      </section>

      <div class="sticky-save-bar"><span>{{ sections.length }} {{ sections.length===1 ? 'seção' : 'seções' }}</span><div><button class="secondary-button" type="button" @click="router.push('/')">Voltar</button><button class="primary-button" type="button" :disabled="saving" @click="save">{{ saving ? 'Salvando…' : 'Salvar página' }}</button></div></div>
    </template>
  </div>
</template>
