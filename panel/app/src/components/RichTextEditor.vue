<script setup lang="ts">
import { nextTick, reactive, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  modelValue?: string | null
  placeholder?: string
  minHeight?: number
  id?: string
}>(), {
  modelValue: '',
  placeholder: 'Escreva o conteúdo…',
  minHeight: 150
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const editor = ref<HTMLElement | null>(null)
const focused = ref(false)
const active = reactive({ bold: false, italic: false, underline: false })

function escapeHtml(value: string) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
}

function toEditorHtml(value: string) {
  let html = escapeHtml(value || '')
  html = html
    .replace(/&lt;(strong|em|u)&gt;/gi, '<$1>')
    .replace(/&lt;\/(strong|em|u)&gt;/gi, '</$1>')
    .replace(/&lt;br\s*\/?&gt;/gi, '<br>')
    .replace(/\r?\n/g, '<br>')
  return html
}

function serializeNode(node: Node): string {
  if (node.nodeType === Node.TEXT_NODE) return escapeHtml(node.textContent || '')
  if (!(node instanceof HTMLElement)) return ''

  const content = Array.from(node.childNodes).map(serializeNode).join('')
  const tag = node.tagName.toLowerCase()

  if (tag === 'br') return '<br>'
  if (tag === 'strong' || tag === 'b') return `<strong>${content}</strong>`
  if (tag === 'em' || tag === 'i') return `<em>${content}</em>`
  if (tag === 'u') return `<u>${content}</u>`
  if (tag === 'div' || tag === 'p') return `${content}<br>`
  return content
}

function normalizedValue() {
  if (!editor.value) return ''
  return Array.from(editor.value.childNodes)
    .map(serializeNode)
    .join('')
    .replace(/(?:<br>\s*){3,}/g, '<br><br>')
    .replace(/(?:<br>)+$/g, '')
}

function syncFromModel(value: string) {
  if (!editor.value || focused.value) return
  const html = toEditorHtml(value)
  if (editor.value.innerHTML !== html) editor.value.innerHTML = html
}

function updateModel() {
  emit('update:modelValue', normalizedValue())
  refreshState()
}

function refreshState() {
  if (typeof document === 'undefined') return
  active.bold = document.queryCommandState('bold')
  active.italic = document.queryCommandState('italic')
  active.underline = document.queryCommandState('underline')
}

function format(command: 'bold' | 'italic' | 'underline' | 'removeFormat') {
  editor.value?.focus()
  document.execCommand(command, false)
  updateModel()
}

function onFocus() {
  focused.value = true
  refreshState()
}

function onBlur() {
  focused.value = false
  updateModel()
}

function onPaste(event: ClipboardEvent) {
  event.preventDefault()
  const text = event.clipboardData?.getData('text/plain') || ''
  document.execCommand('insertText', false, text)
  updateModel()
}

watch(
  () => props.modelValue,
  async value => {
    await nextTick()
    syncFromModel(value || '')
  },
  { immediate: true }
)
</script>

<template>
  <div class="rich-editor" :class="{ 'is-focused': focused }">
    <div class="rich-editor-toolbar" aria-label="Formatação do texto">
      <button
        type="button"
        class="rich-editor-button rich-editor-button-bold"
        :class="{ active: active.bold }"
        aria-label="Negrito"
        title="Negrito (Ctrl+B)"
        @mousedown.prevent="format('bold')"
      >B</button>
      <button
        type="button"
        class="rich-editor-button rich-editor-button-italic"
        :class="{ active: active.italic }"
        aria-label="Itálico"
        title="Itálico (Ctrl+I)"
        @mousedown.prevent="format('italic')"
      >I</button>
      <button
        type="button"
        class="rich-editor-button rich-editor-button-underline"
        :class="{ active: active.underline }"
        aria-label="Sublinhado"
        title="Sublinhado (Ctrl+U)"
        @mousedown.prevent="format('underline')"
      >U</button>
      <span class="rich-editor-divider" aria-hidden="true" />
      <button
        type="button"
        class="rich-editor-button rich-editor-clear"
        aria-label="Limpar formatação"
        title="Limpar formatação do trecho selecionado"
        @mousedown.prevent="format('removeFormat')"
      >Limpar</button>
      <span class="rich-editor-hint">Selecione um trecho para formatar</span>
    </div>

    <div
      ref="editor"
      :id="id"
      class="rich-editor-content"
      :style="{ minHeight: `${minHeight}px` }"
      :data-placeholder="placeholder"
      contenteditable="true"
      role="textbox"
      aria-multiline="true"
      spellcheck="true"
      @input="updateModel"
      @focus="onFocus"
      @blur="onBlur"
      @keyup="refreshState"
      @mouseup="refreshState"
      @paste="onPaste"
    />
  </div>
</template>
