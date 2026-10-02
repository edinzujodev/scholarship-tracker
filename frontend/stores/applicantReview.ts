import { defineStore } from 'pinia'
import type {
  ApplicationDocument,
  ApplicationFilters,
  ApplicationSort,
  ApplicationStatus,
  ApplicantSummaryDocument,
  ApplicantSummaryMeta,
  ApplicantSummaryParams,
  JsonApiErrorDocument,
  ReviewItem,
} from '~/types/applicant'
import { toReviewItems } from '~/utils/jsonApi'

export const DEFAULT_PAGE_SIZE = 6
export const PAGE_SIZE_OPTIONS = [6, 12, 24, 48]
export const DEFAULT_SORT: ApplicationSort = '-aiScore'

export const emptyFilters = (): ApplicationFilters => ({ minScore: 0, status: null, appliedFrom: null, appliedTo: null })

export interface ActionError {
  /** Increments on every failure so watchers fire even for repeated errors. */
  seq: number
  applicationId: string
  applicantName: string
  attemptedStatus: ApplicationStatus
  message: string
  /** X-Request-Id of the failed call: shown to the user as a support reference. */
  requestId?: string
}

/** Pull the first JSON:API error detail out of an ofetch error, if any. */
export function describeError(error: unknown, fallback: string): string {
  const data = (error as { data?: Partial<JsonApiErrorDocument> } | null)?.data
  return data?.errors?.[0]?.detail ?? fallback
}

export const useApplicantReviewStore = defineStore('applicantReview', () => {
  // During SSR this forwards the page request's cookie / Authorization header to
  // the /api/portal routes, so data calls act as the same partner as the page
  // access check. In the browser it is plain $fetch.
  const requestFetch = usePortalFetch()
  // Client-only plugin: undefined during SSR, so reporting is optional.
  const nuxtApp = useNuxtApp()
  // User-facing fallbacks come from the locale (i18n/locales/en.json).
  const t = nuxtApp.$i18n.t
  const reportClient = (input: { type: 'api_error' | 'status_rollback'; message: string; status?: number; requestId?: string }) =>
    (nuxtApp.$clientLog as { report?: (event: typeof input) => void } | undefined)?.report?.(input)
  const statusOf = (error: unknown) => (error as { statusCode?: number; status?: number })?.statusCode ?? (error as { status?: number })?.status

  const campaignId = ref<string | null>(null)
  /** Current page: one item per application, with the applicant's name resolved. */
  const applications = ref<ReviewItem[]>([])
  const meta = ref<ApplicantSummaryMeta | null>(null)
  const loading = ref(false)
  const loadError = ref<string | null>(null)
  /** Support reference (request id) of the failed load, if any. */
  const loadErrorReference = ref<string | null>(null)
  const actionError = ref<ActionError | null>(null)

  const filters = ref<ApplicationFilters>(emptyFilters())
  const sort = ref<ApplicationSort>(DEFAULT_SORT)
  const pageNumber = ref(1)
  const pageSize = ref(DEFAULT_PAGE_SIZE)

  const isEmpty = computed(() => !loading.value && !loadError.value && applications.value.length === 0)
  const hasActiveFilter = computed(
    () =>
      filters.value.minScore > 0 || filters.value.status !== null || filters.value.appliedFrom !== null || filters.value.appliedTo !== null,
  )

  // Monotonic request id: a slow response for an old filter value must never
  // overwrite the list for the value the user has since chosen.
  let latestRequest = 0

  async function fetchSummary(id: string, params: ApplicantSummaryParams = {}): Promise<void> {
    campaignId.value = id
    if (params.filters) filters.value = { ...filters.value, ...params.filters }
    if (params.sort) sort.value = params.sort
    if (params.pageNumber !== undefined) pageNumber.value = params.pageNumber
    if (params.pageSize !== undefined) pageSize.value = params.pageSize

    const requestId = ++latestRequest
    loading.value = true
    loadError.value = null
    loadErrorReference.value = null

    const f = filters.value
    try {
      const doc = await requestFetch<ApplicantSummaryDocument>(
        `/api/portal/campaigns/${encodeURIComponent(id)}/applicant-summary`,
        {
          query: {
            min_score: f.minScore > 0 ? f.minScore : undefined,
            'filter[status]': f.status ?? undefined,
            'filter[applied_from]': f.appliedFrom ?? undefined,
            'filter[applied_to]': f.appliedTo ?? undefined,
            sort: sort.value,
            'page[size]': pageSize.value,
            'page[number]': pageNumber.value,
          },
        },
      )
      if (requestId !== latestRequest) return

      applications.value = toReviewItems(doc.data, doc.included)
      meta.value = doc.meta
    } catch (error) {
      if (requestId !== latestRequest) return
      applications.value = []
      loadError.value = describeError(error, t('review.loadError'))
      loadErrorReference.value = requestIdFromError(error) ?? null
      reportClient({ type: 'api_error', message: `Loading applicants failed: ${loadError.value}`, status: statusOf(error), requestId: loadErrorReference.value ?? undefined })
    } finally {
      if (requestId === latestRequest) loading.value = false
    }
  }

  /** Any filter change starts from page 1 (staying on page 4 of a shorter list shows a false empty state). */
  function applyFilters(patch: Partial<ApplicationFilters>): Promise<void> {
    if (!campaignId.value) return Promise.resolve()
    return fetchSummary(campaignId.value, { filters: patch, pageNumber: 1 })
  }

  function clearFilters(): Promise<void> {
    return applyFilters(emptyFilters())
  }

  function setSort(value: ApplicationSort): Promise<void> {
    if (!campaignId.value) return Promise.resolve()
    return fetchSummary(campaignId.value, { sort: value, pageNumber: 1 })
  }

  function setPageSize(size: number): Promise<void> {
    if (!campaignId.value) return Promise.resolve()
    return fetchSummary(campaignId.value, { pageSize: size, pageNumber: 1 })
  }

  function goToPage(page: number): Promise<void> {
    if (!campaignId.value) return Promise.resolve()
    return fetchSummary(campaignId.value, { pageNumber: page })
  }

  // ---------------------------------------------------------------------------
  // Optimistic status changes
  //
  // The UI changes immediately and never shows a loading state. Requests for
  // the same application are serialised (one in flight, latest click queued) so
  // the server always ends on the status the user picked last. On failure the
  // card reverts to the last server-confirmed status and `actionError` is set
  // (the board turns it into a toast).
  // ---------------------------------------------------------------------------
  const confirmed = new Map<string, ApplicationStatus>()
  const queued = new Map<string, ApplicationStatus>()
  const inFlight = new Map<string, Promise<boolean>>()
  let errorSeq = 0

  const findApplication = (id: string) => applications.value.find(a => a.id === id)

  /** Status belongs to the application (one applicant in one campaign), so the id here is the application id. */
  function setStatus(applicationId: string, status: ApplicationStatus): Promise<boolean> {
    const application = findApplication(applicationId)
    if (!application || !campaignId.value) return Promise.resolve(false)
    if (application.status === status) return inFlight.get(applicationId) ?? Promise.resolve(true)

    if (!confirmed.has(applicationId)) confirmed.set(applicationId, application.status)
    application.status = status
    queued.set(applicationId, status)

    const running = inFlight.get(applicationId)
    if (running) return running

    const run = flush(applicationId).finally(() => {
      inFlight.delete(applicationId)
      confirmed.delete(applicationId)
      queued.delete(applicationId)
    })
    inFlight.set(applicationId, run)
    return run
  }

  async function flush(applicationId: string): Promise<boolean> {
    let lastDoc: ApplicationDocument | null = null
    let next = queued.get(applicationId)

    while (next !== undefined) {
      queued.delete(applicationId)
      try {
        lastDoc = await requestFetch<ApplicationDocument>(`/api/portal/applications/${encodeURIComponent(applicationId)}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/vnd.api+json', Accept: 'application/vnd.api+json' },
          body: { data: { type: 'applications', id: applicationId, attributes: { status: next } } },
        })
        confirmed.set(applicationId, lastDoc.data.attributes.status)
      } catch (error) {
        // Look the application up again: the list may have been refetched meanwhile.
        const current = findApplication(applicationId)
        const revertTo = confirmed.get(applicationId)
        if (current && revertTo) current.status = revertTo
        actionError.value = {
          seq: ++errorSeq,
          applicationId,
          applicantName: current?.name ?? t('review.statusError.unknownApplicant'),
          attemptedStatus: next,
          message: describeError(error, t('review.statusError.reverted')),
          requestId: requestIdFromError(error),
        }
        reportClient({
          type: 'status_rollback',
          message: `Status change to ${next} failed and was reverted: ${actionError.value.message}`,
          status: statusOf(error),
          requestId: actionError.value.requestId,
        })
        return false
      }
      next = queued.get(applicationId)
    }

    const current = findApplication(applicationId)
    if (current && lastDoc) Object.assign(current, lastDoc.data.attributes)
    return true
  }

  function reset(): void {
    latestRequest++
    campaignId.value = null
    applications.value = []
    meta.value = null
    loading.value = false
    loadError.value = null
    loadErrorReference.value = null
    actionError.value = null
    filters.value = emptyFilters()
    sort.value = DEFAULT_SORT
    pageNumber.value = 1
    pageSize.value = DEFAULT_PAGE_SIZE
    confirmed.clear()
    queued.clear()
    inFlight.clear()
  }

  return {
    reset,
    campaignId,
    applications,
    meta,
    loading,
    loadError,
    loadErrorReference,
    actionError,
    filters,
    sort,
    pageNumber,
    pageSize,
    isEmpty,
    hasActiveFilter,
    fetchSummary,
    applyFilters,
    clearFilters,
    setSort,
    setPageSize,
    goToPage,
    setStatus,
  }
})
