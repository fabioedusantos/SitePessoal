<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'

const email = ref('')
const password = ref('')
const loading = ref(false)
const message = ref('')
const router = useRouter()
const { setSession } = useAuth()

async function submit() {
  message.value = ''
  loading.value = true
  try {
    const result = await api<{ csrf_token: string }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email: email.value, password: password.value })
    })
    setSession(result.csrf_token)
    router.push('/')
  } catch (error) {
    message.value = error instanceof Error ? error.message : 'Não foi possível entrar.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login-page">
    <section class="login-card">
      <div class="cms-mark large">FS</div>
      <p class="overline">FBSantos CMS</p>
      <h1>Conteúdo profissional, sem editar código.</h1>
      <p class="login-copy">Gerencie experiências, projetos, tecnologias, formação e publicações.</p>

      <form @submit.prevent="submit">
        <label>
          E-mail
          <input v-model="email" type="email" autocomplete="username" required />
        </label>
        <label>
          Senha
          <input v-model="password" type="password" autocomplete="current-password" required />
        </label>
        <p v-if="message" class="form-error">{{ message }}</p>
        <button class="primary-button" type="submit" :disabled="loading">
          {{ loading ? 'Entrando…' : 'Entrar' }}
        </button>
      </form>
    </section>
  </main>
</template>
