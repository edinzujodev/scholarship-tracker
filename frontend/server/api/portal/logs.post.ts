import { createRateLimiter, parseClientLogBatch } from '../../utils/clientLogs'
import { jsonApiErrorReply } from '../../utils/jsonApiRequest'
import { log } from '../../utils/logger'
import { requestIdOf } from '../../utils/requestId'

const allow = createRateLimiter(30)

/**
 * POST /api/portal/logs: browser error reports, written to the `client`
 * channel of the portal's structured logs. 204 on success. Strict: JSON only
 * (415), fixed schema (422), at most 16 KB (413), 30 batches per minute per
 * IP (429).
 */
export default defineEventHandler(async event => {
  const contentType = (getRequestHeader(event, 'content-type') ?? '').split(';')[0]!.trim().toLowerCase()
  if (contentType !== 'application/json') {
    return jsonApiErrorReply(event, 415, [{ status: '415', code: 'unsupported_media_type', title: 'Unsupported media type', detail: 'Send application/json.' }])
  }

  const ip = getRequestIP(event, { xForwardedFor: true }) ?? 'unknown'
  if (!allow(ip)) {
    log('warning', 'security', 'Client log rate limit exceeded', { request_id: requestIdOf(event), ip })
    return jsonApiErrorReply(event, 429, [{ status: '429', code: 'too_many_requests', title: 'Too many requests', detail: 'Slow down.' }])
  }

  const parsed = parseClientLogBatch((await readRawBody(event, 'utf8')) ?? '')
  if (!parsed.ok) return jsonApiErrorReply(event, parsed.status, parsed.errors)

  for (const report of parsed.events) {
    const level = report.type === 'status_rollback' || report.type === 'api_error' ? 'warning' : 'error'
    log(level, 'client', report.message, {
      request_id: requestIdOf(event),
      related_request_id: report.requestId,
      type: report.type,
      route: report.route,
      status: report.status,
      occurred_at: report.occurredAt,
      user_agent: getRequestHeader(event, 'user-agent')?.slice(0, 200),
    })
  }

  setResponseStatus(event, 204)
  return null
})
