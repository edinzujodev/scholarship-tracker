<script setup lang="ts">
const { t } = useI18n()
usePageTitle(() => t('campaigns.pageTitle'))
</script>

<template>
  <PageContainer>
    <CampaignList />
  </PageContainer>
</template>
