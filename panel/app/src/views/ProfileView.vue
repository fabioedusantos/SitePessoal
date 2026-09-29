<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { api } from '../lib/api'

const form = reactive({
  full_name: '',
  professional_title: '',
  headline: '',
  summary: '',
  about_short: '',
  location: '',
  email: '',
  phone: ''
})

const loading = ref(true)
const saving = ref(false)
const message = ref('')
const error = ref('')

onMounted(async () => {
  try {
    const result = await api<{ data: typeof form }>('/admin/profile')
    Object.assign(form, result.data)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao carregar apresentação.'
  } finally {
    loading.value = false
  }
})

async function save() {
  if (!form.full_name.trim() || !form.professional_title.trim() || !form.headline.trim() || !form.summary.trim()) {
    error.value = 'Preencha os campos obrigatórios antes de salvar.'
    return
  }

  saving.value = true
  error.value = ''
  message.value = ''

  try {
    await api('/admin/profile', {
      method: 'PUT',
      body: JSON.stringify(form)
    })
    message.value = 'Apresentação atualizada com sucesso.'
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Falha ao salvar.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="profile-page">
    <header class="page-title page-title-editor">
      <div>
        <p class="overline">Apresentação</p>
        <h1>Identidade profissional</h1>
        <p>Informações centrais usadas na apresentação profissional e em diferentes áreas do site.</p>
      </div>
      <span class="edit-status-badge">Perfil público</span>
    </header>

    <div v-if="error" class="alert" role="alert">{{ error }}</div>
    <div v-if="message" class="success" role="status">{{ message }}</div>

    <div v-if="loading" class="editor-loading" aria-live="polite">
      <div class="editor-loading-line" />
      <div class="editor-loading-block" />
      <div class="editor-loading-block short" />
    </div>

    <form v-else class="resource-editor-form" @submit.prevent="save">
      <section class="editor-section">
        <header class="editor-section-heading">
          <div>
            <p class="section-kicker">Identificação</p>
            <p>Nome, posicionamento profissional e localização exibidos publicamente.</p>
          </div>
        </header>
        <div class="form-grid">
          <div class="form-field">
            <label class="field-label" for="profile-name">Nome completo <b>*</b></label>
            <input id="profile-name" v-model="form.full_name" required>
          </div>
          <div class="form-field">
            <label class="field-label" for="profile-location">Localização</label>
            <input id="profile-location" v-model="form.location" placeholder="Bauru · SP">
          </div>
          <div class="form-field wide">
            <label class="field-label" for="profile-title">Título profissional <b>*</b></label>
            <input id="profile-title" v-model="form.professional_title" required>
            <small class="field-help">Use uma descrição objetiva do seu posicionamento profissional.</small>
          </div>
          <div class="form-field wide">
            <label class="field-label" for="profile-headline">Headline <b>*</b></label>
            <textarea id="profile-headline" v-model="form.headline" rows="3" required />
            <small class="field-help">Texto curto para apresentação e contextos em que o espaço é limitado.</small>
          </div>
        </div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading">
          <div>
            <p class="section-kicker">Narrativa profissional</p>
            <p>Textos mais completos usados na apresentação profissional.</p>
          </div>
        </header>
        <div class="form-grid">
          <div class="form-field wide">
            <label class="field-label">Resumo profissional <b>*</b></label>
            <textarea v-model="form.summary" rows="7" placeholder="Resumo da atuação profissional…" required />
          </div>
          <div class="form-field wide">
            <label class="field-label">Sobre — versão curta</label>
            <textarea v-model="form.about_short" rows="6" placeholder="Versão curta da apresentação pessoal…" />
          </div>
        </div>
      </section>

      <section class="editor-section">
        <header class="editor-section-heading">
          <div>
            <p class="section-kicker">Contato público</p>
            <p>Dados de contato que podem aparecer em áreas públicas do site.</p>
          </div>
        </header>
        <div class="form-grid">
          <div class="form-field">
            <label class="field-label" for="profile-email">E-mail público</label>
            <input id="profile-email" v-model="form.email" type="email" placeholder="nome@dominio.com.br">
          </div>
          <div class="form-field">
            <label class="field-label" for="profile-phone">Telefone público</label>
            <input id="profile-phone" v-model="form.phone" placeholder="(00) 00000-0000">
          </div>
        </div>
      </section>

      <div class="form-actions form-actions-sticky">
        <div class="form-actions-copy">
          <strong>Apresentação profissional</strong>
          <span>Revise os textos antes de publicar alterações.</span>
        </div>
        <div class="form-actions-buttons">
          <button class="primary-button" type="submit" :disabled="saving">
            {{ saving ? 'Salvando…' : 'Salvar apresentação' }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
