<script setup lang="ts">
import { storeToRefs } from 'pinia'
import type { ReviewItem } from '~/types/applicant'
import { PAGE_SIZE_OPTIONS } from '~/stores/applicantReview'

/**
 * Applicant review for one campaign: summary, filters, cards, paging and
 * dialogs. Orchestration only; the pieces live in child components and the
 * logic in the store and composables.
 */
const props = withDefaults(
  defineProps<{
    campaignId: string
    /** Debounce for the score slider, in ms. */
    filterDebounce?: number
  }>(),
  { filterDebounce: 250 },
)

const TOAST_GROUP = 'applicant-review'

const store = useApplicantReviewStore()
const { applications, meta, loading, loadError, loadErrorReference, pageNumber, pageSize, isEmpty, hasActiveFilter } = storeToRefs(store)

const filters = useApplicantFilters(() => props.filterDebounce)
useStatusErrorToast(TOAST_GROUP)

// Fetch during setup so SSR renders real data. Pinia state is serialised into
// the payload, so the client does not refetch on hydration. Skip when a
// request for this campaign is already in flight (avoids a double fetch).
// A different campaign starts from a clean slate (filters, sort, page).
if (store.campaignId !== props.campaignId) {
  store.reset()
  await store.fetchSummary(props.campaignId)
} else if (!store.meta && !store.loading) {
  await store.fetchSummary(props.campaignId, { pageNumber: 1 })
}

watch(
  () => props.campaignId,
  id => {
    store.reset()
    store.fetchSummary(id)
  },
)

const summary = computed(() => meta.value?.summary ?? null)
// Published by the API; never hard-coded in the frontend.
const thresholds = computed(() => meta.value?.thresholds ?? null)
const topTierScore = computed(() => thresholds.value?.topTier ?? null)
const total = computed(() => meta.value?.page.total ?? 0)

// Only show skeletons for the first load; later loads keep the old cards
// visible (dimmed) so the layout does not jump while filters change.
const showSkeleton = computed(() => loading.value && applications.value.length === 0 && !loadError.value)

// ---------------------------------------------------------------- dialogs ---
const selected = ref<ReviewItem | null>(null)
function openDetails(id: string) {
  selected.value = applications.value.find(a => a.id === id) ?? null
}

const topTierOpen = ref(false)
function showTopTierInList() {
  topTierOpen.value = false
  if (topTierScore.value !== null) filters.showTopTier(topTierScore.value)
}
</script>

<template>
  <section class="flex flex-col gap-6" aria-labelledby="board-title">
    <Toast :group="TOAST_GROUP" position="bottom-right" data-testid="toast" />

    <!-- Visually hidden: the page has no visible title, but screen readers still get one. -->
    <h1 id="board-title" class="sr-only">{{ $t('review.boardTitle', { campaign: meta?.campaign.name ?? $t('review.campaignFallback') }) }}</h1>

    <ApplicantSummaryTiles v-if="summary" :summary="summary" :top-tier-score="topTierScore" data-testid="summary" @open-top-tier="topTierOpen = true" />

    <ApplicantFilterBar
      :min-score="filters.minScore.value"
      :date-range="filters.dateRange.value"
      :status="filters.status.value"
      :sort="filters.sort.value"
      :has-active-filter="hasActiveFilter"
      data-testid="filter-bar"
      @update:min-score="filters.setMinScore"
      @update:date-range="filters.setDateRange"
      @update:status="filters.setStatus"
      @update:sort="filters.setSort"
      @clear="filters.reset"
    />

    <BaseLoadError
      v-if="loadError"
      :message="loadError"
      :reference="loadErrorReference"
      data-testid="load-error"
      @retry="store.fetchSummary(campaignId)"
    />

    <div
      v-if="showSkeleton"
      class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
      role="status"
      :aria-label="$t('review.loading')"
      data-testid="loading-skeleton"
    >
      <ApplicantCardSkeleton v-for="n in pageSize" :key="n" />
    </div>

    <BaseEmptyState v-else-if="isEmpty" icon="pi-filter-slash" :title="$t('review.empty.title')" data-testid="empty-state">
      {{ hasActiveFilter ? $t('review.empty.filteredHint') : $t('review.empty.noApplicants') }}
      <template #actions>
        <Button v-if="hasActiveFilter" :label="$t('review.empty.reset')" size="small" outlined data-testid="reset-filter" @click="filters.reset" />
      </template>
    </BaseEmptyState>

    <template v-else-if="!loadError">
      <p class="sr-only" aria-live="polite">{{ $t('review.matching', { count: total }, total) }}</p>
      <ul
        class="grid gap-4 transition-opacity md:grid-cols-2 xl:grid-cols-3"
        :class="{ 'pointer-events-none opacity-60': loading }"
        :aria-busy="loading"
        data-testid="applicant-list"
      >
        <li v-for="application in applications" :key="application.id" class="flex">
          <ApplicantMatchCard
            :application="application"
            :standout-above="thresholds?.standoutAbove ?? null"
            @shortlist="id => store.setStatus(id, 'shortlisted')"
            @reject="id => store.setStatus(id, 'rejected')"
            @pending="id => store.setStatus(id, 'pending')"
            @view-details="openDetails"
          />
        </li>
      </ul>
      <BasePaginator
        :page-number="pageNumber"
        :page-size="pageSize"
        :total="total"
        :page-size-options="PAGE_SIZE_OPTIONS"
        data-testid="paginator"
        @go-to-page="store.goToPage"
        @set-page-size="store.setPageSize"
      />
    </template>

    <ApplicantDetailsDialog v-model:application="selected" data-testid="details-dialog" />

    <ApplicantTopTierDialog
      v-if="summary"
      v-model:visible="topTierOpen"
      :summary="summary"
      :top-tier-score="topTierScore"
      data-testid="top-tier-dialog"
      @show-all="showTopTierInList"
    />
  </section>
</template>
