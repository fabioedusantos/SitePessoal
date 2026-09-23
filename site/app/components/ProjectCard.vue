<script setup lang="ts">
import type { Project } from '~/types/content'
defineProps<{ project: Project }>()
const mediaUrl = useMediaUrl()
</script>

<template>
  <NuxtLink :to="`/projetos/${project.slug}`" class="project-card project-card-visual">
    <div v-if="project.cover_image_url" class="project-card-media">
      <img :src="mediaUrl(project.cover_image_url)" :alt="`Projeto ${project.name}`" loading="lazy">
    </div>
    <div class="project-card-content">
      <div class="project-card-top">
        <span class="project-index">CASE</span>
        <span v-if="project.period_label" class="project-period">{{ project.period_label }}</span>
      </div>
      <h3>{{ project.name }}</h3>
      <RichText as="p" :value="project.context" />
      <div class="project-card-bottom">
        <span>{{ project.organization || 'Projeto acadêmico' }}</span>
        <span aria-hidden="true">↗</span>
      </div>
    </div>
  </NuxtLink>
</template>
