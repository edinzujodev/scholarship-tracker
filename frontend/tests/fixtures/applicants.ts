import type { ReviewItem } from '~/types/applicant'

/** One application (with its applicant's name) as the UI sees it. */
export function applicationFixture(overrides: Partial<ReviewItem> = {}): ReviewItem {
  return {
    id: 'a1b2c3d4-0000-4000-8000-000000000001',
    applicantId: 'a1b2c3d4-0000-4000-a000-000000000001',
    firstName: 'Ada',
    middleName: null,
    lastName: 'Lovelace',
    name: 'Ada Lovelace',
    aiScore: 92,
    tier: 'top',
    eligible: true,
    status: 'pending',
    aiHighlights: ['Top 1% maths olympiad', 'Published research on analytical engines'],
    appliedAt: '2026-09-14T08:30:00+00:00',
    ...overrides,
  }
}
