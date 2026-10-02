/**
 * Contract for GET /api/v1/campaigns/{campaignId}/applicant-summary (JSON:API 1.1).
 * Mirrors backend/app/Http/JsonApi/ApplicantSummaryDocument.php.
 */

export const APPLICATION_STATUSES = ['pending', 'shortlisted', 'rejected'] as const
export type ApplicationStatus = (typeof APPLICATION_STATUSES)[number]

export type ScoreTier = 'top' | 'strong' | 'review'

/** JSON:API sort values accepted by the API (leading '-' = descending). */
export const APPLICATION_SORTS = ['-aiScore', 'aiScore', '-appliedAt', 'appliedAt', 'name', '-name'] as const
export type ApplicationSort = (typeof APPLICATION_SORTS)[number]

/** An application: one applicant's submission to one campaign, with its AI review. */
export interface ApplicationAttributes {
  aiScore: number
  tier: ScoreTier
  eligible: boolean
  status: ApplicationStatus
  aiHighlights: string[]
  /** ISO-8601 timestamp */
  appliedAt: string
}

export interface ResourceIdentifier<T extends string> {
  type: T
  id: string
}

export interface ApplicationResource {
  type: 'applications'
  id: string
  attributes: ApplicationAttributes
  relationships: {
    applicant: { data: ResourceIdentifier<'applicants'> }
    campaign: { data: ResourceIdentifier<'campaigns'> }
  }
}

/** The person. The same applicant can have applications in several campaigns. */
export interface ApplicantResource {
  type: 'applicants'
  id: string
  attributes: {
    firstName: string
    /** Optional; null when the applicant has none. */
    middleName: string | null
    lastName: string
  }
}

/**
 * Flat view model the UI works with: one application plus its applicant's
 * name, resolved from the compound document's `included` array.
 */
export interface ReviewItem extends ApplicationAttributes {
  /** Application id: the resource that status changes apply to. */
  id: string
  applicantId: string
  firstName: string
  middleName: string | null
  lastName: string
  /** Display name composed from the parts ("First Middle Last"). */
  name: string
}

export interface TopTierApplication {
  applicationId: string
  applicantId: string
  name: string
  aiScore: number
  aiHighlights: string[]
}

export interface CampaignSummary {
  totalApplicants: number
  eligibleApplicants: number
  eligibleRate: number
  averageAiScore: number
  /** Number of applications at or above the top-tier score (topTier is capped). */
  topTierCount: number
  topTier: TopTierApplication[]
}

export interface PageMeta {
  number: number
  size: number
  total: number
  lastPage: number
}

/** Score thresholds published by the API (backend ScoreTier is the single source). */
export interface ScoreThresholds {
  /** Scores at or above this are "top tier". */
  topTier: number
  /** Scores at or above this (and below topTier) are "strong". */
  strong: number
  /** Scores strictly above this are highlighted as standouts. */
  standoutAbove: number
}

export interface ApplicantSummaryMeta {
  campaign: { id: string; name: string }
  summary: CampaignSummary
  filters: {
    minScore: number | null
    status: ApplicationStatus | null
    appliedFrom: string | null
    appliedTo: string | null
  }
  sort: ApplicationSort
  thresholds: ScoreThresholds
  page: PageMeta
}

export interface PaginationLinks {
  self: string
  first: string
  prev: string | null
  next: string | null
  last: string
}

/** Compound document: `data` = applications, `included` = their applicants. */
export interface ApplicantSummaryDocument {
  jsonapi?: { version: string }
  data: ApplicationResource[]
  included: ApplicantResource[]
  meta: ApplicantSummaryMeta
  links: PaginationLinks
}

/** Response of PATCH /api/v1/applications/{applicationId}. */
export interface ApplicationDocument {
  jsonapi?: { version: string }
  data: ApplicationResource
  included?: ApplicantResource[]
}

export interface JsonApiError {
  status: string
  code: string
  title: string
  detail: string
  source?: { parameter?: string; pointer?: string }
}

export interface JsonApiErrorDocument {
  errors: JsonApiError[]
}

export interface ApplicationFilters {
  minScore: number
  status: ApplicationStatus | null
  /** YYYY-MM-DD, inclusive (UTC day) */
  appliedFrom: string | null
  /** YYYY-MM-DD, inclusive (UTC day) */
  appliedTo: string | null
}

export interface ApplicantSummaryParams {
  filters?: Partial<ApplicationFilters>
  sort?: ApplicationSort
  pageNumber?: number
  pageSize?: number
}

/** GET /api/v1/campaigns: the partner's campaigns with applicant counts. */
export interface CampaignAttributes {
  name: string
  /** ISO-8601 timestamp */
  createdAt: string
  applicantCount: number
  eligibleCount: number
  /** Percentage of applications marked eligible, one decimal (0 when there are none). */
  eligibleRate: number
  averageAiScore: number
  pendingCount: number
  shortlistedCount: number
  rejectedCount: number
}

export interface CampaignResource {
  type: 'campaigns'
  id: string
  attributes: CampaignAttributes
  links?: { applicantSummary: string }
}

export interface CampaignListDocument {
  jsonapi?: { version: string }
  data: CampaignResource[]
  meta: { page: PageMeta }
  links: PaginationLinks
}

/** GET /api/v1/campaigns/{campaignId}: one campaign the caller may access. */
export interface CampaignDocument {
  jsonapi?: { version: string }
  data: {
    type: 'campaigns'
    id: string
    attributes: { name: string; createdAt: string }
    links?: { self: string; applicantSummary: string }
  }
}
