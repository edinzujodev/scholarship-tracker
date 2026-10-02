<script setup lang="ts">
import type { CampaignSummary } from '~/types/applicant'

/** Campaign-wide figures: always for the whole campaign, never narrowed by the filters. */
defineProps<{
  summary: CampaignSummary
  /** Top-tier score from the API's meta.thresholds (null until known). */
  topTierScore: number | null
}>()

defineEmits<{ openTopTier: [] }>()
</script>

<template>
  <dl class="grid grid-cols-2 gap-3 md:grid-cols-4">
    <BaseStatTile :label="$t('review.summary.applicants')">
      <span data-testid="summary-total">{{ summary.totalApplicants }}</span>
    </BaseStatTile>

    <BaseStatTile :label="$t('review.summary.eligible')">
      {{ summary.eligibleApplicants }}
      <span class="text-sm font-normal text-slate-500">({{ summary.eligibleRate }}%)</span>
    </BaseStatTile>

    <BaseStatTile :label="$t('review.summary.averageScore')">
      <span data-testid="summary-average">{{ summary.averageAiScore.toFixed(1) }}</span>
    </BaseStatTile>

    <BaseStatTile :label="topTierScore === null ? $t('review.summary.topTier') : $t('review.summary.topTierFrom', { score: topTierScore })">
      <span class="flex items-end justify-between gap-2">
        <span data-testid="summary-top-tier-count">{{ summary.topTierCount }}</span>
        <Button
          v-if="summary.topTierCount > 0"
          :label="$t('review.summary.viewAll')"
          size="small"
          text
          icon="pi pi-users"
          data-testid="top-tier-button"
          @click="$emit('openTopTier')"
        />
        <span v-else class="text-sm font-normal text-slate-500">{{ $t('review.summary.noneYet') }}</span>
      </span>
    </BaseStatTile>
  </dl>
</template>
