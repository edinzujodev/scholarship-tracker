import { mockCampaignList } from '../../../utils/mockApplicants'
import { portalCall } from '../../../utils/portalApi'

export default defineEventHandler(event => {
  const query = getQuery(event)

  return portalCall(event, '/api/v1/campaigns', { query }, partnerId => mockCampaignList(query, getRequestURL(event).pathname, partnerId))
})
