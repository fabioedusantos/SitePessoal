<script setup lang="ts">
import type { ProjectMedia } from '~/types/content'

const props = defineProps<{ media: ProjectMedia[]; projectName: string }>()
const mediaUrl = useMediaUrl()
const imageMedia = computed(() => props.media.filter(item => !['youtube', 'video'].includes(String(item.kind || '').toLowerCase())))
const videoMedia = computed(() => props.media.filter(item => ['youtube', 'video'].includes(String(item.kind || '').toLowerCase())))
const activeIndex = ref<number | null>(null)
const active = computed(() => activeIndex.value === null ? null : imageMedia.value[activeIndex.value])

const open = (index: number) => { activeIndex.value = index }
const close = () => { activeIndex.value = null }
const next = () => {
  if (activeIndex.value === null || imageMedia.value.length === 0) return
  activeIndex.value = (activeIndex.value + 1) % imageMedia.value.length
}
const previous = () => {
  if (activeIndex.value === null || imageMedia.value.length === 0) return
  activeIndex.value = (activeIndex.value - 1 + imageMedia.value.length) % imageMedia.value.length
}

function youtubeEmbedUrl(value?: string | null) {
  if (!value) return ''
  if (/^https:\/\/www\.youtube-nocookie\.com\/embed\/[A-Za-z0-9_-]+/i.test(value)) return value
  if (/^https:\/\/www\.youtube\.com\/embed\/[A-Za-z0-9_-]+/i.test(value)) return value.replace('www.youtube.com', 'www.youtube-nocookie.com')
  const match = value.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/i)
  return match ? `https://www.youtube-nocookie.com/embed/${match[1]}` : ''
}

const onKeydown = (event: KeyboardEvent) => {
  if (activeIndex.value === null) return
  if (event.key === 'Escape') close()
  if (event.key === 'ArrowRight') next()
  if (event.key === 'ArrowLeft') previous()
}

watch(activeIndex, (value) => {
  if (!import.meta.client) return
  document.body.style.overflow = value === null ? '' : 'hidden'
})

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <div v-if="imageMedia.length" class="project-gallery" :class="{ 'is-tall-phone-gallery': projectName === 'ContaOkay' }">
    <button
      v-for="(item, index) in imageMedia"
      :key="item.id"
      type="button"
      class="project-gallery-item"
      :aria-label="`Ampliar imagem ${index + 1} de ${imageMedia.length} do projeto ${projectName}`"
      @click="open(index)"
    >
      <img :src="mediaUrl(item.media_url)" :alt="item.alt_text" loading="lazy">
      <span class="project-gallery-zoom" aria-hidden="true">↗</span>
      <span v-if="item.caption" class="project-gallery-caption">{{ item.caption }}</span>
    </button>
  </div>

  <div v-if="videoMedia.length" class="project-video-grid gallery-video-grid" :class="{ 'has-multiple': videoMedia.length > 1 }">
    <figure v-for="item in videoMedia" :key="`video-${item.id}`" class="project-video-card">
      <div v-if="youtubeEmbedUrl(item.media_url)" class="video-frame">
        <iframe
          :src="youtubeEmbedUrl(item.media_url)"
          :title="item.caption || item.alt_text || `Vídeo do projeto ${projectName}`"
          loading="lazy"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          allowfullscreen
        />
      </div>
      <video v-else class="project-native-video" :src="mediaUrl(item.media_url)" controls preload="metadata" />
      <figcaption v-if="item.caption">{{ item.caption }}</figcaption>
    </figure>
  </div>

  <Teleport to="body">
    <Transition name="gallery-lightbox">
      <div
        v-if="active"
        class="media-lightbox"
        role="dialog"
        aria-modal="true"
        :aria-label="`Galeria do projeto ${projectName}`"
        @click.self="close"
      >
        <div class="media-lightbox-toolbar">
          <span>{{ (activeIndex ?? 0) + 1 }} / {{ imageMedia.length }}</span>
          <button type="button" aria-label="Fechar galeria" @click="close">×</button>
        </div>

        <button v-if="imageMedia.length > 1" class="media-lightbox-nav is-prev" type="button" aria-label="Imagem anterior" @click="previous">‹</button>
        <figure class="media-lightbox-content">
          <img :src="mediaUrl(active.media_url)" :alt="active.alt_text">
          <figcaption v-if="active.caption">{{ active.caption }}</figcaption>
        </figure>
        <button v-if="imageMedia.length > 1" class="media-lightbox-nav is-next" type="button" aria-label="Próxima imagem" @click="next">›</button>
      </div>
    </Transition>
  </Teleport>
</template>
