<script setup lang="ts">
import type { Experience, PageBuilderSection, Project, ProjectMedia, Publication, Technology } from '~/types/content'

const props = defineProps<{ section: PageBuilderSection }>()
const mediaUrl = useMediaUrl()

const entities = computed(() => props.section.items.map(item => item.entity).filter(Boolean) as Record<string, any>[])
const firstEntity = computed(() => entities.value[0] || null)
const project = computed(() => firstEntity.value as Project | null)
const sectionSettings = computed<Record<string, any>>(() => props.section.settings || {})

const safeColor = (value?: string | null) => value && /^#[0-9a-f]{6}$/i.test(value) ? value : undefined
const customStyle = computed(() => props.section.theme === 'custom' ? {
  '--section-bg': safeColor(props.section.background_color),
  '--section-text': safeColor(props.section.text_color),
  '--section-accent': safeColor(props.section.accent_color)
} : {})

const classes = computed(() => [
  'dynamic-section',
  `dynamic-section--${props.section.section_type}`,
  `section-theme--${props.section.theme || 'default'}`,
  `section-width--${props.section.width_mode || 'normal'}`,
  `section-space-top--${props.section.spacing_top || 'large'}`,
  `section-space-bottom--${props.section.spacing_bottom || 'large'}`
])

const nested = () => (firstEntity.value?.items || []) as Record<string, any>[]

const hasContent = (...values: unknown[]) => values.some((value) => {
  if (typeof value === 'number') return true
  if (typeof value !== 'string') return false
  const plain = value
    .replace(/<br\s*\/?\s*>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .trim()
  return plain.length > 0
})

const projectMedia = computed<ProjectMedia[]>(() => {
  if (props.section.section_type !== 'gallery') return []
  if (firstEntity.value?.items) {
    return (nested() as ProjectMedia[]).filter(item => hasContent(item.media_url))
  }
  return props.section.items.filter(item => hasContent(item.image_url)).map((item, index) => ({
    id: item.id || index + 1,
    media_url: item.image_url || '',
    alt_text: item.image_alt || item.title || '',
    caption: item.title || null,
    kind: 'image'
  })) as ProjectMedia[]
})

const resourceLinks = computed(() => {
  const links = firstEntity.value?.items
    ? nested()
    : props.section.items.map(item => ({
        id: item.id || item.sort_order,
        label: item.title || 'Abrir recurso',
        url: item.url,
        kind: item.value_label || 'external'
      }))

  return links.filter(link => hasContent(link.url))
})

const relatedContexts = computed<Record<string, any>[]>(() => {
  const source = firstEntity.value?.items ? nested() : entities.value
  return source.filter(Boolean).map(entity => ({
    ...entity,
    _relation_type: entity._relation_type || (props.section.section_type === 'related-experiences' ? 'experience' : '')
  }))
})

const sectionItemsHaveContent = computed(() => props.section.items.some(item =>
  Boolean(item.entity) || hasContent(item.eyebrow, item.title, item.subtitle, item.body, item.value_label, item.url, item.image_url)
))

const projectStoryHasContent = computed(() => {
  const current = project.value
  if (!current) return false
  if (hasContent(current.context, current.participation, current.solution, current.architecture, current.challenges, current.results)) return true
  return (current.sections || []).some(section => hasContent(section.eyebrow, section.title, section.body))
})

const shouldRenderSection = computed(() => {
  const type = props.section.section_type
  const sectionHasContent = hasContent(
    props.section.eyebrow,
    props.section.title,
    props.section.accent_title,
    props.section.lead,
    props.section.body,
    props.section.image_url,
    props.section.cta_label
  )

  if (type === 'gallery') return projectMedia.value.length > 0
  if (type === 'links') return resourceLinks.value.length > 0
  if (['related-contexts', 'related-experiences'].includes(type)) return relatedContexts.value.length > 0
  if (type === 'project-story') return projectStoryHasContent.value
  if (['project-hero', 'project-feature'].includes(type)) return Boolean(project.value)
  if (['projects-grid', 'timeline', 'education', 'certifications', 'evaluation-boards', 'publications', 'logo-cloud'].includes(type)) return entities.value.length > 0
  if (type === 'technologies') return (firstEntity.value?.items ? nested() : entities.value).length > 0
  if (type === 'video') return props.section.items.some(item => hasContent(item.url))
  if (['stats', 'cards'].includes(type)) return sectionItemsHaveContent.value
  if (type === 'contact') return true

  return sectionHasContent || sectionItemsHaveContent.value || entities.value.length > 0
})

const heroVariant = computed(() => String(sectionSettings.value.variant || 'profile-card'))
const heroSecondaryLabel = computed(() => String(sectionSettings.value.secondary_cta_label || 'Conhecer trajetória'))
const heroSecondaryUrl = computed(() => String(sectionSettings.value.secondary_cta_url || '/trajetoria'))
const heroBadgeTitle = computed(() => String(sectionSettings.value.badge_title || '15+ anos'))
const heroBadgeSubtitle = computed(() => String(sectionSettings.value.badge_subtitle || 'construindo'))
const heroCaptionTitle = computed(() => String(sectionSettings.value.photo_caption_title || 'Do código à estratégia.'))
const heroCaptionText = computed(() => String(sectionSettings.value.photo_caption_text || 'Produto, engenharia, pessoas e operação.'))

const projectScreens = computed<ProjectMedia[]>(() => {
  const images = (project.value?.media || []).filter(item => String(item.kind || '').toLowerCase() === 'image' && hasContent(item.media_url))
  const configured = Array.isArray(sectionSettings.value.featured_media_ids)
    ? sectionSettings.value.featured_media_ids.slice(0, 3).map((id: unknown) => Number(id || 0))
    : []
  const selectedIds = new Set<number>()
  const selected: ProjectMedia[] = []

  for (let index = 0; index < 3; index += 1) {
    const configuredId = configured[index] || 0
    let candidate = configuredId
      ? images.find(item => Number(item.id) === configuredId && !selectedIds.has(Number(item.id)))
      : undefined

    if (!candidate) {
      const reservedForLater = new Set(configured.slice(index + 1).filter(Boolean))
      candidate = images.find(item => !selectedIds.has(Number(item.id)) && !reservedForLater.has(Number(item.id)))
        || images.find(item => !selectedIds.has(Number(item.id)))
    }

    if (!candidate) break
    selectedIds.add(Number(candidate.id))
    selected.push(candidate)
  }

  return selected
})

const projectFeatureVariant = computed(() => {
  const configured = String(sectionSettings.value.variant || '')
  if (configured) return configured
  return projectScreens.value.length >= 3 ? 'device-showcase' : 'cover'
})

const cardsVariant = computed(() => String(sectionSettings.value.variant || (props.section.internal_name?.toLowerCase().includes('fundamento') ? 'pillars' : 'cards')))
type FreeCard = PageBuilderSection['items'][number]

function cardContentMode(item: FreeCard): 'text' | 'image' {
  if (item.settings?.content_mode === 'image') return 'image'
  if (item.settings?.content_mode === 'text') return 'text'
  return item.image_url && !hasContent(item.body) ? 'image' : 'text'
}

function safeCardUrl(item: FreeCard): string | undefined {
  const value = String(item.url || '').trim()
  if (!value || /[\u0000-\u001f\u007f\\]/.test(value)) return undefined
  if ((value.startsWith('/') && !value.startsWith('//')) || value.startsWith('#')) return value
  try {
    const url = new URL(value)
    return ['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol) ? value : undefined
  } catch {
    return undefined
  }
}

function isExternalCardUrl(item: FreeCard): boolean {
  return /^(?:https?:\/\/|mailto:|tel:)/i.test(safeCardUrl(item) || '')
}

function opensCardInNewTab(item: FreeCard): boolean {
  return /^https?:\/\//i.test(safeCardUrl(item) || '')
}

function cardLinkLabel(item: FreeCard): string {
  return `Abrir ${item.title || item.image_alt || 'card'}${opensCardInNewTab(item) ? ' (nova aba)' : ''}`
}
const technologyVariant = computed(() => String(sectionSettings.value.variant || 'groups'))

const textImageVariant = computed(() => String(sectionSettings.value.variant || 'image-left'))
const educationVariant = computed(() => String(sectionSettings.value.variant || 'cards'))

function localDate(value?: string | null) {
  if (!value) return null
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (!match) return null
  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
}

function yearFromDate(value?: string | null) {
  const date = localDate(value)
  return date ? String(date.getFullYear()) : ''
}

function formatDatePtBr(value?: string | null) {
  const date = localDate(value)
  if (!date) return ''
  return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }).format(date)
}

function formatMonthYear(value?: string | null) {
  const date = localDate(value)
  if (!date) return ''
  return new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(date)
}

function educationPeriod(entity: Record<string, any>) {
  if (educationVariant.value === 'detailed') {
    const start = formatMonthYear(entity.start_date)
    const end = formatMonthYear(entity.end_date)
    if (start && end) return `${start} — ${end}`
    if (start) return `${start} — atual`
    return end
  }
  const start = yearFromDate(entity.start_date)
  const end = yearFromDate(entity.end_date)
  if (start && end) return `${start} — ${end}`
  if (start) return `${start} — atual`
  return end
}

function workloadLabel(value?: string | number | null) {
  if (value === null || value === undefined || value === '') return ''
  const number = Number(value)
  if (!Number.isFinite(number)) return ''
  const formatted = Number.isInteger(number) ? String(number) : String(number).replace('.', ',')
  return `${formatted} ${number === 1 ? 'hora' : 'horas'}`
}

function relatedContextTypeLabel(entity: Record<string, any>) {
  if (entity._relation_label) return String(entity._relation_label)
  if (entity._relation_type === 'education') return 'Formação acadêmica'
  if (entity._relation_type === 'certification') return 'Curso / certificação'
  return 'Experiência profissional'
}

function relatedContextLogo(entity: Record<string, any>) {
  return entity._relation_type === 'experience' ? entity.organization_image_url : entity.logo_url
}

function relatedContextOrganization(entity: Record<string, any>) {
  if (entity._relation_type === 'education') return entity.institution || ''
  if (entity._relation_type === 'certification') return entity.issuer || ''
  return entity.organization || ''
}

function relatedContextTitle(entity: Record<string, any>) {
  if (entity._relation_type === 'education') return entity.course || ''
  if (entity._relation_type === 'certification') return entity.name || ''
  return entity.role_title || ''
}

function relatedContextPeriod(entity: Record<string, any>) {
  if (entity._relation_type === 'education') return educationPeriod(entity)
  if (entity._relation_type === 'certification') return formatMonthYear(entity.issued_date)
  const start = formatMonthYear(entity.start_date)
  const end = entity.is_current ? 'atual' : formatMonthYear(entity.end_date)
  if (start && end) return `${start} — ${end}`
  return start || end || ''
}

function publicationHasMedia(entity: Publication) {
  return Boolean(entity.media?.length)
}

function youtubeEmbedUrl(value?: string | null) {
  if (!value) return ''
  if (/^https:\/\/www\.youtube-nocookie\.com\/embed\/[A-Za-z0-9_-]+/i.test(value)) return value
  if (/^https:\/\/www\.youtube\.com\/embed\/[A-Za-z0-9_-]+/i.test(value)) return value.replace('www.youtube.com', 'www.youtube-nocookie.com')
  const match = value.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/i)
  return match ? `https://www.youtube-nocookie.com/embed/${match[1]}` : ''
}

function mediaEmbedUrl(media: Record<string, any>) {
  return youtubeEmbedUrl(String(media.embed_url || media.media_url || media.url || ''))
}

function isYoutubeResource(link: Record<string, any>) {
  return String(link.kind || '').toLowerCase() === 'youtube' || Boolean(youtubeEmbedUrl(String(link.url || '')))
}

const technologyGroups = computed(() => {
  const source = firstEntity.value?.items ? nested() : entities.value
  const groups = new Map<string, Record<string, any>[]>()
  for (const entity of source) {
    const category = String(entity.category || 'Outras tecnologias')
    const current = groups.get(category) || []
    current.push(entity)
    groups.set(category, current)
  }
  return Array.from(groups.entries())
})

function projectHref(entity: Record<string, any>) {
  return `/projetos/${entity.slug}`
}
</script>

<template>
  <section v-if="shouldRenderSection" :class="classes" :style="customStyle">
    <div class="dynamic-section-inner" :class="`dynamic-container--${section.width_mode || 'normal'}`">
      <template v-if="section.section_type === 'hero'">
        <div class="hero hero-with-photo dynamic-hero-reset">
          <div class="container hero-grid">
            <div class="hero-copy-column">
              <span v-if="section.eyebrow" class="kicker">{{ section.eyebrow }}</span>
              <h1 class="hero-title-static">
                <span class="hero-title-main">{{ section.title }}</span>
                <span v-if="section.accent_title" class="hero-title-accent">{{ section.accent_title }}</span>
              </h1>
              <RichText v-if="section.lead" as="p" class="hero-copy" :value="section.lead" />
              <div v-if="section.cta_label || heroSecondaryLabel" class="hero-actions">
                <NuxtLink v-if="section.cta_label" class="btn btn-primary" :to="section.cta_url || '#'">{{ section.cta_label }} <span aria-hidden="true">→</span></NuxtLink>
                <NuxtLink v-if="heroSecondaryLabel" class="btn btn-secondary" :to="heroSecondaryUrl">{{ heroSecondaryLabel }}</NuxtLink>
              </div>
            </div>

            <div v-if="section.image_url" class="hero-visual hero-photo-visual">
              <div v-if="heroVariant === 'profile-card'" class="hero-orbit" />
              <div class="hero-photo-card">
                <img :src="mediaUrl(section.image_url)" :alt="section.image_alt || section.title || ''" width="625" height="815">
                <div v-if="heroVariant === 'profile-card'" class="hero-photo-caption">
                  <img src="/brand/fbsantos-icon-color.svg" alt="" width="42" height="42">
                  <div><strong>{{ heroCaptionTitle }}</strong><span>{{ heroCaptionText }}</span></div>
                </div>
              </div>
              <div v-if="heroVariant === 'profile-card'" class="hero-badge">{{ heroBadgeTitle }}<br>{{ heroBadgeSubtitle }}</div>
            </div>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'page-hero'">
        <div class="container page-hero dynamic-page-hero" :class="{ 'has-media': section.image_url }">
          <div class="dynamic-page-hero-grid">
            <div class="dynamic-page-hero-copy">
              <span v-if="section.eyebrow" class="kicker">{{ section.eyebrow }}</span>
              <h1>{{ section.title }} <span v-if="section.accent_title" class="dynamic-accent">{{ section.accent_title }}</span></h1>
              <RichText v-if="section.lead" as="p" :value="section.lead" />
              <NuxtLink v-if="section.cta_label" class="btn btn-primary dynamic-page-hero-cta" :to="section.cta_url || '#'">{{ section.cta_label }} <span aria-hidden="true">→</span></NuxtLink>
            </div>

            <figure v-if="section.image_url" class="dynamic-page-hero-media">
              <img :src="mediaUrl(section.image_url)" :alt="section.image_alt || section.title || ''">
            </figure>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'text'">
        <div class="container dynamic-copy-block">
          <SectionHeading :eyebrow="section.eyebrow || undefined" :title="section.title || ''" :description="section.lead || undefined" />
          <RichText v-if="section.body" as="div" class="dynamic-rich-body" :value="section.body" />
          <NuxtLink v-if="section.cta_label" class="btn btn-primary" :to="section.cta_url || '#'">{{ section.cta_label }} →</NuxtLink>
        </div>
      </template>

      <template v-else-if="section.section_type === 'cta'">
        <div class="container">
          <div class="contact-panel contact-panel-strong dynamic-contact-panel">
            <div class="dynamic-cta-copy">
              <p v-if="section.eyebrow" class="eyebrow">{{ section.eyebrow }}</p>
              <h2>{{ section.title }}</h2>
              <RichText v-if="section.lead || section.body" as="p" :value="section.lead || section.body" />
            </div>
            <NuxtLink v-if="section.cta_label" class="btn btn-primary dynamic-cta-button" :to="section.cta_url || '#'"><span>{{ section.cta_label }}</span><span class="dynamic-cta-arrow" aria-hidden="true">→</span></NuxtLink>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'text-image'">
        <div class="container dynamic-split dynamic-editorial-split" :class="`is-${textImageVariant}`">
          <figure v-if="section.image_url" class="dynamic-image-card dynamic-editorial-image"><img :src="mediaUrl(section.image_url)" :alt="section.image_alt || section.title || ''" loading="lazy"></figure>
          <div class="dynamic-editorial-copy">
            <SectionHeading :eyebrow="section.eyebrow || undefined" :title="section.title || ''" :description="section.lead || undefined" />
            <RichText v-if="section.body" as="div" class="dynamic-rich-body" :value="section.body" />
            <NuxtLink v-if="section.cta_label" class="text-link" :to="section.cta_url || '#'">{{ section.cta_label }} →</NuxtLink>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'logo-cloud'">
        <div class="container">
          <p v-if="section.lead" class="proof-label">{{ section.lead }}</p>
          <div class="logo-cloud">
            <a v-for="entity in entities" :key="entity.id" class="logo-cloud-item" :href="entity.website_url || undefined" :target="entity.website_url ? '_blank' : undefined" rel="noopener">
              <img v-if="entity.image_url" :src="mediaUrl(entity.image_url)" :alt="entity.name" loading="lazy">
              <span v-else>{{ entity.name }}</span>
            </a>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'stats'">
        <div class="container stat-grid">
          <article v-for="item in section.items" :key="item.id || item.sort_order" class="stat"><strong>{{ item.value_label }}</strong><RichText as="p" :value="item.body" /></article>
        </div>
      </template>

      <template v-else-if="section.section_type === 'project-feature' && project">
        <div class="container product-spotlight">
          <div v-if="projectFeatureVariant === 'device-showcase' && (project.cover_image_url || projectScreens.length)" class="product-spotlight-media">
            <div v-if="project.cover_image_url" class="product-spotlight-brand"><img :src="mediaUrl(project.cover_image_url)" :alt="project.name" loading="lazy"></div>
            <div v-if="projectScreens.length" class="product-device-grid" :aria-label="`Telas do projeto ${project.name}`">
              <figure v-for="(screen, index) in projectScreens" :key="screen.id" class="product-device" :class="{ 'is-featured': index === 1 }">
                <img :src="mediaUrl(screen.media_url)" :alt="screen.alt_text || project.name" loading="lazy">
              </figure>
            </div>
          </div>
          <figure v-else-if="project.cover_image_url" class="product-feature-media"><img :src="mediaUrl(project.cover_image_url)" :alt="project.name" loading="lazy"></figure>

          <div class="product-spotlight-copy">
            <span v-if="section.eyebrow" class="eyebrow">{{ section.eyebrow }}</span>
            <h2>{{ section.title || project.name }}</h2>
            <RichText v-if="project.context" as="p" :value="project.context" />
            <RichText v-if="project.participation" as="p" :value="project.participation" />
            <div v-if="project.technologies?.length" class="spotlight-points">
              <span v-for="tech in project.technologies.slice(0, 5)" :key="tech.id">{{ tech.name }}</span>
            </div>
            <div class="product-spotlight-actions">
              <NuxtLink class="btn btn-primary" :to="projectHref(project)">{{ section.cta_label || 'Conhecer o projeto' }} →</NuxtLink>
              <a v-for="link in project.links || []" :key="link.id" class="btn btn-secondary product-official-link" :href="link.url" target="_blank" rel="noopener noreferrer">{{ link.label }} ↗</a>
            </div>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'projects-grid'">
        <div class="container">
          <SectionHeading v-if="section.title || section.eyebrow" :eyebrow="section.eyebrow || undefined" :title="section.title || ''" :description="section.lead || undefined" />
          <div class="project-grid"><ProjectCard v-for="entity in entities" :key="entity.id" :project="entity as Project" /></div>
          <div v-if="section.cta_label" class="section-action"><NuxtLink class="text-link" :to="section.cta_url || '/projetos'">{{ section.cta_label }} →</NuxtLink></div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'timeline'">
        <div class="container">
          <SectionHeading v-if="section.title || section.eyebrow" :eyebrow="section.eyebrow || undefined" :title="section.title || ''" :description="section.lead || undefined" />
          <ExperienceTimeline :items="entities as Experience[]" :compact="section.internal_name?.toLowerCase().includes('resum')" />
          <div v-if="section.cta_label" class="section-action"><NuxtLink class="text-link" :to="section.cta_url || '/trajetoria'">{{ section.cta_label }} →</NuxtLink></div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'cards'">
        <div class="container">
          <SectionHeading v-if="section.title || section.eyebrow" :eyebrow="section.eyebrow || undefined" :title="section.title || ''" :description="section.lead || undefined" />
          <div v-if="cardsVariant === 'pillars'" class="engineering-pillars dynamic-engineering-pillars">
            <article v-for="(item, index) in section.items" :key="item.id || item.sort_order" class="free-card" :class="{ 'free-card--linked': safeCardUrl(item), 'free-card--image': cardContentMode(item) === 'image' }">
              <span v-if="cardContentMode(item) === 'text' || item.value_label">{{ item.value_label || String(index + 1).padStart(2, '0') }}</span>
              <h3 v-if="item.title">{{ item.title }}</h3>
              <figure v-if="cardContentMode(item) === 'image' && item.image_url" class="free-card-image"><img :src="mediaUrl(item.image_url)" :alt="item.image_alt || item.title || ''" loading="lazy" decoding="async"></figure>
              <RichText v-else-if="cardContentMode(item) === 'text'" as="p" :value="item.body" />
              <NuxtLink v-if="safeCardUrl(item)" class="free-card-action" :to="safeCardUrl(item)" :external="isExternalCardUrl(item)" :target="opensCardInNewTab(item) ? '_blank' : undefined" :rel="opensCardInNewTab(item) ? 'noopener noreferrer' : undefined" :aria-label="cardLinkLabel(item)"><span class="free-card-link-indicator" aria-hidden="true">↗</span></NuxtLink>
            </article>
          </div>
          <div v-else class="capability-grid">
            <article v-for="(item, index) in section.items" :key="item.id || item.sort_order" class="capability-card free-card" :class="{ 'free-card--linked': safeCardUrl(item), 'free-card--image': cardContentMode(item) === 'image' }">
              <span v-if="cardContentMode(item) === 'text' || item.value_label" class="number">{{ item.value_label || `0${index + 1}` }}</span>
              <h3 v-if="item.title">{{ item.title }}</h3>
              <figure v-if="cardContentMode(item) === 'image' && item.image_url" class="free-card-image"><img :src="mediaUrl(item.image_url)" :alt="item.image_alt || item.title || ''" loading="lazy" decoding="async"></figure>
              <RichText v-else-if="cardContentMode(item) === 'text'" as="p" :value="item.body" />
              <NuxtLink v-if="safeCardUrl(item)" class="free-card-action" :to="safeCardUrl(item)" :external="isExternalCardUrl(item)" :target="opensCardInNewTab(item) ? '_blank' : undefined" :rel="opensCardInNewTab(item) ? 'noopener noreferrer' : undefined" :aria-label="cardLinkLabel(item)"><span class="free-card-link-indicator" aria-hidden="true">↗</span></NuxtLink>
            </article>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'technologies'">
        <div class="container">
          <SectionHeading v-if="section.title || section.eyebrow" :eyebrow="section.eyebrow || undefined" :title="section.title || ''" :description="section.lead || undefined" />
          <div v-if="technologyVariant === 'groups'" class="tech-groups dynamic-tech-groups">
            <section v-for="[category, items] in technologyGroups" :key="category" class="tech-group"><h3>{{ category }}</h3><div class="tech-group-list"><span v-for="tech in items" :key="tech.id" class="tech-chip">{{ tech.name }}</span></div></section>
          </div>
          <div v-else class="tech-cloud"><span v-for="entity in (firstEntity?.items || entities)" :key="entity.id">{{ (entity as Technology).name }}</span></div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'education'">
        <div class="container">
          <SectionHeading v-if="section.title || section.eyebrow" :eyebrow="section.eyebrow || undefined" :title="section.title || ''" :description="section.lead || undefined" />
          <div class="education-grid" :class="`education-grid--${educationVariant}`">
            <article v-for="entity in entities" :key="entity.id" class="education-card">
              <div class="education-card-brand">
                <img v-if="entity.logo_url" class="education-card-logo" :src="mediaUrl(entity.logo_url)" :alt="entity.institution || entity.course || ''" loading="lazy">
                <span v-else class="education-card-monogram" aria-hidden="true">{{ (entity.institution || entity.course || 'F').slice(0, 1) }}</span>
              </div>
              <div class="education-card-body">
                <p class="eyebrow">{{ entity.institution || '' }}</p>
                <h3>{{ entity.course || entity.name || entity.title }}</h3>
                <div v-if="entity.degree_type || educationPeriod(entity) || entity.location" class="dynamic-info-meta">
                  <span v-if="entity.degree_type">{{ entity.degree_type }}</span>
                  <span v-if="educationPeriod(entity)">{{ educationPeriod(entity) }}</span>
                  <span v-if="entity.location">{{ entity.location }}</span>
                </div>
                <RichText v-if="entity.description || entity.summary" as="p" class="education-card-description" :value="entity.description || entity.summary" />
                <a v-if="entity.certificate_url" class="text-link education-certificate-link" :href="mediaUrl(entity.certificate_url)" target="_blank" rel="noopener noreferrer">Ver diploma / comprovante <span aria-hidden="true">↗</span></a>
              </div>
            </article>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'certifications'">
        <div class="container">
          <SectionHeading v-if="section.title || section.eyebrow" :eyebrow="section.eyebrow || undefined" :title="section.title || ''" :description="section.lead || undefined" />
          <div class="info-grid dynamic-info-grid certification-grid">
            <article v-for="entity in entities" :key="entity.id" class="info-card dynamic-info-card certification-card-dynamic">
              <div v-if="entity.logo_url" class="dynamic-info-logo-stage"><img class="info-card-logo" :src="mediaUrl(entity.logo_url)" :alt="entity.issuer || entity.name || ''" loading="lazy"></div>
              <p class="eyebrow">{{ entity.issuer || '' }}</p>
              <h3>{{ entity.name || entity.title }}</h3>
              <div v-if="entity.issued_date || entity.workload_hours" class="dynamic-info-meta certification-meta">
                <span v-if="entity.issued_date">{{ formatMonthYear(entity.issued_date) }}</span>
                <span v-if="entity.workload_hours" class="certification-workload">Carga horária · {{ workloadLabel(entity.workload_hours) }}</span>
              </div>
              <RichText v-if="entity.description || entity.summary" as="p" :value="entity.description || entity.summary" />
              <a v-if="entity.credential_url" class="text-link credential-link" :href="mediaUrl(entity.credential_url)" target="_blank" rel="noopener noreferrer">Ver certificado <span aria-hidden="true">↗</span></a>
            </article>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'evaluation-boards'">
        <div class="container">
          <SectionHeading v-if="section.title || section.eyebrow" :eyebrow="section.eyebrow || undefined" :title="section.title || ''" :description="section.lead || undefined" />
          <div class="academic-board-grid">
            <article v-for="entity in entities" :key="entity.id" class="academic-board-card dynamic-academic-board-card">
              <div class="academic-board-identity">
                <img v-if="entity.logo_url" :src="mediaUrl(entity.logo_url)" :alt="entity.institution || ''" loading="lazy">
                <div>
                  <span class="academic-board-institution">{{ entity.institution }}</span>
                  <time v-if="entity.event_date" :datetime="entity.event_date">{{ formatDatePtBr(entity.event_date) }}</time>
                </div>
              </div>
              <h3>{{ entity.title }}</h3>
              <RichText v-if="entity.description" as="p" class="academic-board-description" :value="entity.description" />
            </article>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'publications'">
        <div class="container">
          <SectionHeading v-if="section.title || section.eyebrow" :eyebrow="section.eyebrow || undefined" :title="section.title || ''" :description="section.lead || undefined" />
          <div class="presentation-list dynamic-presentation-list">
            <article v-for="entity in (entities as Publication[])" :key="entity.id" class="presentation-feature">
              <div class="presentation-copy">
                <div class="presentation-meta"><span v-if="entity.kind">{{ entity.kind }}</span><time v-if="entity.published_date" :datetime="entity.published_date">{{ formatDatePtBr(entity.published_date) }}</time><span v-if="entity.location">{{ entity.location }}</span></div>
                <h2>{{ entity.title }}</h2>
                <p v-if="entity.event_name" class="presentation-event">{{ entity.event_name }}</p>
                <p v-if="entity.organization" class="presentation-organization">{{ entity.organization }}</p>
                <RichText v-if="entity.summary" as="p" class="presentation-summary" :value="entity.summary" />
                <RichText v-if="entity.description" as="div" class="presentation-description" :value="entity.description" />
              </div>
              <div v-if="entity.media?.length" class="presentation-videos" :class="{ 'has-multiple': entity.media.length > 1 }">
                <figure v-for="media in entity.media" :key="media.id" class="video-card dynamic-publication-video">
                  <div v-if="mediaEmbedUrl(media)" class="video-frame">
                    <iframe :src="mediaEmbedUrl(media)" :title="media.title || entity.title" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen />
                  </div>
                  <figcaption class="dynamic-video-caption">
                    <strong v-if="media.title">{{ media.title }}</strong>
                    <a v-if="media.media_url" class="text-link dynamic-video-reference" :href="mediaUrl(media.media_url)" target="_blank" rel="noopener noreferrer">Abrir referência <span aria-hidden="true">↗</span></a>
                  </figcaption>
                </figure>
              </div>
              <div v-if="entity.sections?.length" class="presentation-details">
                <div class="presentation-details-heading">
                  <span class="eyebrow">Conteúdo complementar</span>
                  <h3>Principais pontos</h3>
                </div>
                <div class="presentation-topic-grid">
                  <article v-for="detail in entity.sections" :key="detail.id" class="presentation-topic-card">
                    <p v-if="detail.eyebrow" class="eyebrow">{{ detail.eyebrow }}</p>
                    <h4>{{ detail.title }}</h4>
                    <RichText v-if="detail.body" as="div" :value="detail.body" />
                    <ul v-if="detail.items?.length">
                      <li v-for="item in detail.items" :key="item">{{ item }}</li>
                    </ul>
                  </article>
                </div>
              </div>
              <div v-if="entity.related_projects?.length" class="presentation-related">
                <div>
                  <span class="eyebrow">Cases relacionados</span>
                  <h3>Projetos conectados a esta publicação</h3>
                </div>
                <div class="presentation-related-grid">
                  <NuxtLink v-for="project in entity.related_projects" :key="project.id" class="presentation-related-project" :to="`/projetos/${project.slug}`">
                    <span>
                      <small v-if="project.relation_label">{{ project.relation_label }}</small>
                      <strong>{{ project.name }}</strong>
                      <span v-if="project.period_label">{{ project.period_label }}</span>
                    </span>
                    <span aria-hidden="true">→</span>
                  </NuxtLink>
                </div>
              </div>
              <a v-if="entity.external_url && !publicationHasMedia(entity)" class="text-link dynamic-publication-link" :href="entity.external_url" target="_blank" rel="noopener noreferrer">Abrir referência ↗</a>
            </article>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'video'">
        <div class="container">
          <SectionHeading v-if="section.title || section.eyebrow" :eyebrow="section.eyebrow || undefined" :title="section.title || 'Vídeos'" :description="section.lead || undefined" />
          <div class="presentation-videos dynamic-video-grid" :class="{ 'has-multiple': section.items.length > 1 }">
            <figure v-for="item in section.items" :key="item.id || item.sort_order" class="video-card">
              <div v-if="youtubeEmbedUrl(item.url)" class="video-frame"><iframe :src="youtubeEmbedUrl(item.url)" :title="item.title || section.title || 'Vídeo'" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen /></div>
              <figcaption v-if="item.title || item.body || item.url" class="dynamic-video-caption"><span><strong v-if="item.title">{{ item.title }}</strong><RichText v-if="item.body" as="span" :value="item.body" /></span><a v-if="item.url" class="text-link dynamic-video-reference" :href="item.url" target="_blank" rel="noopener noreferrer">Abrir referência <span aria-hidden="true">↗</span></a></figcaption>
            </figure>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'project-hero' && project">
        <div class="container project-hero-grid">
          <div><span class="kicker">{{ project.organization || 'Projeto' }}</span><h1>{{ project.name }}</h1><RichText as="p" :value="project.context" /><div class="chip-list"><span v-for="tech in project.technologies || []" :key="tech.id">{{ tech.name }}</span></div></div>
          <figure v-if="project.cover_image_url" class="project-hero-media"><img :src="mediaUrl(project.cover_image_url)" :alt="project.name"></figure>
        </div>
      </template>

      <template v-else-if="section.section_type === 'project-story' && project">
        <div class="container">
          <SectionHeading :eyebrow="section.eyebrow || undefined" :title="section.title || project.name" :description="section.lead || undefined" />
          <div class="project-story-grid">
            <article v-if="project.context"><h3>Problema / contexto</h3><RichText as="div" :value="project.context" /></article>
            <article v-if="project.participation"><h3>Minha participação</h3><RichText as="div" :value="project.participation" /></article>
            <article v-if="project.solution"><h3>Solução</h3><RichText as="div" :value="project.solution" /></article>
            <article v-if="project.architecture"><h3>Arquitetura</h3><RichText as="div" :value="project.architecture" /></article>
            <article v-if="project.challenges"><h3>Desafios</h3><RichText as="div" :value="project.challenges" /></article>
            <article v-if="project.results"><h3>Resultados</h3><RichText as="div" :value="project.results" /></article>
            <article v-for="extra in project.sections || []" :key="extra.id"><p v-if="extra.eyebrow" class="eyebrow">{{ extra.eyebrow }}</p><h3>{{ extra.title }}</h3><RichText v-if="extra.body" as="div" :value="extra.body" /></article>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'gallery'">
        <div class="container"><SectionHeading :eyebrow="section.eyebrow || undefined" :title="section.title || 'Galeria'" :description="section.lead || undefined" /><ProjectMediaGallery v-if="projectMedia.length" :media="projectMedia" :project-name="String(firstEntity?.project_name || section.internal_name || 'Projeto')" /></div>
      </template>

      <template v-else-if="section.section_type === 'links'">
        <div class="container">
          <SectionHeading :eyebrow="section.eyebrow || undefined" :title="section.title || 'Links e recursos'" />
          <div v-if="resourceLinks.some(isYoutubeResource)" class="project-video-grid" :class="{ 'has-multiple': resourceLinks.filter(isYoutubeResource).length > 1 }">
            <figure v-for="link in resourceLinks.filter(isYoutubeResource)" :key="`video-${link.id}`" class="project-video-card">
              <div class="video-frame"><iframe :src="youtubeEmbedUrl(link.url)" :title="link.label || 'Vídeo do projeto'" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen /></div>
              <figcaption><span>{{ link.label }}</span><a :href="link.url" target="_blank" rel="noopener noreferrer">Abrir no YouTube ↗</a></figcaption>
            </figure>
          </div>
          <div class="resource-links"><a v-for="link in resourceLinks.filter(link => !isYoutubeResource(link))" :key="link.id" :href="link.url" target="_blank" rel="noopener"><strong>{{ link.label }}</strong><span>{{ link.kind }} ↗</span></a></div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'related-contexts' || section.section_type === 'related-experiences'">
        <div class="container project-related-contexts">
          <SectionHeading :eyebrow="section.eyebrow || undefined" :title="section.title || 'Trajetória relacionada'" :description="section.lead || undefined" />
          <div class="related-context-grid">
            <article v-for="entity in relatedContexts" :key="`${entity._relation_type}-${entity.id}`" class="related-context-card">
              <div class="related-context-brand">
                <img v-if="relatedContextLogo(entity)" :src="mediaUrl(relatedContextLogo(entity))" :alt="relatedContextOrganization(entity) || relatedContextTitle(entity)" loading="lazy">
                <span v-else class="related-context-monogram" aria-hidden="true">{{ (relatedContextOrganization(entity) || relatedContextTitle(entity) || 'F').slice(0, 1) }}</span>
              </div>
              <div class="related-context-copy">
                <div class="related-context-topline">
                  <span class="related-context-type">{{ relatedContextTypeLabel(entity) }}</span>
                  <span v-if="relatedContextPeriod(entity)" class="related-context-period">{{ relatedContextPeriod(entity) }}</span>
                </div>
                <p v-if="relatedContextOrganization(entity)" class="related-context-organization">{{ relatedContextOrganization(entity) }}</p>
                <h3>{{ relatedContextTitle(entity) }}</h3>
                <div v-if="entity._relation_type === 'certification' && entity.workload_hours" class="dynamic-info-meta related-context-meta">
                  <span>Carga horária · {{ workloadLabel(entity.workload_hours) }}</span>
                </div>
                <RichText v-if="entity.summary || entity.description" as="p" class="related-context-description" :value="entity.summary || entity.description" />
                <a v-if="entity._relation_type === 'education' && entity.certificate_url" class="text-link" :href="mediaUrl(entity.certificate_url)" target="_blank" rel="noopener noreferrer">Ver diploma / comprovante <span aria-hidden="true">↗</span></a>
                <a v-if="entity._relation_type === 'certification' && entity.credential_url" class="text-link" :href="mediaUrl(entity.credential_url)" target="_blank" rel="noopener noreferrer">Ver certificado <span aria-hidden="true">↗</span></a>
              </div>
            </article>
          </div>
        </div>
      </template>

      <template v-else-if="section.section_type === 'contact'">
        <div class="container contact-layout"><div class="contact-form-intro"><SectionHeading :eyebrow="section.eyebrow || undefined" :title="section.title || 'Contato'" :description="section.lead || undefined" /></div><DynamicContactForm /></div>
      </template>
    </div>
  </section>
</template>
