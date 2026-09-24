<script setup lang="ts">
const props = defineProps<{
  error: {
    statusCode?: number
    statusMessage?: string
    message?: string
  }
}>()

const route = useRoute()
const statusCode = computed(() => Number(props.error?.statusCode || 500))
const isNotFound = computed(() => statusCode.value === 404)

const title = computed(() => isNotFound.value ? 'Página não encontrada' : 'Não foi possível carregar esta página')
const description = computed(() => isNotFound.value
  ? 'O endereço que você tentou acessar não existe ou pode ter sido alterado.'
  : 'Ocorreu um erro inesperado ao carregar este conteúdo. Você pode tentar novamente ou voltar ao início.'
)

useHead(() => ({
  title: `${statusCode.value} | ${title.value} | FBSantos`,
  meta: [
    { name: 'robots', content: 'noindex, nofollow' }
  ]
}))

const goHome = () => clearError({ redirect: '/' })
const retry = () => clearError({ redirect: route.fullPath || '/' })
</script>

<template>
  <div class="app-shell">
    <SiteHeader />

    <main class="error-page">
      <div class="container error-page-inner">
        <section class="error-content" aria-labelledby="error-title">
          <p class="eyebrow">Erro {{ statusCode }}</p>

          <div class="error-code" aria-hidden="true">{{ statusCode }}</div>

          <h1 id="error-title">{{ title }}</h1>
          <p class="error-description">{{ description }}</p>

          <div class="error-actions">
            <button v-if="!isNotFound" type="button" class="btn btn-primary" @click="retry">
              Tentar novamente
            </button>
            <button type="button" class="btn" :class="isNotFound ? 'btn-primary' : 'btn-secondary'" @click="goHome">
              Voltar ao início
            </button>
          </div>
        </section>

        <div class="error-visual" aria-hidden="true">
          <div class="error-orbit error-orbit-large" />
          <div class="error-orbit error-orbit-small" />
          <img src="/brand/fbsantos-mark.svg" alt="" width="148" height="148">
        </div>
      </div>
    </main>

    <SiteFooter />
  </div>
</template>

<style scoped>
.error-page {
  flex: 1 0 auto;
  width: 100%;
  display: flex;
  align-items: center;
  min-height: clamp(560px, calc(100svh - 78px), 860px);
  overflow: hidden;
}

.error-page-inner {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(300px, .9fr);
  gap: clamp(48px, 9vw, 120px);
  align-items: center;
  padding-block: clamp(72px, 10vw, 128px);
}

.error-content {
  position: relative;
  z-index: 1;
  max-width: 720px;
}

.error-code {
  margin: -6px 0 10px;
  color: transparent;
  background: linear-gradient(110deg, #ffffff 4%, #72c7ff 54%, #0c96fb 100%);
  background-clip: text;
  -webkit-background-clip: text;
  font-family: var(--font-display);
  font-size: clamp(6rem, 15vw, 11rem);
  font-weight: 800;
  line-height: .78;
  letter-spacing: -.085em;
}

.error-content h1 {
  max-width: 680px;
  margin: 28px 0 18px;
  font-size: clamp(2.35rem, 5vw, 4.7rem);
  line-height: .98;
  letter-spacing: -.055em;
  text-wrap: balance;
}

.error-description {
  max-width: 650px;
  margin: 0;
  color: var(--muted);
  font-size: clamp(1rem, 1.6vw, 1.16rem);
}

.error-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 32px;
}

.error-actions .btn {
  cursor: pointer;
}

.error-visual {
  position: relative;
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  min-height: 330px;
}

.error-visual::before {
  content: "";
  position: absolute;
  width: 72%;
  aspect-ratio: 1;
  border-radius: 50%;
  background: rgba(12, 150, 251, .14);
  filter: blur(48px);
}

.error-visual img {
  position: relative;
  z-index: 2;
  width: clamp(96px, 12vw, 148px);
  filter: drop-shadow(0 22px 34px rgba(0, 0, 0, .24));
}

.error-orbit {
  position: absolute;
  border: 1px solid rgba(114, 199, 255, .16);
  border-radius: 50%;
}

.error-orbit-large {
  inset: 5%;
}

.error-orbit-small {
  inset: 21%;
  border-color: rgba(12, 150, 251, .24);
}

@media (max-width: 820px) {
  .error-page-inner {
    grid-template-columns: 1fr;
    gap: 30px;
    padding-block: 72px 84px;
  }

  .error-content {
    max-width: 680px;
  }

  .error-code {
    font-size: clamp(5.5rem, 24vw, 9rem);
  }

  .error-visual {
    order: -1;
    min-height: 210px;
    max-width: 310px;
    width: 70%;
    margin-inline: auto;
  }
}

@media (max-width: 520px) {
  .error-page-inner {
    padding-block: 52px 68px;
  }

  .error-content h1 {
    margin-top: 22px;
  }

  .error-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .error-actions .btn {
    width: 100%;
  }
}
</style>
