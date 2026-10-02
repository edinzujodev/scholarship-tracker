import type { CampaignDocument } from '~/types/applicant'
import { isUuid } from '~/utils/ids'

/**
 * Runs before a campaign page renders: on the server for a direct page load,
 * and in the browser for in-app navigation. The page component never sees an
 * inaccessible campaign; instead Nuxt renders error.vue ("Page not
 * accessible") with the right HTTP status:
 *
 *   400  campaign id is not a well-formed UUID (checked locally, no API call)
 *   401  no valid partner session / token
 *   403  authenticated but not allowed
 *   404  campaign does not exist, or belongs to another partner (same answer)
 *   503  the campaign service could not be reached
 */
export default defineNuxtRouteMiddleware(async to => {
  const campaignId = to.params.campaignId

  if (!isUuid(campaignId)) {
    return abortNavigation(accessError(400, 'invalid-id'))
  }

  try {
    // usePortalFetch forwards the page request's cookie, Authorization header
    // and request id during SSR, so the check runs as the caller.
    await usePortalFetch()<CampaignDocument>(`/api/portal/campaigns/${encodeURIComponent(campaignId)}`)
  } catch (error) {
    const status = (error as { statusCode?: number; status?: number }).statusCode ?? (error as { status?: number }).status
    const reference = requestIdFromError(error)
    if (status === 401 || status === 403) return abortNavigation(accessError(status, 'unauthorized', reference))
    if (status === 404) return abortNavigation(accessError(404, 'not-found', reference))
    if (status === 400 || status === 422) return abortNavigation(accessError(400, 'invalid-id', reference))
    return abortNavigation(accessError(503, 'unavailable', reference))
  }
})

type AccessReason = 'invalid-id' | 'unauthorized' | 'not-found' | 'unavailable'

const MESSAGES: Record<AccessReason, string> = {
  'invalid-id': 'Invalid campaign id',
  unauthorized: 'Not authorized',
  'not-found': 'Campaign not found',
  unavailable: 'Campaign service unavailable',
}

function accessError(statusCode: number, reason: AccessReason, requestId?: string) {
  return createError({ statusCode, statusMessage: MESSAGES[reason], data: { reason, page: 'campaign', requestId }, fatal: true })
}
