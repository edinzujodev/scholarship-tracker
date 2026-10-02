<script setup lang="ts">
import type { ScoreTier } from '~/types/applicant'

/** AI score pill coloured by the tier the API assigned (never recomputed here). */
defineProps<{
  score: number
  tier: ScoreTier
}>()
</script>

<template>
  <span
    class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-sm font-semibold ring-1 ring-inset"
    :class="TIER_BADGE[tier]"
    :title="$t(`tier.${tier}`)"
    :data-tier="tier"
  >
    {{ score }}<span class="sr-only"> {{ $t('card.scoreForScreenReaders', { tier: $t(`tier.${tier}`) }) }}</span>
  </span>
</template>
