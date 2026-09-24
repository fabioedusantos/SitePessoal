<script setup lang="ts">
interface ContactResponse { message: string }
interface HCaptchaApi { render: (container: string | HTMLElement, options: Record<string, unknown>) => string | number; reset: (widgetId?: string | number) => void }
declare global { interface Window { hcaptcha?: HCaptchaApi } }

const api = usePublicApi()
const config = useRuntimeConfig()
const form = reactive({ name:'', email:'', subject:'', message:'', website:'' })
const captchaToken = ref('')
const captchaWidget = ref<string | number | null>(null)
const submitting = ref(false)
const feedback = ref<{type:'success'|'error'|''; message:string}>({ type:'', message:'' })
const instance = getCurrentInstance()
const slotId = `contact-hcaptcha-${instance?.uid ?? 0}`

useHead({ script: [{ src:'https://js.hcaptcha.com/1/api.js?render=explicit', async:true, defer:true }] })

function renderCaptcha() {
  if (!import.meta.client || !window.hcaptcha || captchaWidget.value !== null) return
  const container = document.getElementById(slotId)
  if (!container) return
  captchaWidget.value = window.hcaptcha.render(container, {
    sitekey: config.public.hcaptchaSiteKey,
    theme: 'light',
    callback: (token:string) => { captchaToken.value = token },
    'expired-callback': () => { captchaToken.value = '' },
    'error-callback': () => { captchaToken.value = '' }
  })
}

onMounted(() => {
  let attempts=0
  const timer=window.setInterval(() => {
    attempts += 1; renderCaptcha()
    if (captchaWidget.value !== null || attempts >= 50) window.clearInterval(timer)
  }, 100)
})

async function submit() {
  feedback.value={type:'',message:''}
  if (!captchaToken.value) { feedback.value={type:'error',message:'Conclua a verificação do hCaptcha antes de enviar.'}; return }
  submitting.value=true
  try {
    const result=await api<ContactResponse>('/public/contact',{ method:'POST', body:{...form,hcaptcha_token:captchaToken.value}, timeout:25000, retry:0 })
    feedback.value={type:'success',message:result.message}
    Object.assign(form,{name:'',email:'',subject:'',message:'',website:''}); captchaToken.value=''
    if(window.hcaptcha && captchaWidget.value!==null) window.hcaptcha.reset(captchaWidget.value)
  } catch(error:any) {
    feedback.value={type:'error',message:error?.data?.message || 'Não foi possível enviar sua mensagem agora. Tente novamente.'}
    captchaToken.value=''
    if(window.hcaptcha && captchaWidget.value!==null) window.hcaptcha.reset(captchaWidget.value)
  } finally { submitting.value=false }
}
</script>

<template>
  <form class="contact-form" @submit.prevent="submit">
    <div class="form-grid">
      <label><span>Nome</span><input v-model.trim="form.name" autocomplete="name" maxlength="120" required placeholder="Seu nome"></label>
      <label><span>E-mail</span><input v-model.trim="form.email" type="email" autocomplete="email" maxlength="190" required placeholder="voce@empresa.com.br"></label>
    </div>
    <label><span>Assunto</span><input v-model.trim="form.subject" maxlength="180" required placeholder="O que você gostaria de conversar?"></label>
    <label><span>Mensagem</span><textarea v-model.trim="form.message" minlength="20" maxlength="5000" rows="8" required placeholder="Conte um pouco do contexto, oportunidade ou projeto..." /></label>
    <label class="form-honeypot" aria-hidden="true"><span>Website</span><input v-model="form.website" tabindex="-1" autocomplete="off"></label>
    <div :id="slotId" class="hcaptcha-slot" />
    <div v-if="feedback.message" class="form-feedback" :class="`is-${feedback.type}`" role="status">{{ feedback.message }}</div>
    <div class="contact-form-footer"><p>Protegido por hCaptcha e limite de requisições. Seus dados são usados somente para responder ao contato.</p><button class="btn btn-primary" type="submit" :disabled="submitting">{{ submitting ? 'Enviando...' : 'Enviar mensagem →' }}</button></div>
  </form>
</template>
