import { APPLICATION_SORTS, APPLICATION_STATUSES } from '../../types/applicant'
import type {
  ApplicantResource,
  ApplicantSummaryDocument,
  ApplicationAttributes,
  ApplicationDocument,
  ApplicationResource,
  ApplicationSort,
  ApplicationStatus,
  CampaignDocument,
  CampaignListDocument,
  JsonApiError,
  ScoreThresholds,
  ScoreTier,
} from '../../types/applicant'
import { UUID_PATTERN } from '../../utils/ids'
import { fullName } from '../../utils/jsonApi'

/**
 * In-memory stand-in for the Laravel API, implementing the same contract
 * (applicants vs applications, filtering, paging, aggregates, compound
 * documents, JSON:API errors). Used when NUXT_BACKEND_URL is not set, so the
 * portal and its tests run without PHP.
 *
 * Mirrors backend/database/seeders/DatabaseSeeder.php: people are a shared
 * pool, and people 1–5 have applications in two campaigns.
 */

export const MOCK_CAMPAIGN_ID = '9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01'
export const MOCK_EMPTY_CAMPAIGN_ID = '3c6e2b8a-1f4d-4e5a-8b7c-2d9e0f1a2b3c'
export const MOCK_FELLOWSHIP_CAMPAIGN_ID = '5d8e1c2a-3b4f-4c6d-9e7f-8a9b0c1d2e3f'
/** Belongs to another partner: the mock (acting as partner-demo) must answer 404 for it. */
export const MOCK_OTHER_PARTNER_CAMPAIGN_ID = '7a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d'
/** Default partner when a caller does not say otherwise (tests, demo). */
export const MOCK_PARTNER = 'partner-demo'
/** Mock bearer tokens, mirroring backend/config/partners.php. */
export const MOCK_TOKENS: Readonly<Record<string, string>> = {
  'demo-partner-token': 'partner-demo',
  'other-partner-token': 'partner-other',
}

const FIRST = ['Amara', 'Luka', 'Sofia', 'Kenji', 'Leila', 'Mateo', 'Ines', 'Noah', 'Zara', 'Emil', 'Yara', 'Omar', 'Hana']
const LAST = ['Okafor', 'Horvat', 'Rossi', 'Tanaka', 'Haddad', 'Silva', 'Kovač', 'Berg', 'Nguyen', 'Fischer']
const STRONG = [
  'Top 5% in quantitative reasoning assessment',
  'Led a 12-person robotics team to national finals',
  'Personal statement strongly aligned with programme goals',
  'Fluent in three languages, including the programme language',
  'Two relevant internships in data engineering',
  'Recommendation letters describe exceptional initiative',
]
const WEAK = [
  'Recommendation letters describe exceptional initiative',
  'Essay lacks concrete examples of impact',
  'Missing one required transcript (flagged for follow-up)',
  'Volunteer tutoring record of 200+ hours',
  'Portfolio shows strong applied ML projects',
]

interface MockApplication {
  id: string
  applicantId: string
  campaignId: string
  attributes: ApplicationAttributes
}

interface MockCampaign {
  id: string
  partnerId: string
  name: string
  createdAt: string
  applications: MockApplication[]
}

interface MockDb {
  people: Map<string, ApplicantResource>
  campaigns: Map<string, MockCampaign>
}

/** Small deterministic PRNG (mulberry32) so the demo data is stable. */
function prng(seed: number) {
  return () => {
    seed = (seed + 0x6d2b79f5) | 0
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed)
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296
  }
}

const pad = (n: number) => String(n).padStart(12, '0')
const personId = (n: number) => `00000000-0000-4000-a000-${pad(n)}`

function person(n: number): ApplicantResource {
  return {
    type: 'applicants',
    id: personId(n),
    // Middle name deliberately left empty, as in the Laravel seeder.
    attributes: { firstName: FIRST[(n - 1) % FIRST.length]!, middleName: null, lastName: LAST[((n - 1) * 7) % LAST.length]! },
  }
}

function buildApplications(campaignId: string, personNumbers: number[], seed: number, idGroup: string): MockApplication[] {
  const rand = prng(seed)
  return personNumbers.map((n, i) => {
    const score = 35 + Math.floor(rand() * 65)
    const pool = score >= 70 ? STRONG : WEAK
    return {
      id: `00000000-0000-4000-${idGroup}-${pad(i + 1)}`,
      applicantId: personId(n),
      campaignId,
      attributes: {
        aiScore: score,
        tier: tierFor(score),
        eligible: score >= 60 && i % 5 !== 0,
        status: (i % 9 === 0 ? 'shortlisted' : 'pending') as ApplicationStatus,
        aiHighlights: [...new Set([pool[i % pool.length]!, pool[(i + 2) % pool.length]!, pool[(i + 3) % pool.length]!])],
        appliedAt: new Date(Date.UTC(2026, 8, 1 + (i % 28), 9, i)).toISOString(),
      },
    }
  })
}

/**
 * The mock plays the backend, so it carries the backend's thresholds
 * (mirrors backend/app/Enums/ScoreTier.php) and publishes them in
 * meta.thresholds exactly like the real API. The UI itself hard-codes none.
 */
const THRESHOLDS: ScoreThresholds = { topTier: 85, strong: 70, standoutAbove: 90 }

function tierFor(score: number): ScoreTier {
  if (score >= THRESHOLDS.topTier) return 'top'
  if (score >= THRESHOLDS.strong) return 'strong'
  return 'review'
}

const range = (from: number, to: number) => Array.from({ length: to - from + 1 }, (_, i) => from + i)

let state: MockDb | null = null

function db(): MockDb {
  if (state) return state
  const createdAt = '2026-08-15T09:00:00.000Z'
  const campaigns: MockCampaign[] = [
    {
      id: MOCK_CAMPAIGN_ID,
      partnerId: MOCK_PARTNER,
      name: 'Global Scholars Programme 2026',
      createdAt,
      applications: buildApplications(MOCK_CAMPAIGN_ID, range(1, 28), 2026, '8000'),
    },
    {
      id: MOCK_FELLOWSHIP_CAMPAIGN_ID,
      partnerId: MOCK_PARTNER,
      name: 'Engineering Fellowship 2026',
      createdAt,
      // People 1–5 also applied to Global Scholars.
      applications: buildApplications(MOCK_FELLOWSHIP_CAMPAIGN_ID, [...range(1, 5), ...range(29, 35)], 7, '8001'),
    },
    { id: MOCK_EMPTY_CAMPAIGN_ID, partnerId: MOCK_PARTNER, name: 'Spring Pilot (no applicants yet)', createdAt, applications: [] },
    {
      id: MOCK_OTHER_PARTNER_CAMPAIGN_ID,
      partnerId: 'partner-other',
      name: 'Northbridge Bursary 2026',
      createdAt,
      applications: buildApplications(MOCK_OTHER_PARTNER_CAMPAIGN_ID, range(36, 38), 99, '8002'),
    },
  ]
  state = {
    people: new Map(range(1, 38).map(n => [personId(n), person(n)])),
    campaigns: new Map(campaigns.map(c => [c.id, c])),
  }
  return state
}

/** Test hook: restore the pristine dataset. */
export function resetMockApplicants(): void {
  state = null
}

export class MockApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly errors: JsonApiError[],
  ) {
    super(errors[0]?.detail ?? 'Mock API error')
  }
}

function invalid(parameter: string, detail: string): JsonApiError {
  return { status: '422', code: 'validation_failed', title: 'Invalid attribute', detail, source: { parameter } }
}

function notFound(detail: string): MockApiError {
  return new MockApiError(404, [{ status: '404', code: 'not_found', title: 'Resource not found', detail }])
}

/** Canonical non-negative integer: digits only, no sign/space/decimal/leading zero (same rule as the API). */
const CANONICAL_INTEGER = /^(0|[1-9][0-9]*)$/

function intParam(query: Record<string, unknown>, key: string): number | undefined | 'invalid' {
  const raw = query[key]
  if (raw === undefined) return undefined
  if (typeof raw !== 'string' || !CANONICAL_INTEGER.test(raw)) return 'invalid'
  return Number(raw)
}

/** A real calendar day in YYYY-MM-DD (rejects 2026-02-30 and time suffixes). */
function isRealDay(value: unknown): value is string {
  if (typeof value !== 'string' || !/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(value)) return false
  const [y, m, d] = value.split('-').map(Number)
  const date = new Date(Date.UTC(y!, m! - 1, d!))
  return date.getUTCFullYear() === y && date.getUTCMonth() === m! - 1 && date.getUTCDate() === d
}

/**
 * Like the API: unknown top-level query parameters are a 400 (JSON:API), and
 * unknown keys inside a supported family (filter[...], page[...]) are a 422.
 * `allowed` maps a family to its allowed nested keys, or null for a scalar.
 */
function assertQuery(query: Record<string, unknown>, allowed: Record<string, readonly string[] | null>): void {
  const unknown: string[] = []
  const badFamilies: string[] = []
  for (const key of Object.keys(query)) {
    const open = key.indexOf('[')
    const family = open === -1 ? key : key.slice(0, open)
    const member = open === -1 ? undefined : key.slice(open + 1, key.endsWith(']') ? -1 : undefined)
    if (!(family in allowed)) {
      if (!unknown.includes(family)) unknown.push(family)
      continue
    }
    const members = allowed[family]
    const nested = member !== undefined
    const ok = members === null || members === undefined ? !nested : nested && members.includes(member!)
    if (!ok && !badFamilies.includes(family)) badFamilies.push(family)
  }
  if (unknown.length) {
    throw new MockApiError(
      400,
      unknown.map(name => ({
        status: '400',
        code: 'unsupported_parameter',
        title: 'Bad request',
        detail: `The query parameter "${name}" is not supported by this endpoint.`,
        source: { parameter: name },
      })),
    )
  }
  if (badFamilies.length) {
    throw new MockApiError(422, badFamilies.map(name => invalid(name, `Unsupported keys in ${name}[...].`)))
  }
}

function findCampaign(campaignId: string, partnerId: string): MockCampaign {
  if (!UUID_PATTERN.test(campaignId)) {
    throw new MockApiError(422, [invalid('campaignId', 'The campaign id must be a valid UUID.')])
  }
  const campaign = db().campaigns.get(campaignId.toLowerCase())
  // Same 404 for "missing" and "someone else's", exactly like the Laravel API.
  if (!campaign || campaign.partnerId !== partnerId) throw notFound(`Campaign "${campaignId}" was not found.`)
  return campaign
}

function personOf(application: MockApplication): ApplicantResource['attributes'] {
  return db().people.get(application.applicantId)?.attributes ?? { firstName: '', middleName: null, lastName: '' }
}

function nameOf(application: MockApplication): string {
  return fullName(personOf(application))
}

/** Directory order, like the Laravel API: last name, then first, then middle. */
function byLastName(a: MockApplication, b: MockApplication): number {
  const x = personOf(a)
  const y = personOf(b)
  return x.lastName.localeCompare(y.lastName) || x.firstName.localeCompare(y.firstName) || (x.middleName ?? '').localeCompare(y.middleName ?? '')
}

function toResource(application: MockApplication): ApplicationResource {
  return {
    type: 'applications',
    id: application.id,
    attributes: structuredClone(application.attributes),
    relationships: {
      applicant: { data: { type: 'applicants', id: application.applicantId } },
      campaign: { data: { type: 'campaigns', id: application.campaignId } },
    },
  }
}

function included(applications: MockApplication[]): ApplicantResource[] {
  const ids = [...new Set(applications.map(a => a.applicantId))]
  return ids.map(id => structuredClone(db().people.get(id)!))
}

type Cmp = (a: MockApplication, b: MockApplication) => number
const SORTS: Record<ApplicationSort, Cmp> = {
  '-aiScore': (a, b) => b.attributes.aiScore - a.attributes.aiScore || a.attributes.appliedAt.localeCompare(b.attributes.appliedAt),
  aiScore: (a, b) => a.attributes.aiScore - b.attributes.aiScore || a.attributes.appliedAt.localeCompare(b.attributes.appliedAt),
  '-appliedAt': (a, b) => b.attributes.appliedAt.localeCompare(a.attributes.appliedAt),
  appliedAt: (a, b) => a.attributes.appliedAt.localeCompare(b.attributes.appliedAt),
  name: (a, b) => byLastName(a, b),
  '-name': (a, b) => byLastName(b, a),
}
const withIdTiebreak = (cmp: Cmp): Cmp => (a, b) => cmp(a, b) || a.id.localeCompare(b.id)

export function mockSummary(
  campaignId: string,
  query: Record<string, unknown>,
  selfUrl: string,
  partnerId: string = MOCK_PARTNER,
): ApplicantSummaryDocument {
  assertQuery(query, {
    min_score: null,
    sort: null,
    filter: ['status', 'applied_from', 'applied_to'],
    page: ['size', 'number'],
  })
  const campaign = findCampaign(campaignId, partnerId)

  const sortParam = query.sort === undefined ? '-aiScore' : query.sort
  if (typeof sortParam !== 'string' || !(APPLICATION_SORTS as readonly string[]).includes(sortParam)) {
    throw new MockApiError(400, [
      { status: '400', code: 'unsupported_sort', title: 'Bad request', detail: `Unsupported sort. Allowed: ${APPLICATION_SORTS.join(', ')}.`, source: { parameter: 'sort' } },
    ])
  }
  const sort = sortParam as ApplicationSort

  const minScore = intParam(query, 'min_score')
  const size = intParam(query, 'page[size]') ?? 10
  const number = intParam(query, 'page[number]') ?? 1
  const status = query['filter[status]'] as string | undefined
  const appliedFrom = query['filter[applied_from]'] as string | undefined
  const appliedTo = query['filter[applied_to]'] as string | undefined

  const errors: JsonApiError[] = []
  if (minScore === 'invalid' || (minScore !== undefined && (minScore < 0 || minScore > 100))) {
    errors.push(invalid('min_score', 'The min score field must be between 0 and 100.'))
  }
  if (size === 'invalid' || size < 1 || size > 100) errors.push(invalid('page[size]', 'The page size field must be between 1 and 100.'))
  if (number === 'invalid' || number < 1) errors.push(invalid('page[number]', 'The page number field must be at least 1.'))
  if (status !== undefined && (typeof status !== 'string' || !(APPLICATION_STATUSES as readonly string[]).includes(status))) {
    errors.push(invalid('filter[status]', 'The selected filter.status is invalid.'))
  }
  if (appliedFrom !== undefined && !isRealDay(appliedFrom)) errors.push(invalid('filter[applied_from]', 'Use a real date in the format YYYY-MM-DD.'))
  if (appliedTo !== undefined && !isRealDay(appliedTo)) errors.push(invalid('filter[applied_to]', 'Use a real date in the format YYYY-MM-DD.'))
  if (appliedFrom && appliedTo && appliedTo < appliedFrom) {
    errors.push(invalid('filter[applied_to]', 'filter[applied_to] must be on or after filter[applied_from].'))
  }
  if (errors.length) throw new MockApiError(422, errors)

  const all = campaign.applications
  const pageSize = size as number
  const pageNumber = number as number
  const total = all.length
  const eligible = all.filter(a => a.attributes.eligible).length
  const average = total ? Math.round((all.reduce((s, a) => s + a.attributes.aiScore, 0) / total) * 100) / 100 : 0
  const topTierAll = all.filter(a => a.attributes.aiScore >= THRESHOLDS.topTier).sort(withIdTiebreak(SORTS['-aiScore']))

  // appliedAt is ISO-8601 UTC, so comparing the YYYY-MM-DD prefix is an inclusive UTC-day range.
  const filtered = all
    .filter(a => minScore === undefined || a.attributes.aiScore >= (minScore as number))
    .filter(a => status === undefined || a.attributes.status === status)
    .filter(a => !appliedFrom || a.attributes.appliedAt.slice(0, 10) >= appliedFrom)
    .filter(a => !appliedTo || a.attributes.appliedAt.slice(0, 10) <= appliedTo)
    .sort(withIdTiebreak(SORTS[sort]))
  const lastPage = Math.max(1, Math.ceil(filtered.length / pageSize))
  const page = filtered.slice((pageNumber - 1) * pageSize, pageNumber * pageSize)

  const baseParams: Record<string, string> = {}
  for (const [key, value] of Object.entries(query)) if (typeof value === 'string' && !key.startsWith('page[')) baseParams[key] = value
  const link = (n: number) =>
    `${selfUrl}?${new URLSearchParams({ ...baseParams, 'page[size]': String(pageSize), 'page[number]': String(n) })}`

  return {
    jsonapi: { version: '1.1' },
    data: page.map(toResource),
    included: included(page),
    meta: {
      campaign: { id: campaign.id, name: campaign.name },
      summary: {
        totalApplicants: total,
        eligibleApplicants: eligible,
        eligibleRate: total ? Math.round((eligible / total) * 1000) / 10 : 0,
        averageAiScore: average,
        topTierCount: topTierAll.length,
        topTier: topTierAll.slice(0, 5).map(a => ({
          applicationId: a.id,
          applicantId: a.applicantId,
          name: nameOf(a),
          aiScore: a.attributes.aiScore,
          aiHighlights: a.attributes.aiHighlights.slice(0, 2),
        })),
      },
      filters: {
        minScore: minScore === undefined ? null : (minScore as number),
        status: (status as ApplicationStatus | undefined) ?? null,
        appliedFrom: appliedFrom ?? null,
        appliedTo: appliedTo ?? null,
      },
      sort,
      thresholds: { ...THRESHOLDS },
      page: { number: pageNumber, size: pageSize, total: filtered.length, lastPage },
    },
    links: {
      self: link(pageNumber),
      first: link(1),
      prev: pageNumber > 1 ? link(Math.min(pageNumber - 1, lastPage)) : null,
      next: pageNumber < lastPage ? link(pageNumber + 1) : null,
      last: link(lastPage),
    },
  }
}

/** PATCH /api/v1/applications/{applicationId} */
export function mockUpdateStatus(
  applicationId: string,
  body: unknown,
  partnerId: string = MOCK_PARTNER,
  query: Record<string, unknown> = {},
): ApplicationDocument {
  assertQuery(query, {}) // writes take no query parameters
  const errors: JsonApiError[] = []
  const pointerError = (pointer: string, detail: string): JsonApiError => ({
    status: '422',
    code: 'validation_failed',
    title: 'Invalid attribute',
    detail,
    source: { pointer },
  })
  const isObject = (v: unknown): v is Record<string, unknown> => typeof v === 'object' && v !== null && !Array.isArray(v)
  const rejectUnknown = (object: unknown, allowed: string[], path: string) => {
    if (!isObject(object)) return
    for (const member of Object.keys(object)) {
      if (!allowed.includes(member)) errors.push(pointerError(`${path}/${member}`, `The member "${member}" is not allowed here.`))
    }
  }

  if (!UUID_PATTERN.test(applicationId)) errors.push(invalid('applicationId', 'The application id must be a valid UUID.'))
  const doc = isObject(body) ? body : {}
  const data = doc.data
  if (!isObject(data)) {
    errors.push(pointerError('/data', 'The data member is required.'))
  } else {
    if (typeof data.type !== 'string') errors.push(pointerError('/data/type', 'data.type must be a string.'))
    if (typeof data.id !== 'string' || !UUID_PATTERN.test(data.id)) errors.push(pointerError('/data/id', 'data.id must be a valid UUID.'))
    const attributes = data.attributes
    if (!isObject(attributes) || Object.keys(attributes).length === 0) {
      errors.push(pointerError('/data/attributes', 'data.attributes is required.'))
    } else if (typeof attributes.status !== 'string' || !(APPLICATION_STATUSES as readonly string[]).includes(attributes.status)) {
      errors.push(pointerError('/data/attributes/status', 'The selected status is invalid.'))
    }
  }
  rejectUnknown(doc, ['data', 'meta', 'jsonapi'], '')
  rejectUnknown(data, ['type', 'id', 'attributes', 'meta'], '/data')
  rejectUnknown(isObject(data) ? data.attributes : undefined, ['status'], '/data/attributes')
  if (errors.length) throw new MockApiError(422, errors)

  const valid = data as { type: string; id: string; attributes: { status: ApplicationStatus } }
  if (valid.type !== 'applications' || valid.id.toLowerCase() !== applicationId.toLowerCase()) {
    throw new MockApiError(409, [{ status: '409', code: 'conflict', title: 'Conflict', detail: 'Resource type or id does not match the URL.' }])
  }
  const status = valid.attributes.status

  // Only applications in the caller's own campaigns are visible.
  const application = [...db().campaigns.values()]
    .filter(c => c.partnerId === partnerId)
    .flatMap(c => c.applications)
    .find(a => a.id === applicationId)
  if (!application) throw notFound(`Application "${applicationId}" was not found.`)

  application.attributes.status = status as ApplicationStatus
  return { jsonapi: { version: '1.1' }, data: toResource(application), included: included([application]) }
}

export function mockCampaignList(query: Record<string, unknown>, selfUrl: string, partnerId: string = MOCK_PARTNER): CampaignListDocument {
  assertQuery(query, { page: ['size', 'number'] })
  const size = intParam(query, 'page[size]') ?? 20
  const number = intParam(query, 'page[number]') ?? 1
  const errors: JsonApiError[] = []
  if (size === 'invalid' || size < 1 || size > 100) errors.push(invalid('page[size]', 'The page size field must be between 1 and 100.'))
  if (number === 'invalid' || number < 1) errors.push(invalid('page[number]', 'The page number field must be at least 1.'))
  if (errors.length) throw new MockApiError(422, errors)

  const pageSize = size as number
  const pageNumber = number as number
  const own = [...db().campaigns.values()]
    .filter(c => c.partnerId === partnerId)
    .sort((a, b) => b.createdAt.localeCompare(a.createdAt) || a.name.localeCompare(b.name) || a.id.localeCompare(b.id))
  const lastPage = Math.max(1, Math.ceil(own.length / pageSize))
  const link = (n: number) => `${selfUrl}?${new URLSearchParams({ 'page[size]': String(pageSize), 'page[number]': String(n) })}`
  const count = (c: MockCampaign, status: ApplicationStatus) => c.applications.filter(a => a.attributes.status === status).length
  const eligibleOf = (c: MockCampaign) => c.applications.filter(a => a.attributes.eligible).length

  return {
    jsonapi: { version: '1.1' },
    data: own.slice((pageNumber - 1) * pageSize, pageNumber * pageSize).map(c => ({
      type: 'campaigns' as const,
      id: c.id,
      attributes: {
        name: c.name,
        createdAt: c.createdAt,
        applicantCount: c.applications.length,
        eligibleCount: eligibleOf(c),
        eligibleRate: c.applications.length ? Math.round((eligibleOf(c) / c.applications.length) * 1000) / 10 : 0,
        averageAiScore: c.applications.length
          ? Math.round((c.applications.reduce((sum, a) => sum + a.attributes.aiScore, 0) / c.applications.length) * 100) / 100
          : 0,
        pendingCount: count(c, 'pending'),
        shortlistedCount: count(c, 'shortlisted'),
        rejectedCount: count(c, 'rejected'),
      },
      links: { applicantSummary: `/api/portal/campaigns/${c.id}/applicant-summary` },
    })),
    meta: { page: { number: pageNumber, size: pageSize, total: own.length, lastPage } },
    links: {
      self: link(pageNumber),
      first: link(1),
      prev: pageNumber > 1 ? link(Math.min(pageNumber - 1, lastPage)) : null,
      next: pageNumber < lastPage ? link(pageNumber + 1) : null,
      last: link(lastPage),
    },
  }
}

/** GET /api/v1/campaigns/{campaignId}: 422 malformed id, 404 unknown or someone else's. */
export function mockCampaign(campaignId: string, partnerId: string = MOCK_PARTNER, query: Record<string, unknown> = {}): CampaignDocument {
  assertQuery(query, {}) // this endpoint takes no query parameters
  const campaign = findCampaign(campaignId, partnerId)
  return {
    jsonapi: { version: '1.1' },
    data: {
      type: 'campaigns',
      id: campaign.id,
      attributes: { name: campaign.name, createdAt: campaign.createdAt },
      links: {
        self: `/api/portal/campaigns/${campaign.id}`,
        applicantSummary: `/api/portal/campaigns/${campaign.id}/applicant-summary`,
      },
    },
  }
}
