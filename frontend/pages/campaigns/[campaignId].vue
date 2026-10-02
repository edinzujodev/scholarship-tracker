<script setup lang="ts">
// Access is checked before this page renders (format, existence, authorization).
// Failures render error.vue ("Page not accessible") with the matching status code.
definePageMeta({ middleware: ['campaign-access'] })

const route = useRoute()
const campaignId = computed(() => String(route.params.campaignId))

const { t } = useI18n()
usePageTitle(() => t('review.pageTitle'))
</script>

<template>
  <PageContainer>
    <BackBreadcrumb />
    <ApplicantReviewBoard :campaign-id="campaignId" />
  </PageContainer>
</template>
