import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ApplicantMatchCard from '~/components/applicant/ApplicantMatchCard.vue'
import { applicationFixture } from '../fixtures/applicants'

describe('ApplicantMatchCard', () => {
  it('renders the applicant name, colour-coded score, date, status and AI highlights', async () => {
    const wrapper = await mountSuspended(ApplicantMatchCard, { props: { application: applicationFixture() } })

    expect(wrapper.get('[data-testid="applicant-name"]').text()).toBe('Ada Lovelace')

    const badge = wrapper.get('[data-testid="score-badge"]')
    expect(badge.text()).toContain('92')
    expect(badge.attributes('data-tier')).toBe('top')
    expect(badge.classes()).toContain('bg-emerald-100')

    const date = wrapper.get('[data-testid="applied-at"]')
    expect(date.text()).toBe('14 Sept 2026')
    expect(date.attributes('datetime')).toBe('2026-09-14T08:30:00+00:00')

    const highlights = wrapper.findAll('[data-testid="highlights"] li').map(li => li.text())
    expect(highlights).toEqual(['Top 1% maths olympiad', 'Published research on analytical engines'])
    expect(wrapper.get('[data-testid="status"]').text()).toContain('Pending review')
  })

  it.each([
    ['top', 'bg-emerald-100'],
    ['strong', 'bg-amber-100'],
    ['review', 'bg-rose-100'],
  ] as const)('colours the badge by the tier the API sent (%s)', async (tier, colour) => {
    // Score 50 on purpose: the card must not recompute the tier from the score.
    const wrapper = await mountSuspended(ApplicantMatchCard, { props: { application: applicationFixture({ aiScore: 50, tier }) } })
    const badge = wrapper.get('[data-testid="score-badge"]')

    expect(badge.attributes('data-tier')).toBe(tier)
    expect(badge.classes()).toContain(colour)
  })

  it.each([
    [91, 90, true],
    [90, 90, false],
    [82, 80, true],
    [99, null, false],
  ])('highlights scores above the API threshold (score %i, standoutAbove %s → %s)', async (aiScore, standoutAbove, highlighted) => {
    const wrapper = await mountSuspended(ApplicantMatchCard, {
      props: { application: applicationFixture({ aiScore }), standoutAbove },
    })
    const card = wrapper.get('[data-testid="applicant-card"]')

    expect(card.attributes('data-standout')).toBe(String(highlighted))
    expect(card.classes().includes('ring-emerald-400/60')).toBe(highlighted)
    expect(wrapper.find('[data-testid="standout-label"]').exists()).toBe(highlighted)
    if (highlighted) expect(wrapper.get('[data-testid="standout-label"]').text()).toContain(`above ${standoutAbove}`)
  })

  it('puts all status buttons in one row, then a divider, then View details', async () => {
    const wrapper = await mountSuspended(ApplicantMatchCard, { props: { application: applicationFixture() } })

    const row = wrapper.get('[data-testid="status-actions"]')
    expect(row.classes()).toContain('flex-nowrap')
    expect(row.findAll('button').map(b => b.text())).toEqual(['Shortlist', 'Reject', 'Pending'])

    const order = [...wrapper.element.querySelectorAll('[data-testid="status-actions"], hr, [data-testid="details-button"]')].map(el =>
      el.tagName === 'HR' ? 'hr' : el.getAttribute('data-testid'),
    )
    expect(order).toEqual(['status-actions', 'hr', 'details-button'])
  })

  it('emits typed events for each action button', async () => {
    const applicant = applicationFixture({ status: 'rejected' })
    const wrapper = await mountSuspended(ApplicantMatchCard, { props: { application: applicant } })

    await wrapper.get('[data-testid="shortlist-button"]').trigger('click')
    await wrapper.get('[data-testid="pending-button"]').trigger('click')
    await wrapper.get('[data-testid="details-button"]').trigger('click')

    expect(wrapper.emitted('shortlist')).toEqual([[applicant.id]])
    expect(wrapper.emitted('pending')).toEqual([[applicant.id]])
    expect(wrapper.emitted('viewDetails')).toEqual([[applicant.id]])
  })

  it('disables only the button for the current status and never shows a loading state', async () => {
    const wrapper = await mountSuspended(ApplicantMatchCard, { props: { application: applicationFixture({ status: 'shortlisted' }) } })

    expect(wrapper.get('[data-testid="shortlist-button"]').attributes()).toHaveProperty('disabled')
    expect(wrapper.get('[data-testid="shortlist-button"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.get('[data-testid="reject-button"]').attributes()).not.toHaveProperty('disabled')
    expect(wrapper.get('[data-testid="pending-button"]').attributes()).not.toHaveProperty('disabled')
    expect(wrapper.find('.p-button-loading').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Saving')
  })

  it('does not crash on missing highlights or an invalid date', async () => {
    const wrapper = await mountSuspended(ApplicantMatchCard, {
      props: { application: applicationFixture({ aiHighlights: [], appliedAt: 'not-a-date' }) },
    })

    expect(wrapper.text()).toContain('No AI highlights yet.')
    expect(wrapper.get('[data-testid="applied-at"]').text()).toBe('Unknown date')
  })
})
