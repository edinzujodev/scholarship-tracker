import type { MaybeRefOrGetter } from 'vue'
import { storeToRefs } from 'pinia'
import type { ApplicationSort, ApplicationStatus } from '~/types/applicant'

type DatePickerValue = Date | Date[] | (Date | null)[] | null | undefined

/**
 * Filter and sort state of the applicant board, translated between what the
 * controls show and what the store (and the API) expects.
 *
 * - The score slider moves instantly; the request follows after a debounce.
 * - The date range picker works with local Dates; the API with YYYY-MM-DD days.
 * - Every change goes through the store, which resets to page 1.
 */
export function useApplicantFilters(debounceMs: MaybeRefOrGetter<number> = 250) {
  const store = useApplicantReviewStore()
  const { filters, sort, hasActiveFilter } = storeToRefs(store)

  // ------------------------------------------------------------ min score ---
  const minScore = ref(filters.value.minScore)
  let debounceTimer: ReturnType<typeof setTimeout> | undefined
  const cancelPending = () => clearTimeout(debounceTimer)
  watch(() => filters.value.minScore, value => (minScore.value = value))
  onScopeDispose(cancelPending)

  function setMinScore(value: number | number[]) {
    const score = Array.isArray(value) ? (value[0] ?? 0) : value
    minScore.value = score
    cancelPending()
    debounceTimer = setTimeout(() => store.applyFilters({ minScore: score }), toValue(debounceMs))
  }

  // ----------------------------------------------------------- date range ---
  const dateRange = computed<Date[] | null>(() => {
    const { appliedFrom, appliedTo } = filters.value
    if (!appliedFrom) return null
    return appliedTo ? [fromIsoDay(appliedFrom), fromIsoDay(appliedTo)] : [fromIsoDay(appliedFrom)]
  })

  function setDateRange(value: DatePickerValue) {
    if (!value || (Array.isArray(value) && value.length === 0)) {
      store.applyFilters({ appliedFrom: null, appliedTo: null })
      return
    }
    const [start, end] = Array.isArray(value) ? value : [value]
    // A range picker emits [start, null] after the first click; wait for the end date.
    if (!start || !end) return
    store.applyFilters({ appliedFrom: toIsoDay(start), appliedTo: toIsoDay(end) })
  }

  // ------------------------------------------------------- status and sort ---
  const status = computed(() => filters.value.status)
  const setStatus = (value: ApplicationStatus | null | undefined) => store.applyFilters({ status: value ?? null })
  const setSort = (value: ApplicationSort) => store.setSort(value)

  // ---------------------------------------------------------------- reset ---
  function reset() {
    cancelPending()
    minScore.value = 0
    store.clearFilters()
  }

  /** Lists every top-tier applicant: score filter at the threshold, other filters cleared, best first. */
  function showTopTier(topTierScore: number) {
    if (!store.campaignId) return
    cancelPending()
    minScore.value = topTierScore
    store.fetchSummary(store.campaignId, {
      filters: { minScore: topTierScore, status: null, appliedFrom: null, appliedTo: null },
      sort: '-aiScore',
      pageNumber: 1,
    })
  }

  return { minScore, setMinScore, dateRange, setDateRange, status, setStatus, sort, setSort, hasActiveFilter, reset, showTopTier }
}
