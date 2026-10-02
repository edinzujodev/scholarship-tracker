import { mockCampaign } from '../../../../utils/mockApplicants'
import { portalCall } from '../../../../utils/portalApi'

/**
 * GET /api/portal/campaigns/:campaignId → Laravel GET /api/v1/campaigns/:campaignId.
 * Used by the campaign-access route middleware before a campaign page renders.
 */
export default defineEventHandler(event => {
  const campaignId = getRouterParam(event, 'campaignId') ?? ''

  const query = getQuery(event)

  return portalCall(
    event,
    `/api/v1/campaigns/${encodeURIComponent(campaignId)}`,
    { query },
    partnerId => mockCampaign(campaignId, partnerId, query),
  )
})
