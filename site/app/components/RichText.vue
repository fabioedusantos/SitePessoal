<script setup lang="ts">
import { safeRichText } from '~/utils/richText'

const props = withDefaults(defineProps<{
  value?: string | null
  as?: string
}>(), {
  value: '',
  as: 'span'
})

const html = computed(() => safeRichText(props.value))
</script>

<template>
  <component :is="as" v-if="value" class="rich-text" v-html="html" />
</template>
