<script setup lang="ts">
import type { ApplicationStatus, ReviewItem } from '~/types/applicant'

const props = defineProps<{
  /**
   * Highlight scores strictly above this value as "standout". Comes from the
   * API's meta.thresholds; omit it to show no standout highlight.
   */
  standoutAbove?: number | null
  /** One application (the applicant's submission to this campaign) with the applicant's name. */
  application: ReviewItem
}>()

const emit = defineEmits<{
  shortlist: [id: string]
  reject: [id: string]
  pending: [id: string]
  viewDetails: [id: string]
}>()

const { formatDay } = useFormatters()

// Derived from props, never copied into local state: the store is the single
// source of truth, so optimistic changes and rollbacks show up automatically.
const attrs = computed(() => props.application)
const standout = computed(() => props.standoutAbove != null && attrs.value.aiScore > props.standoutAbove)
const headingId = computed(() => `application-${props.application.id}-name`)
const appliedOn = computed(() => formatDay(attrs.value.appliedAt))

type ActionEvent = 'shortlist' | 'reject' | 'pending'
const ACTIONS: { status: ApplicationStatus; icon: string; severity: 'success' | 'danger' | 'secondary'; event: ActionEvent }[] = [
  { status: 'shortlisted', icon: 'pi pi-check', severity: 'success', event: 'shortlist' },
  { status: 'rejected', icon: 'pi pi-times', severity: 'danger', event: 'reject' },
  { status: 'pending', icon: 'pi pi-undo', severity: 'secondary', event: 'pending' },
]

function onAction(event: ActionEvent) {
  // Separate calls keep defineEmits' per-event typing intact.
  if (event === 'shortlist') emit('shortlist', props.application.id)
  else if (event === 'reject') emit('reject', props.application.id)
  else emit('pending', props.application.id)
}
</script>

<template>
  <article
    :aria-labelledby="headingId"
    data-testid="applicant-card"
    :data-application-id="application.id"
    :data-status="attrs.status"
    :data-standout="standout"
    class="flex w-full flex-col gap-4 rounded-xl border bg-white p-5 shadow-sm transition-[opacity,box-shadow]"
    :class="[
      standout ? 'border-emerald-300 bg-gradient-to-b from-emerald-50/70 to-white ring-2 ring-emerald-400/60' : 'border-slate-200',
      { 'opacity-60': attrs.status === 'rejected' },
    ]"
  >
    <header class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p v-if="standout" class="mb-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-emerald-700" data-testid="standout-label">
          <i class="pi pi-star-fill text-[0.7rem]" aria-hidden="true" /> {{ $t('card.standout', { score: standoutAbove }) }}
        </p>
        <h3 :id="headingId" class="truncate text-base font-semibold text-slate-900" data-testid="applicant-name">
          {{ attrs.name }}
        </h3>
        <p class="mt-0.5 text-sm text-slate-500">
          {{ $t('card.applied') }} <time :datetime="attrs.appliedAt" data-testid="applied-at">{{ appliedOn }}</time>
          <span v-if="!attrs.eligible" class="ml-2 text-xs font-medium text-rose-700">{{ $t('card.notEligible') }}</span>
        </p>
        <div class="mt-2" aria-live="polite" data-testid="status">
          <ApplicantStatusTag :status="attrs.status" />
        </div>
      </div>
      <ApplicantScoreBadge :score="attrs.aiScore" :tier="attrs.tier" data-testid="score-badge" />
    </header>

    <section :aria-label="$t('card.highlights')">
      <ul v-if="attrs.aiHighlights.length" class="list-disc space-y-1 pl-5 text-sm text-slate-700" data-testid="highlights">
        <li v-for="(highlight, i) in attrs.aiHighlights" :key="i">{{ highlight }}</li>
      </ul>
      <p v-else class="text-sm italic text-slate-500">{{ $t('card.noHighlights') }}</p>
    </section>

    <div class="mt-auto flex flex-col gap-3">
      <div
        class="flex flex-nowrap items-center gap-2"
        role="group"
        :aria-label="$t('card.decisionGroup', { name: attrs.name })"
        data-testid="status-actions"
      >
        <Button
          v-for="action in ACTIONS"
          :key="action.status"
          :label="$t(`card.action.${action.status}`)"
          :icon="action.icon"
          size="small"
          :severity="action.severity"
          :outlined="attrs.status !== action.status"
          :disabled="attrs.status === action.status"
          :aria-pressed="attrs.status === action.status"
          :aria-label="$t('card.actionFor', { action: $t(`card.action.${action.status}`), name: attrs.name })"
          :data-testid="`${action.event}-button`"
          class="flex-1 whitespace-nowrap"
          @click="onAction(action.event)"
        />
      </div>

      <hr class="border-slate-200" >

      <Button
        :label="$t('card.viewDetails')"
        icon="pi pi-arrow-right"
        icon-pos="right"
        size="small"
        text
        class="self-start"
        :aria-label="$t('card.viewDetailsFor', { name: attrs.name })"
        data-testid="details-button"
        @click="emit('viewDetails', application.id)"
      />
    </div>
  </article>
</template>
