<script setup lang="ts">
import type { Experience } from '~/types/content'
defineProps<{ items: Experience[]; compact?: boolean }>()
const { period } = useFormatters()
const mediaUrl = useMediaUrl()

const isFbsantos = (item: Experience) => item.organization_slug === 'fbsantos-desenvolvimentos' || item.organization === 'Fábio Santos Desenvolvimentos'
const organizationImage = (item: Experience) => item.organization_image_url
</script>

<template>
  <div class="timeline">
    <article v-for="item in items" :key="item.id" class="timeline-item">
      <div class="timeline-marker" aria-hidden="true" />
      <div class="timeline-meta">
        <span>{{ period(item.start_date, item.end_date, item.is_current) }}</span>
        <span>{{ item.location }}</span>
      </div>
      <div class="timeline-content">
        <div class="timeline-org-row">
          <div
            v-if="organizationImage(item)"
            class="timeline-org-logo"
            :class="{ 'is-fbsantos': isFbsantos(item) }"
          >
            <img :src="mediaUrl(organizationImage(item))" :alt="item.organization" loading="lazy">
          </div>
          <p class="timeline-org">{{ item.organization }}</p>
        </div>
        <h3>{{ item.role_title }}</h3>
        <RichText as="p" class="timeline-summary" :value="item.summary" />
        <RichText
          v-if="!compact && item.description"
          as="div"
          class="timeline-description"
          :value="item.description"
        />
        <ul v-if="!compact && item.highlights?.length" class="chip-list">
          <li v-for="highlight in item.highlights" :key="highlight">{{ highlight }}</li>
        </ul>
        <div v-if="!compact && item.technologies?.length" class="timeline-technologies">
          <span v-for="technology in item.technologies" :key="technology.id">{{ technology.name }}</span>
        </div>
      </div>
    </article>
  </div>
</template>
