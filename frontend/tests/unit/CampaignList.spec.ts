import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { getQuery } from 'h3'
import CampaignList from '~/components/campaign/CampaignList.vue'
import {
  MOCK_CAMPAIGN_ID,
  MOCK_OTHER_PARTNER_CAMPAIGN_ID,
  MockApiError,
  mockCampaignList,
  mockSummary,
  resetMockApplicants,
} from '~~/server/utils/mockApplicants'

const url = '/api/portal/campaigns'

describe('CampaignList', () => {
  beforeEach(async () => {
    resetMockApplicants()
    registerEndpoint(url, event => mockCampaignList(getQuery(event), url))
    await clearNuxtData(key => key.startsWith('campaign-list'))
  })

  it("lists the partner's campaigns in a table with counts, each linking to its applicant board", async () => {
    const wrapper = await mountSuspended(CampaignList)

    expect(wrapper.find('[data-testid="campaign-table"] table').exists()).toBe(true)
    expect(wrapper.findAll('th').map(th => th.text())).toEqual(['Campaign', 'Applicants', 'Eligible', 'Avg AI score', 'Review progress', '']) // last column: row chevron

    const rows = wrapper.findAll('[data-testid="campaign-row"]')
    const linkOf = (row: (typeof rows)[number]) => row.get('[data-testid="campaign-link"]')
    expect(rows.map(r => linkOf(r).text())).toEqual([
      'Engineering Fellowship 2026',
      'Global Scholars Programme 2026',
      'Spring Pilot (no applicants yet)',
    ])

    const scholars = rows.find(r => linkOf(r).attributes('data-campaign-id') === MOCK_CAMPAIGN_ID)!
    expect(linkOf(scholars).attributes('href')).toBe(`/campaigns/${MOCK_CAMPAIGN_ID}`)
    expect(scholars.get('[data-testid="campaign-applicants"]').text()).toBe('28')
    expect(scholars.text()).toContain('shortlisted')

    // The list's eligible figure matches the campaign's own summary.
    const { eligibleApplicants, eligibleRate } = mockSummary(MOCK_CAMPAIGN_ID, {}, '/x').meta.summary
    expect(scholars.get('[data-testid="campaign-eligible"]').text()).toBe(`${eligibleApplicants} (${eligibleRate}%)`)
  })

  it("never shows another partner's campaign", async () => {
    const wrapper = await mountSuspended(CampaignList)

    expect(wrapper.text()).not.toContain('Northbridge Bursary 2026')
    expect(wrapper.find(`[data-campaign-id="${MOCK_OTHER_PARTNER_CAMPAIGN_ID}"]`).exists()).toBe(false)
  })

  it("answers 404 for another partner's campaign, exactly like a missing one", () => {
    const status = (id: string) => {
      try {
        mockSummary(id, {}, '/x')
        return 200
      } catch (error) {
        return (error as MockApiError).status
      }
    }

    expect(status(MOCK_OTHER_PARTNER_CAMPAIGN_ID)).toBe(404)
    expect(status('11111111-2222-4333-8444-555555555555')).toBe(404)
    expect(status(MOCK_CAMPAIGN_ID)).toBe(200)
  })

  it('renders through a DataTable and hides the paginator when everything fits on one page', async () => {
    const wrapper = await mountSuspended(CampaignList)

    expect(wrapper.find('[data-testid="campaign-table"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid="campaign-row"]')).toHaveLength(3)
    expect(wrapper.find('.p-paginator').exists()).toBe(false)
  })

  it('ends every row with a chevron that hints the row is clickable, hidden from screen readers', async () => {
    const wrapper = await mountSuspended(CampaignList)

    const rows = wrapper.findAll('[data-testid="campaign-row"]')
    for (const row of rows) {
      expect(row.classes()).toContain('cursor-pointer')
      const cells = row.findAll('td')
      const chevron = cells.at(-1)!.get('[data-testid="campaign-row-chevron"]')
      expect(chevron.classes()).toContain('pi-chevron-right')
      expect(chevron.attributes('aria-hidden')).toBe('true')
    }
  })

  it('pages through the API with JSON:API page parameters', async () => {
    const requested: Array<Record<string, unknown>> = []
    const all = Array.from({ length: 8 }, (_, i) => ({
      type: 'campaigns' as const,
      id: `00000000-0000-4000-8000-00000000000${i}`,
      attributes: {
        name: `Campaign ${i + 1}`,
        createdAt: '2026-09-01T00:00:00Z',
        applicantCount: 0,
        eligibleCount: 0,
        eligibleRate: 0,
        averageAiScore: 0,
        pendingCount: 0,
        shortlistedCount: 0,
        rejectedCount: 0,
      },
    }))
    registerEndpoint(url, event => {
      const query = getQuery(event)
      requested.push(query)
      const size = Number(query['page[size]'])
      const number = Number(query['page[number]'])
      return {
        data: all.slice((number - 1) * size, number * size),
        meta: { page: { number, size, total: all.length, lastPage: Math.ceil(all.length / size) } },
        links: {},
      }
    })

    const wrapper = await mountSuspended(CampaignList)
    expect(requested.at(-1)).toMatchObject({ 'page[size]': '6', 'page[number]': '1' })
    expect(wrapper.findAll('[data-testid="campaign-row"]')).toHaveLength(6)
    expect(wrapper.get('.p-paginator').text()).toContain('1–6 of 8')

    await wrapper.get('.p-paginator-next').trigger('click')
    await vi.waitFor(() => expect(wrapper.findAll('[data-testid="campaign-row"]')).toHaveLength(2), { timeout: 5000 })

    expect(requested.at(-1)).toMatchObject({ 'page[size]': '6', 'page[number]': '2' })
    expect(wrapper.get('[data-testid="campaign-link"]').text()).toBe('Campaign 7')
    expect(wrapper.get('.p-paginator').text()).toContain('7–8 of 8')
  })

  it('shows an empty state when the partner has no campaigns', async () => {
    registerEndpoint(url, () => ({ data: [], meta: { page: { number: 1, size: 50, total: 0, lastPage: 1 } }, links: {} }))
    const wrapper = await mountSuspended(CampaignList)

    expect(wrapper.get('[data-testid="campaigns-empty"]').text()).toContain('no campaigns')
  })
})
