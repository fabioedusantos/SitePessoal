<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import RichTextEditor from '../components/RichTextEditor.vue'
import ImageUploadField from '../components/ImageUploadField.vue'
import FileUploadField from '../components/FileUploadField.vue'
import { resources, type FieldConfig } from '../config/resources'
import { ApiError, api } from '../lib/api'

const route = useRoute()
const router = useRouter()
const resource = computed(() => String(route.params.resource || ''))
const id = computed(() => route.params.id ? String(route.params.id) : null)
const config = computed(() => resources[resource.value])
const form = reactive<Record<string, any>>({})
const options = reactive<Record<string, Array<{ value: string | number; label: string }>>>({})
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const validationMessage = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const technologyOptions = ref<Array<{ id: number; label: string }>>([])
const selectedTechnologyIds = ref<number[]>([])
const originalExperienceTechnologyRelations = ref<Record<number, number>>({})
const isExperience = computed(() => resource.value === 'experiences')

const publishingKeys = new Set([
  'status', 'featured', 'is_visible', 'is_current', 'is_external', 'noindex', 'show_in_sitemap', 'sort_order'
])
const mediaKeys = new Set([
  'repository_url', 'external_url', 'cover_image_url', 'website_url', 'image_url', 'logo_url',
  'credential_url', 'certificate_url', 'media_url', 'embed_url', 'og_image_url', 'canonical_url', 'url', 'cta_url'
])

const groupDefinitions = [
  { key: 'main', label: 'Informações principais', description: 'Identificação, vínculo, período e demais dados estruturados.' },
  { key: 'content', label: 'Conteúdo', description: 'Textos que ajudam a contar contexto, participação, responsabilidades e resultados.' },
  { key: 'media', label: 'Mídia e links', description: 'Endereços públicos, imagens, repositórios e destinos relacionados.' },
  { key: 'publishing', label: 'Publicação e exibição', description: 'Controle de destaque, visibilidade, status e ordenação.' }
] as const

type GroupKey = typeof groupDefinitions[number]['key']

function fieldGroup(field: FieldConfig): GroupKey {
  if (publishingKeys.has(field.key)) return 'publishing'
  if (mediaKeys.has(field.key) || /_url$/.test(field.key)) return 'media'
  if (field.richText || field.type === 'textarea' || field.type === 'list') return 'content'
  return 'main'
}

const fieldGroups = computed(() => groupDefinitions
  .map(group => ({ ...group, fields: config.value?.fields.filter(field => fieldGroup(field) === group.key) || [] }))
  .filter(group => group.fields.length > 0)
)

async function loadOptions(field: FieldConfig) {
  if (!field.optionsResource) return
  const result = await api<{ data: Record<string, any>[] }>(`/admin/${field.optionsResource}`)
  options[field.key] = result.data.map(item => ({
    value: item.id,
    label: item[field.optionLabel || 'name'] || item.title || item.name || item.role_title || item.section_key || `#${item.id}`
  }))
}


async function loadExperienceTechnologies() {
  technologyOptions.value = []
  selectedTechnologyIds.value = []
  originalExperienceTechnologyRelations.value = {}
  if (!isExperience.value) return

  const [technologyResult, relationResult] = await Promise.all([
    api<{ data: Record<string, any>[] }>('/admin/technologies'),
    api<{ data: Record<string, any>[] }>('/admin/experience-technologies')
  ])

  technologyOptions.value = (technologyResult.data || [])
    .filter(item => Number(item.is_visible) === 1)
    .sort((a, b) => Number(a.sort_order) - Number(b.sort_order) || String(a.name || '').localeCompare(String(b.name || ''), 'pt-BR'))
    .map(item => ({ id: Number(item.id), label: String(item.name || `#${item.id}`) }))

  if (!id.value) return
  const relations = (relationResult.data || [])
    .filter(row => Number(row.experience_id) === Number(id.value))
    .sort((a, b) => Number(a.sort_order) - Number(b.sort_order) || Number(a.id) - Number(b.id))

  selectedTechnologyIds.value = relations.map(row => Number(row.technology_id))
  originalExperienceTechnologyRelations.value = Object.fromEntries(
    relations.map(row => [Number(row.technology_id), Number(row.id)])
  )
}

async function syncExperienceTechnologies(experienceId: number) {
  if (!isExperience.value) return
  const selected = selectedTechnologyIds.value.map(Number)
  const selectedSet = new Set(selected)
  const originals = originalExperienceTechnologyRelations.value

  for (let index = 0; index < selected.length; index += 1) {
    const technologyId = selected[index]
    const payload = {
      experience_id: experienceId,
      technology_id: technologyId,
      sort_order: (index + 1) * 10
    }

    if (originals[technologyId]) {
      await api(`/admin/experience-technologies/${originals[technologyId]}`, {
        method: 'PUT',
        body: JSON.stringify(payload)
      })
    } else {
      const created = await api<{ id: number }>('/admin/experience-technologies', {
        method: 'POST',
        body: JSON.stringify(payload)
      })
      originals[technologyId] = created.id
    }
  }

  for (const [technologyIdText, relationId] of Object.entries({ ...originals })) {
    const technologyId = Number(technologyIdText)
    if (!selectedSet.has(technologyId)) {
      await api(`/admin/experience-technologies/${relationId}`, { method: 'DELETE' })
      delete originals[technologyId]
    }
  }
}

async function load() {
  if (!config.value) {
    router.replace('/')
    return
  }

  error.value = ''
  clearValidation()
  loading.value = true

  try {
    await Promise.all([
      ...config.value.fields.map(loadOptions),
      loadExperienceTechnologies()
    ])

    for (const field of config.value.fields) {
      if (field.type === 'checkbox') form[field.key] = false
      else if (field.key === 'sort_order') form[field.key] = 0
      else form[field.key] = ''
    }

    if (id.value) {
      const result = await api<{ data: Record<string, any> }>(`/admin/${resource.value}/${id.value}`)
      for (const field of config.value.fields) {
        const value = result.data[field.key]
        if (field.type === 'checkbox') {
          form[field.key] = Boolean(Number(value))
        } else if (field.type === 'list') {
          try {
            const parsed = typeof value === 'string' ? JSON.parse(value) : value
            form[field.key] = Array.isArray(parsed) ? parsed.join('\n') : (value ?? '')
          } catch {
            form[field.key] = value ?? ''
          }
        } else {
          form[field.key] = value ?? ''
        }
      }
    } else {
      if ('is_visible' in form) form.is_visible = true
      if ('status' in form) form.status = 'draft'
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao carregar formulário.'
  } finally {
    loading.value = false
  }
}

function fieldId(field: FieldConfig) {
  return `field-${resource.value}-${field.key}`
}

function inputType(field: FieldConfig) {
  if (field.type === 'number' || field.type === 'date') return field.type
  if (field.key === 'email') return 'email'
  return 'text'
}

function placeholder(field: FieldConfig) {
  if (field.placeholder) return field.placeholder
  if (field.type === 'list') return 'Um item por linha'
  if (field.key === 'slug') return 'exemplo-de-slug'
  if (field.key.endsWith('_url') || field.key === 'url') return 'https://…'
  if (field.type === 'textarea') return 'Escreva aqui…'
  return ''
}


function externalUrl(value: unknown) {
  const url = String(value || '').trim()
  return /^https?:\/\//i.test(url) ? url : ''
}


function isBlank(value: unknown) {
  return value === null || value === undefined || (typeof value === 'string' && value.trim() === '')
}

function plainText(value: unknown) {
  return String(value || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim()
}

function isSafeUrl(value: unknown) {
  const url = String(value || '').trim()
  if (!url) return true
  if ((url.startsWith('/') && !url.startsWith('//')) || url.startsWith('#')) return true
  if (/^(mailto|tel):/i.test(url)) return !/[\r\n]/.test(url)
  try {
    const parsed = new URL(url)
    return parsed.protocol === 'http:' || parsed.protocol === 'https:'
  } catch {
    return false
  }
}

function clearValidation() {
  validationMessage.value = ''
  for (const key of Object.keys(fieldErrors)) delete fieldErrors[key]
}

function clearFieldError(key: string) {
  delete fieldErrors[key]
  if (!Object.keys(fieldErrors).length) validationMessage.value = ''
}

function validateForm() {
  if (!config.value) return false
  clearValidation()

  for (const field of config.value.fields) {
    const value = form[field.key]
    const effectiveValue = field.richText ? plainText(value) : value

    if (field.required && isBlank(effectiveValue)) {
      fieldErrors[field.key] = `Preencha ${field.label.toLowerCase()}.`
      continue
    }

    if (!isBlank(value) && field.key === 'slug' && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(String(value).trim())) {
      fieldErrors[field.key] = 'Use apenas letras minúsculas, números e hífens, sem espaços.'
    }

    if (!isBlank(value) && (field.key === 'url' || field.key.endsWith('_url')) && !isSafeUrl(value)) {
      fieldErrors[field.key] = 'Informe uma URL válida, por exemplo https://exemplo.com.'
    }

    if (!isBlank(value) && field.type === 'number' && !Number.isFinite(Number(value))) {
      fieldErrors[field.key] = 'Informe um número válido.'
    }

    if (field.key === 'workload_hours' && !isBlank(value) && Number(value) <= 0) {
      fieldErrors[field.key] = 'A carga horária deve ser maior que zero.'
    }
  }

  const start = String(form.start_date || '')
  const end = String(form.end_date || '')
  if (start && end && end < start) {
    fieldErrors.end_date = 'A data final não pode ser anterior à data inicial.'
  }

  if (!Object.keys(fieldErrors).length) return true

  validationMessage.value = 'Revise os campos destacados antes de salvar.'
  nextTick(() => {
    const firstKey = Object.keys(fieldErrors)[0]
    const element = document.getElementById(`field-${resource.value}-${firstKey}`)
    element?.focus()
    element?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  })
  return false
}

function applyApiFieldErrors(exception: ApiError) {
  for (const [key, message] of Object.entries(exception.fieldErrors)) {
    if (config.value?.fields.some(field => field.key === key)) fieldErrors[key] = message
  }
  const hasFieldErrors = Object.keys(fieldErrors).length > 0
  if (hasFieldErrors) validationMessage.value = 'Revise os campos destacados antes de salvar.'
  return hasFieldErrors
}

function help(field: FieldConfig) {
  if (field.help) return field.help
  if (field.type === 'image') return 'A imagem é enviada para o servidor e gerenciada pelo CMS. Ao substituí-la ou removê-la, uploads sem uso são excluídos fisicamente.'
  if (field.type === 'media') return field.help || 'Para imagens, use upload. URLs externas continuam disponíveis para vídeos e mídias remotas.'
  if (field.type === 'file') return field.help || 'Envie um arquivo gerenciado pelo CMS ou informe uma URL externa.'
  if (field.richText) return 'Você pode aplicar negrito, itálico e sublinhado aos trechos selecionados.'
  if (field.type === 'list') return 'Cada linha será salva como um item separado.'
  if (field.type === 'date') return 'Use a data correspondente ao período real.'
  if (field.key.endsWith('_url') || field.key === 'url') return 'Informe uma URL completa ou um caminho público gerenciado (ex.: /uploads/…), conforme o campo.'
  return ''
}

async function save() {
  if (!config.value) return
  error.value = ''
  if (!validateForm()) return
  saving.value = true

  try {
    const body: Record<string, any> = {}
    for (const field of config.value.fields) {
      let value = form[field.key]
      if (field.type === 'checkbox') value = value ? 1 : 0
      if (field.type === 'number' && value !== '') value = Number(value)
      if (field.type === 'list') value = String(value || '').split(/\r?\n/).map(item => item.trim()).filter(Boolean)
      body[field.key] = value
    }

    const path = id.value ? `/admin/${resource.value}/${id.value}` : `/admin/${resource.value}`
    let savedId = id.value ? Number(id.value) : 0
    if (id.value) {
      await api(path, { method: 'PUT', body: JSON.stringify(body) })
    } else {
      const created = await api<{ id: number }>(path, { method: 'POST', body: JSON.stringify(body) })
      savedId = created.id
    }

    if (isExperience.value && savedId) {
      await syncExperienceTechnologies(savedId)
    }

    router.push(`/${resource.value}`)
  } catch (e) {
    const handledInline = e instanceof ApiError ? applyApiFieldErrors(e) : false
    error.value = handledInline ? '' : (e instanceof Error ? e.message : 'Não foi possível salvar. Revise os dados e tente novamente.')
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    saving.value = false
  }
}

watch([resource, id], load, { immediate: true })

function newResourceLabel() {
  const adjective = config.value?.gender === 'feminine' ? 'Nova' : 'Novo'
  return `${adjective} ${config.value?.singular.toLowerCase() ?? 'registro'}`
}
</script>

<template>
  <div v-if="config" class="resource-editor-page">
    <header class="page-title page-title-editor">
      <div>
        <RouterLink class="back-link" :to="`/${resource}`">← Voltar para {{ config.label.toLowerCase() }}</RouterLink>
        <p class="overline">{{ config.label }}</p>
        <h1>{{ id ? `Editar ${config.singular.toLowerCase()}` : newResourceLabel() }}</h1>
        <p>Organize o conteúdo com clareza. O que for salvo aqui alimenta o site público.</p>
      </div>
      <span class="edit-status-badge">{{ id ? `Registro #${id}` : 'Novo registro' }}</span>
    </header>

    <div v-if="validationMessage" class="validation-summary" role="alert">{{ validationMessage }}</div>
    <div v-if="error" class="alert" role="alert">{{ error }}</div>

    <div v-if="loading" class="editor-loading" aria-live="polite">
      <div class="editor-loading-line" />
      <div class="editor-loading-block" />
      <div class="editor-loading-block short" />
    </div>

    <form v-else class="resource-editor-form" novalidate @submit.prevent="save">
      <section v-for="group in fieldGroups" :key="group.key" class="editor-section">
        <header class="editor-section-heading">
          <div>
            <p class="section-kicker">{{ group.label }}</p>
            <p>{{ group.description }}</p>
          </div>
        </header>

        <div class="form-grid">
          <div
            v-for="field in group.fields"
            :key="field.key"
            class="form-field"
            :class="{
              wide: field.richText || field.type === 'textarea' || field.type === 'list' || field.type === 'image' || field.type === 'media' || field.type === 'file',
              'is-toggle-field': field.type === 'checkbox',
              'is-code-field': field.key === 'slug' || field.key.endsWith('_key'),
              'has-error': Boolean(fieldErrors[field.key])
            }"
          >
            <template v-if="field.type === 'checkbox'">
              <div class="toggle-copy">
                <span class="field-label">{{ field.label }}</span>
                <small v-if="fieldErrors[field.key]" :id="`${fieldId(field)}-error`" class="field-error">{{ fieldErrors[field.key] }}</small>
              <small v-if="help(field)" class="field-help">{{ help(field) }}</small>
              </div>
              <label class="switch" :for="fieldId(field)">
                <input :id="fieldId(field)" v-model="form[field.key]" type="checkbox">
                <span class="switch-track" aria-hidden="true"><span /></span>
                <span class="switch-state">{{ form[field.key] ? 'Ativo' : 'Inativo' }}</span>
              </label>
            </template>

            <template v-else>
              <label class="field-label" :for="fieldId(field)">
                {{ field.label }}
                <b v-if="field.required" aria-label="obrigatório">*</b>
              </label>

              <ImageUploadField
                v-if="field.type === 'image' || field.type === 'media'"
                v-model="form[field.key]"
                :id="fieldId(field)"
                :scope="`${resource}-${field.key}`"
                :preset="field.imagePreset || 'free'"
                :allow-external="field.type === 'media'"
                :required="field.required"
                @update:model-value="clearFieldError(field.key)"
              />

              <FileUploadField
                v-else-if="field.type === 'file'"
                v-model="form[field.key]"
                :id="fieldId(field)"
                :scope="`${resource}-${field.key}`"
                allow-external
                :required="field.required"
                @update:model-value="clearFieldError(field.key)"
              />

              <RichTextEditor
                v-else-if="field.richText"
                :id="fieldId(field)"
                v-model="form[field.key]"
                :placeholder="placeholder(field)"
                :min-height="Math.max(130, (field.rows || 5) * 24)"
                @update:model-value="clearFieldError(field.key)"
              />

              <textarea
                v-else-if="field.type === 'textarea' || field.type === 'list'"
                :id="fieldId(field)"
                v-model="form[field.key]"
                :rows="field.rows || (field.type === 'list' ? 6 : 5)"
                :placeholder="placeholder(field)"
                :required="field.required"
                :aria-invalid="Boolean(fieldErrors[field.key])"
                :aria-describedby="fieldErrors[field.key] ? `${fieldId(field)}-error` : undefined"
                @input="clearFieldError(field.key)"
              />

              <select
                v-else-if="field.type === 'select'"
                :id="fieldId(field)"
                v-model="form[field.key]"
                :required="field.required"
                :aria-invalid="Boolean(fieldErrors[field.key])"
                :aria-describedby="fieldErrors[field.key] ? `${fieldId(field)}-error` : undefined"
                @change="clearFieldError(field.key)"
              >
                <option value="">Selecione uma opção</option>
                <option
                  v-for="option in field.options || options[field.key] || []"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>

              <input
                v-else
                :id="fieldId(field)"
                v-model="form[field.key]"
                :type="inputType(field)"
                :placeholder="placeholder(field)"
                :required="field.required"
                :aria-invalid="Boolean(fieldErrors[field.key])"
                :aria-describedby="fieldErrors[field.key] ? `${fieldId(field)}-error` : undefined"
                @input="clearFieldError(field.key)"
              >

              <a
                v-if="field.type !== 'image' && field.type !== 'media' && externalUrl(form[field.key]) && (field.key.endsWith('_url') || field.key === 'url')"
                class="field-inline-link"
                :href="externalUrl(form[field.key])"
                target="_blank"
                rel="noopener noreferrer"
              >Abrir endereço ↗</a>
              <small v-if="fieldErrors[field.key]" :id="`${fieldId(field)}-error`" class="field-error">{{ fieldErrors[field.key] }}</small>
              <small v-if="help(field)" class="field-help">{{ help(field) }}</small>
            </template>
          </div>
        </div>
      </section>


      <section v-if="isExperience" class="editor-section">
        <header class="editor-section-heading">
          <div>
            <p class="section-kicker">Tecnologias</p>
            <p>Associe aqui as tecnologias realmente utilizadas nesta experiência. Não existe mais uma tela separada de relacionamento.</p>
          </div>
        </header>
        <div v-if="technologyOptions.length" class="choice-grid choice-grid-large">
          <label v-for="item in technologyOptions" :key="item.id" class="choice-chip">
            <input v-model="selectedTechnologyIds" type="checkbox" :value="item.id">
            <span>{{ item.label }}</span>
          </label>
        </div>
        <div v-else class="inline-empty-state">
          Nenhuma tecnologia cadastrada. Cadastre as tecnologias primeiro para poder associá-las à experiência.
        </div>
      </section>

      <div class="form-actions form-actions-sticky">
        <div class="form-actions-copy">
          <strong>{{ id ? 'Revise antes de salvar' : 'Pronto para cadastrar?' }}</strong>
          <span>Campos marcados com * são obrigatórios.</span>
        </div>
        <div class="form-actions-buttons">
          <RouterLink class="secondary-button link-button" :to="`/${resource}`">Cancelar</RouterLink>
          <button class="primary-button" type="submit" :disabled="saving">
            {{ saving ? 'Salvando…' : (id ? 'Salvar alterações' : 'Cadastrar') }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
