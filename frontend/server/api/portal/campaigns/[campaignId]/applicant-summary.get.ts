import { mockSummary } from '../../../../utils/mockApplicants'
import { portalCall } from '../../../../utils/portalApi'

export default defineEventHandler(event => {
  const campaignId = getRouterParam(event, 'campaignId') ?? ''
  const query = getQuery(event)

  return portalCall(
    event,
    `/api/v1/campaigns/${encodeURIComponent(campaignId)}/applicant-summary`,
    { query },
    partnerId => mockSummary(campaignId, query, getRequestURL(event).pathname, partnerId),
  )
})
