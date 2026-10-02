<script setup lang="ts">
import type { ReviewItem } from '~/types/applicant'

/** The selected application in a modal; closing it clears the selection. */
const application = defineModel<ReviewItem | null>('application', { required: true })

const visible = computed({
  get: () => application.value !== null,
  set: open => {
    if (!open) application.value = null
  },
})
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="application?.name" class="w-full max-w-lg">
    <div v-if="application" class="flex flex-col gap-3 text-sm text-slate-700">
      <p>
        {{ $t('review.details.score', { score: application.aiScore }) }} ·
        {{ $t(`tier.${application.tier}`) }} ·
        {{ application.eligible ? $t('common.eligible') : $t('common.notEligible') }}
      </p>
      <ul class="list-disc space-y-1 pl-5">
        <li v-for="(highlight, i) in application.aiHighlights" :key="i">{{ highlight }}</li>
      </ul>
    </div>
  </Dialog>
</template>
