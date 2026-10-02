import { mockUpdateStatus } from '../../../utils/mockApplicants'
import { jsonApiErrorReply, readJsonApiDocument } from '../../../utils/jsonApiRequest'
import { portalCall } from '../../../utils/portalApi'

/**
 * PATCH /api/portal/applications/:applicationId → Laravel PATCH /api/v1/applications/:applicationId
 *
 * Strict: 415 for a non-JSON:API media type, 400 for a malformed body. The
 * query string is forwarded as-is so the API (or mock) rejects any parameter.
 */
export default defineEventHandler(async event => {
  const applicationId = getRouterParam(event, 'applicationId') ?? ''
  const query = getQuery(event)
  const request = await readJsonApiDocument(event)
  if (!request.ok) return jsonApiErrorReply(event, request.status, request.errors)

  return portalCall(
    event,
    `/api/v1/applications/${encodeURIComponent(applicationId)}`,
    { method: 'PATCH', body: request.body, query },
    partnerId => mockUpdateStatus(applicationId, request.body, partnerId, query),
  )
})
