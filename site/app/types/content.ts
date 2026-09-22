export interface Profile {
  full_name: string
  professional_title: string
  headline: string
  summary: string
  about_short: string
  location: string
  email: string
  phone: string
}

export interface LinkItem {
  label: string
  url: string
  kind: string
}

export interface Organization {
  slug: string
  name: string
  image_url?: string | null
}

export interface Technology {
  id: number
  slug: string
  name: string
  category: string
  description?: string | null
}

export interface Experience {
  id: number
  slug: string
  role_title: string
  start_date: string
  end_date: string | null
  is_current: number | boolean
  location: string
  work_mode: string
  employment_type: string | null
  summary: string
  description?: string | null
  highlights: string[]
  organization: string
  organization_slug: string
  organization_image_url?: string | null
  technologies?: Technology[]
}

export interface ProjectLink {
  id: number
  label: string
  url: string
  kind: string
}

export interface ProjectMedia {
  id: number
  media_url: string
  alt_text: string
  caption?: string | null
  kind: string
}

export interface ProjectSection {
  id: number
  eyebrow?: string | null
  title: string
  body?: string | null
  items: string[]
}

export interface Project {
  id: number
  slug: string
  name: string
  period_label?: string | null
  context?: string | null
  participation?: string | null
  solution?: string | null
  architecture?: string | null
  challenges?: string | null
  results?: string | null
  organization?: string | null
  organization_slug?: string | null
  organization_image_url?: string | null
  cover_image_url?: string | null
  external_url?: string | null
  repository_url?: string | null
  technologies?: Technology[]
  links?: ProjectLink[]
  media?: ProjectMedia[]
  related_experiences?: Experience[]
  related_contexts?: Array<Record<string, unknown>>
  sections?: ProjectSection[]
}

export interface Education {
  id: number
  institution: string
  course: string
  degree_type?: string | null
  start_date?: string | null
  end_date?: string | null
  location?: string | null
  logo_url?: string | null
  certificate_url?: string | null
  description?: string | null
}

export interface Certification {
  id: number
  name: string
  issuer: string
  logo_url?: string | null
  issued_date?: string | null
  workload_hours?: string | number | null
  credential_url?: string | null
}

export interface PublicationMedia {
  id: number
  title?: string | null
  media_type: string
  media_url: string
  embed_url?: string | null
}

export interface PublicationSection {
  id: number
  eyebrow?: string | null
  title: string
  body?: string | null
  items: string[]
}

export interface PublicationRelatedProject {
  id: number
  slug: string
  name: string
  period_label?: string | null
  context?: string | null
  relation_label?: string | null
}

export interface Publication {
  id: number
  slug: string
  title: string
  kind: string
  organization?: string | null
  event_name?: string | null
  location?: string | null
  published_date?: string | null
  summary?: string | null
  description?: string | null
  external_url?: string | null
  media?: PublicationMedia[]
  sections?: PublicationSection[]
  related_projects?: PublicationRelatedProject[]
}

export interface EvaluationBoard {
  id: number
  slug: string
  title: string
  institution: string
  logo_url?: string | null
  event_date: string
  description?: string | null
}

export interface SiteNavigationItem {
  id: number
  location: string
  label: string
  page_id?: number | null
  url?: string | null
  resolved_url?: string | null
  is_external: number | boolean
  sort_order: number
}

export interface SiteContentPayload {
  navigation: SiteNavigationItem[]
  settings: SiteSettings
}

export interface PageBuilderPage {
  id: number
  title: string
  slug: string
  status: string
  page_kind: string
  entity_type?: string | null
  entity_id?: number | null
  seo_title?: string | null
  seo_description?: string | null
  canonical_url?: string | null
  og_image_url?: string | null
  noindex: number | boolean
  show_in_sitemap: number | boolean
}

export interface PageBuilderItem {
  id?: number
  item_type: string
  entity_type?: string | null
  entity_id?: number | null
  eyebrow?: string | null
  title?: string | null
  subtitle?: string | null
  body?: string | null
  value_label?: string | null
  url?: string | null
  image_url?: string | null
  image_alt?: string | null
  settings?: Record<string, any>
  entity?: Record<string, any> | null
  sort_order: number
}

export interface PageBuilderSection {
  id: number
  page_id: number
  section_type: string
  internal_name?: string | null
  eyebrow?: string | null
  title?: string | null
  accent_title?: string | null
  lead?: string | null
  body?: string | null
  image_url?: string | null
  image_alt?: string | null
  cta_label?: string | null
  cta_url?: string | null
  theme: string
  background_color?: string | null
  text_color?: string | null
  accent_color?: string | null
  width_mode: string
  spacing_top: string
  spacing_bottom: string
  settings?: Record<string, any>
  sort_order: number
  items: PageBuilderItem[]
}

export interface PageBuilderPayload {
  page: PageBuilderPage
  sections: PageBuilderSection[]
}

export interface SiteSettings {
  header_logo_url?: string | null
  header_logo_alt?: string | null
  footer_logo_url?: string | null
  footer_logo_alt?: string | null
  footer_lead?: string | null
  footer_title?: string | null
  footer_body?: string | null
}
