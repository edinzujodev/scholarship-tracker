import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { getQuery, readBody, setResponseStatus } from 'h3'
import ApplicantReviewBoard from '~/components/applicant/ApplicantReviewBoard.vue'
import { useApplicantReviewStore } from '~/stores/applicantReview'
import {
  MOCK_CAMPAIGN_ID,
  MOCK_EMPTY_CAMPAIGN_ID,
  MOCK_FELLOWSHIP_CAMPAIGN_ID,
  mockSummary,
  mockUpdateStatus,
  resetMockApplicants,
} from '~~/server/utils/mockApplicants'

const summaryUrl = (id: string) => `/api/portal/campaigns/${id}/applicant-summary`
const patchUrl = (applicationId: string) => `/api/portal/applications/${applicationId}`

/** Serve the component the same JSON:API contract the real BFF/Laravel returns. */
function serveSummary(campaignId: string, delayMs = 0) {
  registerEndpoint(summaryUrl(campaignId), async event => {
    if (delayMs) await new Promise(resolve => setTimeout(resolve, delayMs))
    return mockSummary(campaignId, getQuery(event), summaryUrl(campaignId))
  })
}

const mounted: { unmount: () => void }[] = []

async function mountBoard(campaignId = MOCK_CAMPAIGN_ID) {
  const wrapper = await mountSuspended(ApplicantReviewBoard, { props: { campaignId, filterDebounce: 0 }, attachTo: document.body })
  mounted.push(wrapper)
  await flushPromises()
  return wrapper
}

type Board = Awaited<ReturnType<typeof mountBoard>>

const cardScores = (wrapper: Board) =>
  wrapper.findAll('[data-testid="score-badge"]').map(badge => Number.parseInt(badge.text(), 10))
const cardNames = (wrapper: Board) => wrapper.findAll('[data-testid="applicant-name"]').map(n => n.text())
const selectByTestId = (wrapper: Board, testId: string) =>
  wrapper.findAllComponents({ name: 'Select' }).find(c => c.attributes('data-testid') === testId)!
const settled = (wrapper: Board) => !wrapper.find('[data-testid="applicant-list"][aria-busy="true"]').exists()

describe('ApplicantReviewBoard', () => {
  beforeEach(() => {
    resetMockApplicants()
    useApplicantReviewStore().reset()
    serveSummary(MOCK_CAMPAIGN_ID)
    serveSummary(MOCK_EMPTY_CAMPAIGN_ID)
  })

  afterEach(() => {
    vi.useRealTimers()
    while (mounted.length) mounted.pop()!.unmount()
  })

  it('renders the report and applicant cards with no visible title', async () => {
    const wrapper = await mountBoard()

    // Title is screen-reader only; no visible heading or "Applicant review" eyebrow.
    const title = wrapper.get('#board-title')
    expect(title.classes()).toContain('sr-only')
    expect(title.text()).toBe('Global Scholars Programme 2026 applicants')
    expect(wrapper.findAll('h1:not(.sr-only), h2').length).toBe(0)
    expect(wrapper.text()).not.toContain('Applicant review')
    expect(wrapper.get('[data-testid="summary-total"]').text()).toBe('28')
    expect(wrapper.findAll('[data-testid="applicant-card"]')).toHaveLength(6)

    const scores = cardScores(wrapper)
    expect(scores).toEqual([...scores].sort((a, b) => b - a))
  })

  it('places the filter bar under the report, with Filters: first and Sort by on the right', async () => {
    const wrapper = await mountBoard()

    const summary = wrapper.get('[data-testid="summary"]').element
    const bar = wrapper.get('[data-testid="filter-bar"]')
    expect(summary.compareDocumentPosition(bar.element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()

    expect(bar.element.firstElementChild?.textContent?.trim()).toBe('Filters:')
    // Visible labels only for the slider and sort; date and status explain themselves via placeholders.
    expect(bar.findAll('label:not(.sr-only)').map(el => el.text())).toEqual(['Minimum score', 'Sort by'])
    expect(bar.findAll('label.sr-only').map(el => el.text())).toEqual(['Application date', 'Status'])
    expect(bar.get('#applied-range').attributes('placeholder')).toBe('Application date')
    expect(bar.get('[data-testid="status-filter"]').text()).toContain('Status')
    expect(bar.get('[data-testid="sort-control"]').classes()).toContain('ml-auto')
    // One compact row: every control has its label inline, not stacked above it.
    for (const group of bar.findAll(':scope > div')) expect(group.classes()).toContain('items-center')
  })

  it('filters applicants by minimum score when the slider moves', async () => {
    const wrapper = await mountBoard()

    await wrapper.get('#min-score').setValue('90')
    await vi.waitUntil(() => cardScores(wrapper).every(score => score >= 90) && settled(wrapper))

    expect(wrapper.get('[data-testid="min-score-value"]').text()).toBe('90')
    expect(cardScores(wrapper).length).toBeGreaterThan(0)
    // The campaign-wide report is not affected by the list filters.
    expect(wrapper.get('[data-testid="summary-total"]').text()).toBe('28')
  })

  it('filters by status', async () => {
    const wrapper = await mountBoard()

    selectByTestId(wrapper, 'status-filter').vm.$emit('update:modelValue', 'shortlisted')
    await vi.waitUntil(() => settled(wrapper) && wrapper.findAll('[data-testid="applicant-card"]').every(c => c.attributes('data-status') === 'shortlisted'))

    expect(wrapper.findAll('[data-testid="applicant-card"]').length).toBeGreaterThan(0)
    expect(useApplicantReviewStore().meta?.filters.status).toBe('shortlisted')
  })

  it('filters by application date range once both dates are picked', async () => {
    const wrapper = await mountBoard()
    const picker = wrapper.findComponent({ name: 'DatePicker' })

    picker.vm.$emit('update:modelValue', [new Date(2026, 8, 1), null]) // first click: no request yet
    await flushPromises()
    expect(useApplicantReviewStore().filters.appliedFrom).toBeNull()

    picker.vm.$emit('update:modelValue', [new Date(2026, 8, 1), new Date(2026, 8, 3)])
    await vi.waitUntil(() => settled(wrapper) && useApplicantReviewStore().meta?.filters.appliedTo === '2026-09-03')

    const dates = wrapper.findAll('[data-testid="applied-at"]').map(t => t.attributes('datetime')!.slice(0, 10))
    expect(dates.length).toBeGreaterThan(0)
    expect(dates.every(d => d >= '2026-09-01' && d <= '2026-09-03')).toBe(true)
  })

  it('sorts from the dropdown on the right of the filter bar', async () => {
    const wrapper = await mountBoard()

    selectByTestId(wrapper, 'sort-select').vm.$emit('update:modelValue', 'name')
    await vi.waitUntil(() => settled(wrapper) && useApplicantReviewStore().meta?.sort === 'name')

    // "name" sorts by last name, then first name. Demo names have no middle name.
    const key = (name: string) => {
      const parts = name.split(' ')
      return `${parts.at(-1)} ${parts[0]}`
    }
    const names = cardNames(wrapper)
    expect(names.length).toBeGreaterThan(1)
    expect(names).toEqual([...names].sort((a, b) => key(a).localeCompare(key(b))))
  })

  it('changes the number of applicants per page', async () => {
    const wrapper = await mountBoard()

    wrapper.findComponent({ name: 'Paginator' }).vm.$emit('page', { page: 0, rows: 12, first: 0, pageCount: 3 })
    await vi.waitUntil(() => settled(wrapper) && wrapper.findAll('[data-testid="applicant-card"]').length === 12)

    expect(useApplicantReviewStore().meta?.page).toMatchObject({ size: 12, number: 1, total: 28, lastPage: 3 })
  })

  it('shows an empty state when nothing matches and resets the filters', async () => {
    const wrapper = await mountBoard()

    await wrapper.get('#min-score').setValue('100')
    await vi.waitUntil(() => wrapper.find('[data-testid="empty-state"]').exists())
    expect(wrapper.findAll('[data-testid="applicant-card"]')).toHaveLength(0)

    await wrapper.get('[data-testid="reset-filter"]').trigger('click')
    await vi.waitUntil(() => wrapper.findAll('[data-testid="applicant-card"]').length === 6)
    expect(wrapper.get('[data-testid="min-score-value"]').text()).toBe('0')
  })

  it('starts from clean filters when switching to another campaign', async () => {
    serveSummary(MOCK_FELLOWSHIP_CAMPAIGN_ID)
    const store = useApplicantReviewStore()
    await store.fetchSummary(MOCK_CAMPAIGN_ID, { filters: { minScore: 90, status: 'shortlisted' }, sort: 'name' })

    await mountBoard(MOCK_FELLOWSHIP_CAMPAIGN_ID)

    expect(store.campaignId).toBe(MOCK_FELLOWSHIP_CAMPAIGN_ID)
    expect(store.filters).toEqual({ minScore: 0, status: null, appliedFrom: null, appliedTo: null })
    expect(store.sort).toBe('-aiScore')
    expect(store.meta?.campaign.name).toBe('Engineering Fellowship 2026')
  })

  it('shows the empty state for a campaign with no applicants', async () => {
    const wrapper = await mountBoard(MOCK_EMPTY_CAMPAIGN_ID)

    expect(wrapper.get('[data-testid="empty-state"]').text()).toContain('This campaign has no applicants yet.')
    expect(wrapper.get('[data-testid="summary-average"]').text()).toBe('0.0')
    expect(wrapper.find('[data-testid="top-tier-button"]').exists()).toBe(false)
  })

  it('renders loading skeletons until the first response arrives', async () => {
    serveSummary(MOCK_CAMPAIGN_ID, 150)
    const store = useApplicantReviewStore()
    const pendingFetch = store.fetchSummary(MOCK_CAMPAIGN_ID)

    const wrapper = await mountSuspended(ApplicantReviewBoard, { props: { campaignId: MOCK_CAMPAIGN_ID } })
    expect(wrapper.find('[data-testid="loading-skeleton"]').exists()).toBe(true)

    await pendingFetch
    await flushPromises()
    expect(wrapper.find('[data-testid="loading-skeleton"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-testid="applicant-card"]')).toHaveLength(6)
  })

  it('takes every threshold from the API (nothing hard-coded in the UI)', async () => {
    // Pretend the backend moved its thresholds: the UI must follow without code changes.
    registerEndpoint(summaryUrl(MOCK_CAMPAIGN_ID), event => {
      const doc = mockSummary(MOCK_CAMPAIGN_ID, getQuery(event), summaryUrl(MOCK_CAMPAIGN_ID))
      return { ...doc, meta: { ...doc.meta, thresholds: { topTier: 80, strong: 60, standoutAbove: 95 } } }
    })
    const wrapper = await mountBoard()

    expect(wrapper.get('[data-testid="summary"]').text()).toContain('Top tier (80+)')

    const cards = wrapper.findAll('[data-testid="applicant-card"]')
    for (const card of cards) {
      const score = Number.parseInt(card.get('[data-testid="score-badge"]').text(), 10)
      expect(card.attributes('data-standout')).toBe(String(score > 95))
    }

    await wrapper.get('[data-testid="top-tier-button"]').trigger('click')
    await vi.waitUntil(() => document.querySelector('[data-testid="top-tier-show-all"]'))
    expect(document.querySelector('[data-testid="top-tier-dialog"]')?.textContent).toContain('scored 80 or higher')
    ;(document.querySelector('[data-testid="top-tier-show-all"]') as HTMLButtonElement).click()
    await vi.waitUntil(() => settled(wrapper) && useApplicantReviewStore().meta?.filters.minScore === 80)
  })

  it('lists every top-tier applicant in a dialog and can show them all in the list', async () => {
    const wrapper = await mountBoard()
    const { summary } = useApplicantReviewStore().meta!

    await wrapper.get('[data-testid="top-tier-button"]').trigger('click')
    await vi.waitUntil(() => document.querySelector('[data-testid="top-tier-list"]'))

    const listed = [...document.querySelectorAll('[data-testid="top-tier-list"] > li')]
    expect(listed).toHaveLength(summary.topTier.length)
    expect(listed[0]!.textContent).toContain(summary.topTier[0]!.name)

    ;(document.querySelector('[data-testid="top-tier-show-all"]') as HTMLButtonElement).click()
    await vi.waitUntil(() => settled(wrapper) && useApplicantReviewStore().meta?.filters.minScore === 85)
    expect(useApplicantReviewStore().meta?.page.total).toBe(summary.topTierCount)
    expect(cardScores(wrapper).every(score => score >= 85)).toBe(true)
  })

  it('changes status instantly with no loading state', async () => {
    const wrapper = await mountBoard()
    const card = wrapper.findAll('[data-testid="applicant-card"]').find(c => c.attributes('data-status') === 'pending')!
    const id = card.attributes('data-application-id')!

    let release!: () => void
    const gate = new Promise<void>(resolve => (release = resolve))
    registerEndpoint(patchUrl(id), {
      method: 'PATCH',
      handler: async event => {
        await gate
        return mockUpdateStatus(id, await readBody(event))
      },
    })

    await card.get('[data-testid="shortlist-button"]').trigger('click')

    // Server has not answered yet, but the UI already shows the final state.
    const cardFor = () => wrapper.get(`[data-application-id="${id}"]`)
    expect(cardFor().attributes('data-status')).toBe('shortlisted')
    expect(cardFor().get('[data-testid="status"]').text()).toContain('Shortlisted')
    expect(cardFor().find('.p-button-loading').exists()).toBe(false)
    expect(cardFor().text()).not.toContain('Saving')

    release()
    await flushPromises()
    expect(cardFor().attributes('data-status')).toBe('shortlisted')
    expect(document.body.textContent).not.toContain("Couldn't do the operation")
  })

  it('reverts and shows a toast when the status update fails', async () => {
    const wrapper = await mountBoard()
    const card = wrapper.findAll('[data-testid="applicant-card"]').find(c => c.attributes('data-status') === 'pending')!
    const id = card.attributes('data-application-id')!
    const name = card.get('[data-testid="applicant-name"]').text()

    registerEndpoint(patchUrl(id), {
      method: 'PATCH',
      handler: event => {
        setResponseStatus(event, 503)
        return {
          errors: [{ status: '503', code: 'unavailable', title: 'Unavailable', detail: 'Review service is down.' }],
          meta: { requestId: 'portal-ref-0001' },
        }
      },
    })

    await card.get('[data-testid="reject-button"]').trigger('click')
    expect(wrapper.get(`[data-application-id="${id}"]`).attributes('data-status')).toBe('rejected')

    await vi.waitUntil(() => document.body.textContent?.includes("Couldn't do the operation"))
    expect(wrapper.get(`[data-application-id="${id}"]`).attributes('data-status')).toBe('pending')
    expect(document.body.textContent).toContain(`Could not reject ${name}. Review service is down.`)
    // The toast carries the request id so the partner can quote it to support.
    expect(document.body.textContent).toContain('Reference: portal-ref-0001')
  })
})
