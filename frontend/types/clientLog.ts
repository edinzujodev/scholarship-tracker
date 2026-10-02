/**
 * Browser → server error reports (POST /api/portal/logs). Deliberately small:
 * no page content, no applicant data, no tokens. Only what is needed to find
 * the problem: what kind, where (route pattern), and the request id.
 */
export const CLIENT_LOG_TYPES = ['vue_error', 'unhandled_error', 'unhandled_rejection', 'api_error', 'status_rollback'] as const
export type ClientLogType = (typeof CLIENT_LOG_TYPES)[number]

export interface ClientLogEvent {
  type: ClientLogType
  /** Short description, at most 500 characters. */
  message: string
  /** Route pattern such as /campaigns/:campaignId (never the filled-in URL). */
  route: string
  /** ISO-8601 time in the browser. */
  occurredAt: string
  /** HTTP status of a failed API call, if any. */
  status?: number
  /** The X-Request-Id of the failed API call, linking browser, proxy and API logs. */
  requestId?: string
}

export interface ClientLogBatch {
  events: ClientLogEvent[]
}

export const CLIENT_LOG_LIMITS = {
  maxEventsPerBatch: 20,
  maxBodyBytes: 16_384,
  maxMessageLength: 500,
  maxRouteLength: 200,
} as const
