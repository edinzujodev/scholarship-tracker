import { beforeEach, describe, expect, it } from 'vitest'
import {
  MOCK_CAMPAIGN_ID,
  MockApiError,
  mockCampaign,
  mockCampaignList,
  mockSummary,
  mockUpdateStatus,
  resetMockApplicants,
} from '~~/server/utils/mockApplicants'

/** The mock API must be exactly as strict as the Laravel API it stands in for. */
function failure(run: () => unknown): { status: number; parameter?: string; pointer?: string; code: string } {
  try {
    run()
  } catch (error) {
    if (!(error instanceof MockApiError)) throw error
    const first = error.errors[0]!
    return { status: error.status, parameter: first.source?.parameter, pointer: first.source?.pointer, code: first.code }
  }
  throw new Error('expected the mock to reject the request')
}

const summary = (query: Record<string, unknown>) => () => mockSummary(MOCK_CAMPAIGN_ID, query, '/x')
const APPLICATION = '00000000-0000-4000-8000-000000000002'
const patch = (body: unknown, query: Record<string, unknown> = {}) => () => mockUpdateStatus(APPLICATION, body, undefined, query)
const statusBody = (extra: Record<string, unknown> = {}) => ({
  data: { type: 'applications', id: APPLICATION, attributes: { status: 'rejected' }, ...extra },
})

describe('mock API strict validation (mirrors the Laravel API)', () => {
  beforeEach(() => resetMockApplicants())

  it.each([
    [{ foo: '1' }, 'foo'],
    [{ include: 'applicant' }, 'include'],
    [{ 'fields[applications]': 'aiScore' }, 'fields'],
  ])('unknown summary parameter %o is a 400', (query, parameter) => {
    expect(failure(summary(query))).toMatchObject({ status: 400, code: 'unsupported_parameter', parameter })
  })

  it('campaign list and single campaign reject parameters they do not support', () => {
    expect(failure(() => mockCampaignList({ sort: 'name' }, '/x'))).toMatchObject({ status: 400, parameter: 'sort' })
    expect(failure(() => mockCampaign(MOCK_CAMPAIGN_ID, undefined, { 'page[size]': '1' }))).toMatchObject({ status: 400, parameter: 'page' })
  })

  it.each([
    [{ min_score: '+80' }, 'min_score'],
    [{ min_score: ' 80' }, 'min_score'],
    [{ min_score: '80.0' }, 'min_score'],
    [{ min_score: '' }, 'min_score'],
    [{ min_score: '080' }, 'min_score'],
    [{ min_score: ['80'] }, 'min_score'],
    [{ 'page[size]': '+5' }, 'page[size]'],
    [{ 'filter[status]': 'Shortlisted' }, 'filter[status]'],
    [{ 'filter[applied_from]': '2026-02-30' }, 'filter[applied_from]'],
    [{ 'filter[foo]': 'x' }, 'filter'],
  ])('rejects %o with 422', (query, parameter) => {
    expect(failure(summary(query))).toMatchObject({ status: 422, parameter })
  })

  it('rejects a multi-field or array sort with 400', () => {
    expect(failure(summary({ sort: 'name,-aiScore' }))).toMatchObject({ status: 400, parameter: 'sort' })
    expect(failure(summary({ sort: ['name'] }))).toMatchObject({ status: 400, parameter: 'sort' })
  })

  it.each([
    [{ included: [] }, '/included'],
    [{ data: { ...statusBody().data, relationships: {} } }, '/data/relationships'],
    [{ data: { ...statusBody().data, attributes: { status: 'rejected', aiScore: 100 } } }, '/data/attributes/aiScore'],
    [statusBody({ attributes: { status: 'Rejected' } }), '/data/attributes/status'],
    [statusBody({ attributes: { status: 1 } }), '/data/attributes/status'],
    [statusBody({ attributes: {} }), '/data/attributes'],
    [statusBody({ id: 'abc' }), '/data/id'],
  ])('rejects document %o with a JSON pointer', (body, pointer) => {
    const doc = 'included' in (body as object) ? { ...statusBody(), ...(body as object) } : body
    expect(failure(patch(doc))).toMatchObject({ status: 422, pointer })
  })

  it('rejects query parameters on a write', () => {
    expect(failure(patch(statusBody(), { notify: '1' }))).toMatchObject({ status: 400, parameter: 'notify' })
  })

  it('still accepts a valid strict request', () => {
    const doc = mockSummary(MOCK_CAMPAIGN_ID, { min_score: '0', 'filter[status]': 'pending', sort: '-appliedAt', 'page[size]': '100', 'page[number]': '1' }, '/x')
    expect(doc.data.length).toBeGreaterThan(0)
    expect(mockUpdateStatus(APPLICATION, statusBody()).data.attributes.status).toBe('rejected')
  })
})
