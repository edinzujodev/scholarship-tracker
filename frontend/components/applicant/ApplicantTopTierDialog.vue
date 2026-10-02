<script setup lang="ts">
import type { CampaignSummary } from '~/types/applicant'

/** The best applicants of the campaign, with a shortcut to list all of them. */
const visible = defineModel<boolean>('visible', { required: true })

defineProps<{
  summary: CampaignSummary
  topTierScore: number | null
}>()

defineEmits<{ showAll: [] }>()
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="$t('review.topTier.title')" class="w-full max-w-xl">
    <div class="flex flex-col gap-4">
      <p class="text-sm text-slate-600">
        {{ $t('review.topTier.intro', { count: summary.topTierCount, score: topTierScore ?? '' }, summary.topTierCount) }}
        <template v-if="summary.topTierCount > summary.topTier.length">{{ $t('review.topTier.showingTop', { count: summary.topTier.length }) }}</template>
      </p>
      <ol class="flex flex-col divide-y divide-slate-100" data-testid="top-tier-list">
        <li v-for="(person, i) in summary.topTier" :key="person.applicationId" class="flex items-start gap-3 py-3">
          <span class="w-5 pt-0.5 text-sm font-semibold tabular-nums text-slate-400">{{ i + 1 }}</span>
          <div class="min-w-0 flex-1">
            <p class="font-medium text-slate-900">{{ person.name }}</p>
            <ul class="mt-1 list-disc pl-5 text-sm text-slate-600">
              <li v-for="(highlight, j) in person.aiHighlights" :key="j">{{ highlight }}</li>
            </ul>
          </div>
          <ApplicantScoreBadge :score="person.aiScore" tier="top" />
        </li>
      </ol>
      <Button
        :label="$t('review.topTier.showAll', { count: summary.topTierCount })"
        icon="pi pi-list"
        size="small"
        outlined
        class="self-start"
        data-testid="top-tier-show-all"
        @click="$emit('showAll')"
      />
    </div>
  </Dialog>
</template>
