<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { api } from '../lib/api'

type ImageUploadPreset = 'free' | 'showcase' | 'academic-logo' | 'open-graph' | 'brand-logo'

interface LibraryImage {
  id: number
  url: string
  original_name: string
  alt_text?: string | null
  width?: number | null
  height?: number | null
  size_bytes: number
  created_at: string
}

const props = withDefaults(defineProps<{
  modelValue?: string | null
  id?: string
  scope: string
  preset?: ImageUploadPreset
  allowExternal?: boolean
  required?: boolean
}>(), {
  modelValue: '',
  preset: 'free',
  allowExternal: false,
  required: false
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const presetGuidance: Record<ImageUploadPreset, string> = {
  showcase: 'Padrão do site: 1200 × 320 px (15:4). Envie no mínimo nessa dimensão. O servidor recorta ao centro, redimensiona e converte para WebP.',
  'academic-logo': 'Padrão de logos acadêmicos: 520 × 150 px (52:15). Envie no mínimo nessa dimensão. O servidor enquadra a marca sem cortar e converte para WebP.',
  'open-graph': 'Padrão Open Graph: 1200 × 630 px (aprox. 1,91:1). Envie no mínimo nessa dimensão. O servidor recorta ao centro, redimensiona e converte para WebP.',
  'brand-logo': 'Logo: proporção preservada e sem recorte. Use uma imagem horizontal, nítida e com boa margem. O servidor otimiza e converte para WebP.',
  free: 'Formato livre: a proporção é preservada e nada é recortado. Imagens muito grandes são reduzidas para até 2400 px no maior lado e convertidas para WebP.'
}

const input = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const dragging = ref(false)
const error = ref('')
const lastUploadedUrl = ref('')
const libraryOpen = ref(false)
const libraryLoading = ref(false)
const libraryLoaded = ref(false)
const libraryError = ref('')
const libraryImages = ref<LibraryImage[]>([])
const apiBase = import.meta.env.VITE_API_BASE || 'http://localhost:8080/api/v1'
const apiOrigin = apiBase.replace(/\/api\/v1\/?$/, '')
const siteBase = import.meta.env.VITE_SITE_URL || (import.meta.env.DEV ? 'http://localhost:3000' : 'https://fbsantos.com.br')

const value = computed(() => String(props.modelValue || '').trim())
const guidance = computed(() => presetGuidance[props.preset])

function publicImageUrl(source: string) {
  const url = String(source || '').trim()
  if (!url) return ''
  if (/^https?:\/\//i.test(url) || url.startsWith('data:')) return url
  if (url.startsWith('/uploads/')) return `${apiOrigin}${url}`
  if (url.startsWith('/')) return `${siteBase}${url}`
  return ''
}

const preview = computed(() => publicImageUrl(value.value))

const looksLikeImage = computed(() => {
  const source = value.value.toLowerCase().split('?')[0]
  return source.startsWith('/uploads/') || /\.(jpe?g|png|webp|svg)$/i.test(source)
})

function openPicker() {
  input.value?.click()
}

async function discardPending(url: string) {
  if (!url) return
  try {
    await api('/admin/uploads/image', {
      method: 'DELETE',
      body: JSON.stringify({ url })
    })
  } catch {
    // O backend também remove uploads substituídos após o registro ser salvo.
  }
}

async function upload(file?: File) {
  if (!file) return

  error.value = ''
  if (file.type && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    error.value = 'Envie uma imagem JPG, PNG ou WebP.'
    return
  }

  const maxBytes = 12 * 1024 * 1024
  if (file.size > maxBytes) {
    error.value = 'A imagem deve ter no máximo 12 MB.'
    return
  }

  uploading.value = true
  try {
    if (lastUploadedUrl.value) {
      await discardPending(lastUploadedUrl.value)
      lastUploadedUrl.value = ''
    }

    const body = new FormData()
    body.append('file', file)
    body.append('scope', props.scope)
    body.append('preset', props.preset)

    const result = await api<{ url: string; reused?: boolean }>('/admin/uploads/image', {
      method: 'POST',
      body
    })

    lastUploadedUrl.value = result.reused ? '' : result.url
    libraryLoaded.value = false
    emit('update:modelValue', result.url)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao enviar a imagem.'
  } finally {
    uploading.value = false
    if (input.value) input.value.value = ''
  }
}

function onFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  void upload(target.files?.[0])
}

function onDrop(event: DragEvent) {
  dragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) void upload(file)
}

async function remove() {
  error.value = ''
  if (lastUploadedUrl.value && value.value === lastUploadedUrl.value) {
    const pending = lastUploadedUrl.value
    lastUploadedUrl.value = ''
    emit('update:modelValue', '')
    await discardPending(pending)
    return
  }
  emit('update:modelValue', '')
}

async function onExternalInput(event: Event) {
  const next = (event.target as HTMLInputElement).value
  if (lastUploadedUrl.value && next !== lastUploadedUrl.value) {
    const pending = lastUploadedUrl.value
    lastUploadedUrl.value = ''
    await discardPending(pending)
  }
  emit('update:modelValue', next)
}

async function loadLibrary(force = false) {
  if (libraryLoaded.value && !force) return

  libraryLoading.value = true
  libraryError.value = ''
  try {
    const result = await api<{ data: LibraryImage[] }>(`/admin/uploads/images?preset=${encodeURIComponent(props.preset)}`)
    libraryImages.value = result.data || []
    libraryLoaded.value = true
  } catch (e) {
    libraryError.value = e instanceof Error ? e.message : 'Falha ao carregar as imagens já enviadas.'
  } finally {
    libraryLoading.value = false
  }
}

async function toggleLibrary() {
  libraryOpen.value = !libraryOpen.value
  if (libraryOpen.value) await loadLibrary()
}

async function selectLibraryImage(item: LibraryImage) {
  const next = String(item.url || '').trim()
  if (!next) return

  if (lastUploadedUrl.value && next !== lastUploadedUrl.value) {
    const pending = lastUploadedUrl.value
    lastUploadedUrl.value = ''
    await discardPending(pending)
  }

  emit('update:modelValue', next)
  libraryOpen.value = false
}

function formatBytes(bytes: number) {
  if (!bytes || bytes < 1024) return `${Math.max(0, bytes || 0)} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

onBeforeUnmount(() => {
  if (lastUploadedUrl.value) {
    void discardPending(lastUploadedUrl.value)
  }
})
</script>

<template>
  <div class="image-upload-field">
    <input
      ref="input"
      :id="props.id"
      class="image-upload-native"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      @change="onFileChange"
    >

    <div
      class="image-upload-dropzone"
      :class="{ 'is-dragging': dragging, 'has-file': Boolean(value) }"
      @dragenter.prevent="dragging = true"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <div v-if="value && preview && looksLikeImage" class="image-upload-preview">
        <img :src="preview" alt="Pré-visualização da imagem selecionada">
      </div>
      <div v-else class="image-upload-placeholder" aria-hidden="true">
        <span>IMG</span>
      </div>

      <div class="image-upload-copy">
        <strong>{{ value ? (looksLikeImage ? 'Imagem definida' : 'Mídia externa definida') : 'Envie ou reutilize uma imagem' }}</strong>
        <span>Arraste um arquivo aqui, selecione no dispositivo ou reutilize uma imagem compatível já enviada. JPG, PNG ou WebP, até 12 MB.</span>
        <small v-if="value && !looksLikeImage">Mídia externa informada. Você pode substituí-la por um upload.</small>
      </div>

      <div class="image-upload-actions">
        <button class="secondary-button compact" type="button" :disabled="uploading" @click="openPicker">
          {{ uploading ? 'Enviando…' : (value ? 'Enviar outra' : 'Selecionar imagem') }}
        </button>
        <button class="secondary-button compact" type="button" :disabled="uploading" @click="toggleLibrary">
          {{ libraryOpen ? 'Fechar biblioteca' : 'Usar existente' }}
        </button>
        <button v-if="value" class="danger-button compact" type="button" :disabled="uploading" @click="remove">
          Remover
        </button>
      </div>
    </div>

    <p class="image-upload-guidance">{{ guidance }}</p>
    <p v-if="error" class="image-upload-error" role="alert">{{ error }}</p>

    <div v-if="libraryOpen" class="image-library-picker">
      <div class="image-library-heading">
        <div>
          <strong>Imagens já enviadas</strong>
          <span>Para padrões com tamanho fixo, aparecem somente arquivos com a mesma dimensão final deste campo.</span>
        </div>
        <button class="secondary-button compact" type="button" :disabled="libraryLoading" @click="loadLibrary(true)">
          {{ libraryLoading ? 'Atualizando…' : 'Atualizar' }}
        </button>
      </div>

      <p v-if="libraryError" class="image-upload-error" role="alert">{{ libraryError }}</p>
      <p v-else-if="libraryLoading" class="image-library-empty">Carregando biblioteca…</p>
      <p v-else-if="!libraryImages.length" class="image-library-empty">Nenhuma imagem compatível foi encontrada. O próximo upload ficará disponível aqui para reutilização.</p>
      <div v-else class="image-library-grid">
        <button
          v-for="item in libraryImages"
          :key="item.id"
          class="image-library-item"
          :class="{ 'is-selected': value === item.url }"
          type="button"
          @click="selectLibraryImage(item)"
        >
          <span class="image-library-thumb"><img :src="publicImageUrl(item.url)" :alt="item.alt_text || item.original_name" loading="lazy"></span>
          <span class="image-library-meta">
            <strong>{{ item.original_name }}</strong>
            <small>{{ item.width && item.height ? `${item.width} × ${item.height}px · ` : '' }}{{ formatBytes(item.size_bytes) }}</small>
          </span>
          <span class="image-library-action">{{ value === item.url ? 'Em uso' : 'Usar' }}</span>
        </button>
      </div>
    </div>

    <div v-if="props.allowExternal && (!value || !looksLikeImage)" class="image-upload-external">
      <span>ou use uma URL externa para vídeo/mídia remota</span>
      <input
        :value="value"
        type="url"
        :required="props.required && !value"
        placeholder="https://…"
        @input="onExternalInput"
      >
    </div>
  </div>
</template>
