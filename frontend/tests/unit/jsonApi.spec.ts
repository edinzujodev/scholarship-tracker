import { beforeEach, describe, expect, it } from 'vitest'
import type { ApplicantResource, ApplicationResource } from '~/types/applicant'
import { fullName, toReviewItems } from '~/utils/jsonApi'
import {
  MOCK_CAMPAIGN_ID,
  MOCK_FELLOWSHIP_CAMPAIGN_ID,
  mockSummary,
  mockUpdateStatus,
  resetMockApplicants,
} from '~~/server/utils/mockApplicants'

const application = (id: string, applicantId: string, aiScore: number): ApplicationResource => ({
  type: 'applications',
  id,
  attributes: { aiScore, tier: 'top', eligible: true, status: 'pending', aiHighlights: [], appliedAt: '2026-09-01T00:00:00Z' },
  relationships: {
    applicant: { data: { type: 'applicants', id: applicantId } },
    campaign: { data: { type: 'campaigns', id: MOCK_CAMPAIGN_ID } },
  },
})
const person = (id: string, name: string): ApplicantResource => {
  const [firstName = '', ...rest] = name.split(' ')
  const lastName = rest.pop() ?? ''
  return { type: 'applicants', id, attributes: { firstName, middleName: rest.length ? rest.join(' ') : null, lastName } }
}

describe('toReviewItems', () => {
  it('resolves each application to its applicant through the relationship, not by position', () => {
    const items = toReviewItems(
      [application('app-1', 'p-2', 90), application('app-2', 'p-1', 80)],
      [person('p-1', 'Grace Hopper'), person('p-2', 'Alan Turing')],
    )

    expect(items.map(i => [i.id, i.applicantId, i.name, i.aiScore])).toEqual([
      ['app-1', 'p-2', 'Alan Turing', 90],
      ['app-2', 'p-1', 'Grace Hopper', 80],
    ])
  })

  it('keeps the name parts and composes the display name, skipping an empty middle name', () => {
    const [withMiddle, withoutMiddle] = toReviewItems(
      [application('app-1', 'p-1', 90), application('app-2', 'p-2', 80)],
      [person('p-1', 'Ada King Lovelace'), person('p-2', 'Grace Hopper')],
    )

    expect(withMiddle).toMatchObject({ firstName: 'Ada', middleName: 'King', lastName: 'Lovelace', name: 'Ada King Lovelace' })
    expect(withoutMiddle).toMatchObject({ firstName: 'Grace', middleName: null, lastName: 'Hopper', name: 'Grace Hopper' })
    expect(fullName({ firstName: 'Ada', middleName: '  ', lastName: 'Lovelace' })).toBe('Ada Lovelace')
  })

  it('does not crash when an applicant is missing from included', () => {
    expect(toReviewItems([application('app-1', 'p-9', 70)], [])[0]!.name).toBe('Unknown applicant')
  })
})

describe('mock API: applicants vs applications', () => {
  beforeEach(() => resetMockApplicants())

  const findByApplicant = (campaignId: string, applicantId: string) => {
    const doc = mockSummary(campaignId, { 'page[size]': '100' }, '/x')
    return toReviewItems(doc.data, doc.included).find(i => i.applicantId === applicantId)!
  }

  it('lets the same person apply to two campaigns, each with its own score and status', () => {
    const personId = '00000000-0000-4000-a000-000000000001'
    const scholars = findByApplicant(MOCK_CAMPAIGN_ID, personId)
    const fellowship = findByApplicant(MOCK_FELLOWSHIP_CAMPAIGN_ID, personId)

    expect(scholars.name).toBe(fellowship.name)
    expect(scholars.id).not.toBe(fellowship.id)

    mockUpdateStatus(fellowship.id, { data: { type: 'applications', id: fellowship.id, attributes: { status: 'rejected' } } })

    expect(findByApplicant(MOCK_FELLOWSHIP_CAMPAIGN_ID, personId).status).toBe('rejected')
    expect(findByApplicant(MOCK_CAMPAIGN_ID, personId).status).toBe(scholars.status)
  })

  it('includes every referenced applicant exactly once', () => {
    const doc = mockSummary(MOCK_CAMPAIGN_ID, { 'page[size]': '6' }, '/x')
    const referenced = doc.data.map(a => a.relationships.applicant.data.id)

    expect(doc.included.map(p => p.id).sort()).toEqual([...new Set(referenced)].sort())
  })
})
