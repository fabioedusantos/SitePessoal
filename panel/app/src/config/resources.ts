export type FieldType = 'text' | 'textarea' | 'list' | 'number' | 'date' | 'checkbox' | 'select' | 'image' | 'media' | 'file'
export type ImageUploadPreset = 'free' | 'showcase' | 'academic-logo' | 'open-graph' | 'brand-logo'

export interface FieldConfig {
  key: string
  label: string
  type: FieldType
  required?: boolean
  options?: Array<{ value: string | number; label: string }>
  optionsResource?: string
  optionLabel?: string
  richText?: boolean
  help?: string
  placeholder?: string
  rows?: number
  imagePreset?: ImageUploadPreset
}

export interface SortRule {
  key: string
  direction: 'asc' | 'desc'
}

export interface ResourceConfig {
  key: string
  label: string
  singular: string
  gender?: 'masculine' | 'feminine'
  titleField: string
  columns: Array<{ key: string; label: string }>
  fields: FieldConfig[]
  defaultSort?: SortRule[]
}

export const resources: Record<string, ResourceConfig> = {
  experiences: {
    key: 'experiences',
    label: 'Experiências',
    singular: 'Experiência',
    gender: 'feminine',
    titleField: 'role_title',
    columns: [
      { key: 'organization_id', label: 'Empresa' },
      { key: 'role_title', label: 'Cargo' },
      { key: 'start_date', label: 'Início' },
      { key: 'is_current', label: 'Atual' },
      { key: 'is_visible', label: 'Visível' }
    ],
    fields: [
      { key: 'organization_id', label: 'Organização', type: 'select', required: true, optionsResource: 'organizations', optionLabel: 'name' },
      { key: 'role_title', label: 'Cargo', type: 'text', required: true },
      { key: 'slug', label: 'Slug', type: 'text', required: true, help: 'Identificador usado na URL. Use letras minúsculas, números e hífens.' },
      { key: 'start_date', label: 'Data inicial', type: 'date', required: true },
      { key: 'end_date', label: 'Data final', type: 'date' },
      { key: 'is_current', label: 'Cargo atual', type: 'checkbox' },
      { key: 'location', label: 'Localização', type: 'text' },
      { key: 'work_mode', label: 'Modalidade', type: 'select', options: [
        { value: 'remoto', label: 'Remoto' },
        { value: 'hibrido', label: 'Híbrido' },
        { value: 'presencial', label: 'Presencial' },
        { value: 'nao_informado', label: 'Não informado' }
      ]},
      { key: 'employment_type', label: 'Tipo de vínculo', type: 'text' },
      { key: 'summary', label: 'Resumo', type: 'textarea', richText: true, rows: 4 },
      { key: 'description', label: 'Descrição completa', type: 'textarea', richText: true, rows: 8 },
      { key: 'highlights', label: 'Destaques (um por linha)', type: 'list' },
      { key: 'sort_order', label: 'Ordem', type: 'number', help: 'Números menores aparecem primeiro.' },
      { key: 'is_visible', label: 'Visível', type: 'checkbox', help: 'Controla se este conteúdo pode aparecer no site público.' }
    ]
  },
  projects: {
    key: 'projects',
    label: 'Cases e projetos',
    singular: 'Projeto',
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Projeto' },
      { key: 'period_label', label: 'Período' },
      { key: 'status', label: 'Status' },
      { key: 'featured', label: 'Prioridade' }
    ],
    fields: [
      { key: 'name', label: 'Nome', type: 'text', required: true },
      { key: 'slug', label: 'Slug', type: 'text', required: true, help: 'Identificador usado na URL. Use letras minúsculas, números e hífens.' },
      { key: 'organization_id', label: 'Organização', type: 'select', optionsResource: 'organizations', optionLabel: 'name' },
      { key: 'experience_id', label: 'Experiência principal relacionada', type: 'select', optionsResource: 'experiences', optionLabel: 'role_title', help: 'Use quando houver uma experiência principal diretamente associada ao projeto.' },
      { key: 'period_label', label: 'Período', type: 'text' },
      { key: 'context', label: 'Contexto / problema', type: 'textarea', richText: true, rows: 6 },
      { key: 'participation', label: 'Participação', type: 'textarea', richText: true, rows: 6 },
      { key: 'solution', label: 'Solução', type: 'textarea', richText: true, rows: 6 },
      { key: 'architecture', label: 'Arquitetura', type: 'textarea', richText: true, rows: 6 },
      { key: 'challenges', label: 'Desafios', type: 'textarea', richText: true, rows: 6 },
      { key: 'results', label: 'Resultados', type: 'textarea', richText: true, rows: 6 },
      { key: 'repository_url', label: 'Repositório', type: 'text' },
      { key: 'external_url', label: 'Link externo', type: 'text' },
      { key: 'cover_image_url', label: 'Imagem de capa', type: 'image', imagePreset: 'showcase', help: 'Envie a imagem usada como capa do case.' },
      { key: 'featured', label: 'Priorizar na listagem geral', type: 'checkbox', help: 'Afeta apenas a prioridade na página/listagem geral de projetos. A Home é configurada em Página inicial.' },
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'draft', label: 'Rascunho' },
        { value: 'published', label: 'Publicado' }
      ]},
      { key: 'sort_order', label: 'Ordem', type: 'number', help: 'Números menores aparecem primeiro.' }
    ]
  },
  technologies: {
    key: 'technologies',
    label: 'Tecnologias',
    singular: 'Tecnologia',
    gender: 'feminine',
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Tecnologia' },
      { key: 'category', label: 'Categoria' },
      { key: 'is_current', label: 'Uso atual' },
      { key: 'is_visible', label: 'Visível' }
    ],
    fields: [
      { key: 'name', label: 'Nome', type: 'text', required: true },
      { key: 'slug', label: 'Slug', type: 'text', required: true, help: 'Identificador usado na URL. Use letras minúsculas, números e hífens.' },
      { key: 'category', label: 'Categoria', type: 'text', required: true },
      { key: 'description', label: 'Descrição', type: 'textarea', rows: 5 },
      { key: 'is_current', label: 'Uso atual', type: 'checkbox' },
      { key: 'sort_order', label: 'Ordem', type: 'number', help: 'Números menores aparecem primeiro.' },
      { key: 'is_visible', label: 'Visível', type: 'checkbox', help: 'Controla se este conteúdo pode aparecer no site público.' }
    ]
  },
  education: {
    key: 'education',
    label: 'Formação',
    singular: 'Formação',
    gender: 'feminine',
    titleField: 'course',
    columns: [
      { key: 'course', label: 'Curso' },
      { key: 'institution', label: 'Instituição' },
      { key: 'degree_type', label: 'Tipo' },
      { key: 'is_visible', label: 'Visível' }
    ],
    fields: [
      { key: 'institution', label: 'Instituição', type: 'text', required: true },
      { key: 'course', label: 'Curso', type: 'text', required: true },
      { key: 'degree_type', label: 'Tipo', type: 'text' },
      { key: 'start_date', label: 'Início', type: 'date' },
      { key: 'end_date', label: 'Conclusão', type: 'date' },
      { key: 'location', label: 'Localização', type: 'text' },
      { key: 'logo_url', label: 'Logo da instituição', type: 'image', imagePreset: 'academic-logo' },
      { key: 'certificate_url', label: 'Diploma / comprovante', type: 'file', help: 'Opcional. Envie uma imagem ou PDF do diploma/comprovante, ou informe um link externo.' },
      { key: 'description', label: 'Descrição', type: 'textarea', richText: true, rows: 6 },
      { key: 'sort_order', label: 'Ordem', type: 'number', help: 'Números menores aparecem primeiro.' },
      { key: 'is_visible', label: 'Visível', type: 'checkbox', help: 'Controla se este conteúdo pode aparecer no site público.' }
    ]
  },
  certifications: {
    key: 'certifications',
    label: 'Certificações',
    singular: 'Certificação',
    gender: 'feminine',
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Certificação' },
      { key: 'issuer', label: 'Emissor' },
      { key: 'issued_date', label: 'Data' },
      { key: 'workload_hours', label: 'Carga horária (h)' },
      { key: 'is_visible', label: 'Visível' }
    ],
    fields: [
      { key: 'name', label: 'Nome', type: 'text', required: true },
      { key: 'issuer', label: 'Emissor', type: 'text', required: true },
      { key: 'logo_url', label: 'Logo do emissor', type: 'image', imagePreset: 'academic-logo' },
      { key: 'issued_date', label: 'Data', type: 'date' },
      { key: 'workload_hours', label: 'Carga horária (horas)', type: 'number', help: 'Informe somente a quantidade de horas. Ex.: 24 para um curso de 24 horas. Campo opcional.' },
      { key: 'credential_url', label: 'Certificado / credencial', type: 'file', help: 'Envie o certificado em imagem ou PDF, ou informe o link público da credencial.' },
      { key: 'description', label: 'Descrição', type: 'textarea', rows: 5 },
      { key: 'sort_order', label: 'Ordem', type: 'number', help: 'Números menores aparecem primeiro.' },
      { key: 'is_visible', label: 'Visível', type: 'checkbox', help: 'Controla se este conteúdo pode aparecer no site público.' }
    ]
  },
  publications: {
    key: 'publications',
    label: 'Publicações',
    singular: 'Publicação',
    gender: 'feminine',
    titleField: 'title',
    columns: [
      { key: 'title', label: 'Título' },
      { key: 'kind', label: 'Tipo' },
      { key: 'published_date', label: 'Data' },
      { key: 'is_visible', label: 'Visível' }
    ],
    fields: [
      { key: 'title', label: 'Título', type: 'text', required: true },
      { key: 'slug', label: 'Slug', type: 'text', required: true, help: 'Identificador usado na URL. Use letras minúsculas, números e hífens.' },
      { key: 'kind', label: 'Tipo', type: 'text', required: true },
      { key: 'organization', label: 'Organização', type: 'text' },
      { key: 'event_name', label: 'Evento', type: 'text' },
      { key: 'location', label: 'Local', type: 'text' },
      { key: 'published_date', label: 'Data', type: 'date' },
      { key: 'summary', label: 'Resumo', type: 'textarea', richText: true, rows: 4 },
      { key: 'description', label: 'Descrição completa', type: 'textarea', richText: true, rows: 8 },
      { key: 'external_url', label: 'URL externa', type: 'text' },
      { key: 'sort_order', label: 'Ordem', type: 'number', help: 'Números menores aparecem primeiro.' },
      { key: 'is_visible', label: 'Visível', type: 'checkbox', help: 'Controla se este conteúdo pode aparecer no site público.' }
    ]
  },
  organizations: {
    key: 'organizations',
    label: 'Organizações',
    singular: 'Organização',
    gender: 'feminine',
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Organização' },
      { key: 'slug', label: 'Slug' },
      { key: 'website_url', label: 'Site' },
    ],
    fields: [
      { key: 'name', label: 'Nome', type: 'text', required: true },
      { key: 'slug', label: 'Slug', type: 'text', required: true, help: 'Identificador usado na URL. Use letras minúsculas, números e hífens.' },
      { key: 'website_url', label: 'Site', type: 'text' },
      { key: 'image_url', label: 'Imagem da organização', type: 'image', imagePreset: 'showcase', help: 'Imagem única da organização. O servidor padroniza o arquivo em 1200 × 320 px (15:4) para uso no site.' },
      { key: 'description', label: 'Descrição', type: 'textarea', rows: 5 },
      { key: 'sort_order', label: 'Ordem', type: 'number', help: 'Números menores aparecem primeiro.' }
    ]
  },
  'evaluation-boards': {
    key: 'evaluation-boards',
    label: 'Bancas avaliadoras',
    singular: 'Banca avaliadora',
    gender: 'feminine',
    titleField: 'title',
    columns: [
      { key: 'title', label: 'Projeto' },
      { key: 'event_date', label: 'Data' },
      { key: 'institution', label: 'Instituição' },
      { key: 'is_visible', label: 'Visível' }
    ],
    fields: [
      { key: 'slug', label: 'Slug', type: 'text', required: true, help: 'Identificador usado na URL. Use letras minúsculas, números e hífens.' },
      { key: 'title', label: 'Projeto', type: 'text', required: true },
      { key: 'institution', label: 'Instituição', type: 'text', required: true },
      { key: 'logo_url', label: 'Logo da instituição', type: 'image', imagePreset: 'academic-logo' },
      { key: 'event_date', label: 'Data', type: 'date', required: true },
      { key: 'description', label: 'Descrição', type: 'textarea', richText: true, rows: 6 },
      { key: 'sort_order', label: 'Ordem', type: 'number', help: 'Números menores aparecem primeiro.' },
      { key: 'is_visible', label: 'Visível', type: 'checkbox', help: 'Controla se este conteúdo pode aparecer no site público.' }
    ]
  },
  pages: {
    key: 'pages', label: 'Páginas', singular: 'Página', gender: 'feminine', titleField: 'title',
    columns: [
      { key: 'title', label: 'Página' }, { key: 'slug', label: 'Caminho' },
      { key: 'status', label: 'Status' }, { key: 'page_kind', label: 'Tipo' }, { key: 'sort_order', label: 'Ordem' }
    ],
    defaultSort: [{ key: 'sort_order', direction: 'asc' }],
    fields: [
      { key: 'title', label: 'Título interno', type: 'text', required: true },
      { key: 'slug', label: 'Caminho', type: 'text', required: true, help: 'Use home para a página inicial. Nas demais páginas use o caminho sem barra inicial, por exemplo projetos/contaokay.' },
      { key: 'status', label: 'Status', type: 'select', required: true, options: [{ value: 'draft', label: 'Rascunho' }, { value: 'published', label: 'Publicado' }] },
      { key: 'page_kind', label: 'Tipo de página', type: 'select', options: [{ value: 'standard', label: 'Padrão' }, { value: 'home', label: 'Página inicial' }, { value: 'project', label: 'Case/projeto' }] },
      { key: 'seo_title', label: 'SEO · Title', type: 'text' },
      { key: 'seo_description', label: 'SEO · Description', type: 'textarea', rows: 4 },
      { key: 'canonical_url', label: 'Canonical', type: 'text' },
      { key: 'og_image_url', label: 'Imagem Open Graph', type: 'image', imagePreset: 'open-graph' },
      { key: 'noindex', label: 'Noindex', type: 'checkbox' },
      { key: 'show_in_sitemap', label: 'Exibir no sitemap', type: 'checkbox' },
      { key: 'sort_order', label: 'Ordem', type: 'number' }
    ]
  },
}
