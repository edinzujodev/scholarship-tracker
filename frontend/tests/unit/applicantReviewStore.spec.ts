import { beforeEach, describe, expect, it } from 'vitest'
import { registerEndpoint } from '@nuxt/test-utils/runtime'
import { getQuery, readBody, setResponseStatus } from 'h3'
import { useApplicantReviewStore } from '~/stores/applicantReview'
import { MOCK_CAMPAIGN_ID, mockSummary, mockUpdateStatus, resetMockApplicants } from '~~/server/utils/mockApplicants'

const url = `/api/portal/campaigns/${MOCK_CAMPAIGN_ID}/applicant-summary`
const patchUrl = (id: string) => `/api/portal/applications/${id}`

async function loadedStore() {
  registerEndpoint(url, event => mockSummary(MOCK_CAMPAIGN_ID, getQuery(event), url))
  const store = useApplicantReviewStore()
  await store.fetchSummary(MOCK_CAMPAIGN_ID)
  return store
}

describe('applicantReview store', () => {
  beforeEach(() => {
    resetMockApplicants()
    useApplicantReviewStore().reset()
  })

  it('ignores a slow response for an outdated filter (no stale overwrite)', async () => {
    // min_score=40 answers slowly, min_score=95 answers fast.
    registerEndpoint(url, async event => {
      const query = getQuery(event)
      if (query.min_score === '40') await new Promise(resolve => setTimeout(resolve, 120))
      return mockSummary(MOCK_CAMPAIGN_ID, query, url)
    })

    const store = useApplicantReviewStore()
    const slow = store.fetchSummary(MOCK_CAMPAIGN_ID, { filters: { minScore: 40 } })
    const fast = store.fetchSummary(MOCK_CAMPAIGN_ID, { filters: { minScore: 95 } })
    await Promise.all([slow, fast])

    expect(store.filters.minScore).toBe(95)
    expect(store.meta?.filters.minScore).toBe(95)
    expect(store.applications.every(a => a.aiScore >= 95)).toBe(true)
    expect(store.loading).toBe(false)
  })

  it('sends status, date range and sort as JSON:API query parameters', async () => {
    let seen: Record<string, unknown> = {}
    registerEndpoint(url, event => {
      seen = getQuery(event)
      return mockSummary(MOCK_CAMPAIGN_ID, seen, url)
    })

    const store = useApplicantReviewStore()
    await store.fetchSummary(MOCK_CAMPAIGN_ID, {
      filters: { status: 'shortlisted', appliedFrom: '2026-09-01', appliedTo: '2026-09-15' },
      sort: 'name',
      pageSize: 12,
    })

    expect(seen).toMatchObject({
      'filter[status]': 'shortlisted',
      'filter[applied_from]': '2026-09-01',
      'filter[applied_to]': '2026-09-15',
      sort: 'name',
      'page[size]': '12',
      'page[number]': '1',
    })
    expect(store.applications.every(a => a.status === 'shortlisted')).toBe(true)
    expect(store.meta?.sort).toBe('name')
  })

  it('surfaces the JSON:API error detail when loading fails', async () => {
    registerEndpoint('/api/portal/campaigns/not-a-uuid/applicant-summary', event =>
      mockSummary('not-a-uuid', getQuery(event), url),
    )

    const store = useApplicantReviewStore()
    await store.fetchSummary('not-a-uuid')

    expect(store.loadError).toBeTruthy()
    expect(store.applications).toEqual([])
    expect(store.loading).toBe(false)
  })

  it('resets to page 1 when filters, sort or page size change', async () => {
    const store = await loadedStore()

    await store.goToPage(3)
    await store.applyFilters({ minScore: 60 })
    expect(store.meta?.page.number).toBe(1)

    await store.goToPage(2)
    await store.setSort('name')
    expect(store.meta?.page.number).toBe(1)

    await store.goToPage(2)
    await store.setPageSize(12)
    expect(store.meta?.page).toMatchObject({ number: 1, size: 12 })
  })

  it('updates status instantly and serialises rapid clicks so the last choice wins', async () => {
    const store = await loadedStore()
    const applicant = store.applications.find(a => a.status === 'pending')!
    const received: string[] = []
    registerEndpoint(patchUrl(applicant.id), {
      method: 'PATCH',
      handler: async event => {
        const body = await readBody(event)
        received.push(body.data.attributes.status)
        await new Promise(resolve => setTimeout(resolve, 30))
        return mockUpdateStatus(applicant.id, body)
      },
    })

    const first = store.setStatus(applicant.id, 'shortlisted')
    expect(applicant.status).toBe('shortlisted') // before any response
    store.setStatus(applicant.id, 'rejected')
    expect(applicant.status).toBe('rejected')

    expect(await first).toBe(true)
    expect(received).toEqual(['shortlisted', 'rejected']) // never in parallel, never out of order
    expect(store.applications.find(a => a.id === applicant.id)?.status).toBe('rejected')
    expect(store.actionError).toBeNull()
  })

  it('reverts to the last saved status and records an error when the update fails', async () => {
    const store = await loadedStore()
    const applicant = store.applications.find(a => a.status === 'pending')!
    registerEndpoint(patchUrl(applicant.id), {
      method: 'PATCH',
      handler: event => {
        setResponseStatus(event, 503)
        return { errors: [{ status: '503', code: 'unavailable', title: 'Unavailable', detail: 'Review service is down.' }] }
      },
    })

    const result = store.setStatus(applicant.id, 'rejected')
    expect(applicant.status).toBe('rejected')

    expect(await result).toBe(false)
    expect(applicant.status).toBe('pending')
    expect(store.actionError).toMatchObject({
      applicationId: applicant.id,
      attemptedStatus: 'rejected',
      message: 'Review service is down.',
    })
  })
})
