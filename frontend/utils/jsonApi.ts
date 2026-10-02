import type { ApplicantResource, ApplicationResource, ReviewItem } from '~/types/applicant'

type NameParts = ApplicantResource['attributes']

/** Display name: "First Middle Last", skipping an empty middle name. */
export function fullName({ firstName, middleName, lastName }: NameParts): string {
  return [firstName, middleName, lastName].filter(part => part && part.trim() !== '').join(' ')
}

/**
 * Flattens JSON:API applications and their included applicants into the
 * view model the UI renders. Applicants are looked up through the
 * `relationships.applicant` linkage, never by position.
 */
export function toReviewItems(applications: ApplicationResource[], included: ApplicantResource[] = []): ReviewItem[] {
  const people = new Map(included.filter(r => r.type === 'applicants').map(r => [r.id, r.attributes]))

  return applications.map(application => {
    const applicantId = application.relationships.applicant.data.id
    const person = people.get(applicantId)
    return {
      ...application.attributes,
      id: application.id,
      applicantId,
      firstName: person?.firstName ?? '',
      middleName: person?.middleName ?? null,
      lastName: person?.lastName ?? '',
      name: person ? fullName(person) : 'Unknown applicant',
    }
  })
}
