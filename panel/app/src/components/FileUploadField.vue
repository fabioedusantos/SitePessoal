<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { api } from '../lib/api'

const props = withDefaults(defineProps<{
  modelValue?: string | null
  id?: string
  scope: string
  allowExternal?: boolean
  required?: boolean
}>(), {
  modelValue: '',
  allowExternal: true,
  required: false
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const input = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const dragging = ref(false)
const error = ref('')
const lastUploadedUrl = ref('')
const apiBase = import.meta.env.VITE_API_BASE || 'http://localhost:8080/api/v1'
const apiOrigin = apiBase.replace(/\/api\/v1\/?$/, '')
const siteBase = import.meta.env.VITE_SITE_URL || (import.meta.env.DEV ? 'http://localhost:3000' : 'https://fbsantos.com.br')

const value = computed(() => String(props.modelValue || '').trim())
const publicUrl = computed(() => {
  if (!value.value) return ''
  if (/^https?:\/\//i.test(value.value)) return value.value
  if (value.value.startsWith('/uploads/')) return `${apiOrigin}${value.value}`
  if (value.value.startsWith('/')) return `${siteBase}${value.value}`
  return value.value
})
const isImage = computed(() => /\.(jpe?g|png|webp)(?:\?.*)?$/i.test(value.value))
const isPdf = computed(() => /\.pdf(?:\?.*)?$/i.test(value.value))

function openPicker() { input.value?.click() }

async function discardPending(url: string) {
  if (!url) return
  try {
    await api('/admin/uploads/file', { method: 'DELETE', body: JSON.stringify({ url }) })
  } catch {
    // O backend também limpa uploads antigos quando o registro é salvo/excluído.
  }
}

async function upload(file?: File) {
  if (!file) return
  error.value = ''
  const allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']
  if (file.type && !allowed.includes(file.type)) {
    error.value = 'Envie JPG, PNG, WebP ou PDF.'
    return
  }
  if (file.size > 12 * 1024 * 1024) {
    error.value = 'O arquivo deve ter no máximo 12 MB.'
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
    const result = await api<{ url: string }>('/admin/uploads/file', { method: 'POST', body })
    lastUploadedUrl.value = result.url
    emit('update:modelValue', result.url)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao enviar o arquivo.'
  } finally {
    uploading.value = false
    if (input.value) input.value.value = ''
  }
}

function onFileChange(event: Event) {
  void upload((event.target as HTMLInputElement).files?.[0])
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

onBeforeUnmount(() => {
  if (lastUploadedUrl.value) void discardPending(lastUploadedUrl.value)
})
</script>

<template>
  <div class="image-upload-field document-upload-field">
    <input ref="input" :id="props.id" class="image-upload-native" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" @change="onFileChange">

    <div
      class="image-upload-dropzone document-upload-dropzone"
      :class="{ 'is-dragging': dragging, 'has-file': Boolean(value) }"
      @dragenter.prevent="dragging = true"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <div v-if="value && isImage" class="image-upload-preview"><img :src="publicUrl" alt="Pré-visualização do certificado"></div>
      <div v-else class="image-upload-placeholder document-upload-placeholder" aria-hidden="true"><span>{{ isPdf ? 'PDF' : 'DOC' }}</span></div>

      <div class="image-upload-copy">
        <strong>{{ value ? 'Comprovante definido' : 'Enviar certificado ou comprovante' }}</strong>
        <span>Arraste um JPG, PNG, WebP ou PDF aqui, ou selecione no dispositivo. Até 12 MB.</span>
        <a v-if="value && publicUrl" class="document-upload-open" :href="publicUrl" target="_blank" rel="noopener noreferrer">Abrir arquivo atual ↗</a>
      </div>

      <div class="image-upload-actions">
        <button class="secondary-button compact" type="button" :disabled="uploading" @click="openPicker">{{ uploading ? 'Enviando…' : (value ? 'Substituir' : 'Selecionar arquivo') }}</button>
        <button v-if="value" class="danger-button compact" type="button" :disabled="uploading" @click="remove">Remover</button>
      </div>
    </div>

    <p class="image-upload-guidance">Comprovantes e certificados não seguem proporção visual do site. O arquivo é preservado sem recorte ou redimensionamento.</p>
    <p v-if="error" class="image-upload-error" role="alert">{{ error }}</p>

    <div v-if="props.allowExternal" class="image-upload-external">
      <span>ou informe um link externo para o certificado/credencial</span>
      <input :value="value" type="url" :required="props.required && !value" placeholder="https://…" @input="onExternalInput">
    </div>
  </div>
</template>
