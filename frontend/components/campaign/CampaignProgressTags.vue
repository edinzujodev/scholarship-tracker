<script setup lang="ts">
/** How far the review of one campaign has got: pending, shortlisted and rejected counts. */
defineProps<{
  pending: number
  shortlisted: number
  rejected: number
}>()
</script>

<template>
  <div class="flex flex-wrap gap-2 text-xs">
    <Tag :value="$t('campaigns.progress.pending', { count: pending })" severity="secondary" rounded />
    <Tag :value="$t('campaigns.progress.shortlisted', { count: shortlisted })" severity="success" rounded />
    <Tag :value="$t('campaigns.progress.rejected', { count: rejected })" severity="danger" rounded />
  </div>
</template>
