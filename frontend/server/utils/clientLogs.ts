import type { JsonApiError } from '../../types/applicant'
import { CLIENT_LOG_LIMITS, CLIENT_LOG_TYPES, type ClientLogEvent } from '../../types/clientLog'
import { isRequestId } from './requestId'

/**
 * Strict validation for browser error reports. The endpoint is public (any
 * page can post to it), so it must not become a way to write arbitrary data
 * into the logs: fixed schema, no unknown members, bounded sizes.
 */
type Parsed = { ok: true; events: ClientLogEvent[] } | { ok: false; status: number; errors: JsonApiError[] }

const ROUTE = /^\/[A-Za-z0-9/_:.[\]-]*$/
const ISO_TIME = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,3})?Z$/
const EVENT_KEYS = ['type', 'message', 'route', 'occurredAt', 'status', 'requestId']

const invalid = (pointer: string, detail: string): JsonApiError => ({
  status: '422',
  code: 'validation_failed',
  title: 'Invalid attribute',
  detail,
  source: { pointer },
})

const malformed = (detail: string): Parsed => ({
  ok: false,
  status: 400,
  errors: [{ status: '400', code: 'malformed_body', title: 'Bad request', detail, source: { pointer: '' } }],
})

export function parseClientLogBatch(raw: string): Parsed {
  if (Buffer.byteLength(raw, 'utf8') > CLIENT_LOG_LIMITS.maxBodyBytes) {
    return {
      ok: false,
      status: 413,
      errors: [{ status: '413', code: 'payload_too_large', title: 'Payload too large', detail: `At most ${CLIENT_LOG_LIMITS.maxBodyBytes} bytes.` }],
    }
  }

  let body: unknown
  try {
    body = JSON.parse(raw)
  } catch {
    return malformed('The body is not valid JSON.')
  }
  if (typeof body !== 'object' || body === null || Array.isArray(body)) return malformed('The body must be a JSON object.')

  const errors: JsonApiError[] = []
  for (const key of Object.keys(body)) if (key !== 'events') errors.push(invalid(`/${key}`, `The member "${key}" is not allowed here.`))

  const events = (body as { events?: unknown }).events
  if (!Array.isArray(events) || events.length === 0 || events.length > CLIENT_LOG_LIMITS.maxEventsPerBatch) {
    errors.push(invalid('/events', `events must be a list of 1 to ${CLIENT_LOG_LIMITS.maxEventsPerBatch} items.`))
    return { ok: false, status: 422, errors }
  }

  events.forEach((event: unknown, i) => {
    const at = `/events/${i}`
    if (typeof event !== 'object' || event === null || Array.isArray(event)) {
      errors.push(invalid(at, 'Each event must be an object.'))
      return
    }
    const e = event as Record<string, unknown>
    for (const key of Object.keys(e)) if (!EVENT_KEYS.includes(key)) errors.push(invalid(`${at}/${key}`, `The member "${key}" is not allowed here.`))
    if (!(CLIENT_LOG_TYPES as readonly unknown[]).includes(e.type)) errors.push(invalid(`${at}/type`, `type must be one of ${CLIENT_LOG_TYPES.join(', ')}.`))
    if (typeof e.message !== 'string' || e.message.trim() === '' || e.message.length > CLIENT_LOG_LIMITS.maxMessageLength) {
      errors.push(invalid(`${at}/message`, `message must be 1 to ${CLIENT_LOG_LIMITS.maxMessageLength} characters.`))
    }
    if (typeof e.route !== 'string' || e.route.length > CLIENT_LOG_LIMITS.maxRouteLength || !ROUTE.test(e.route)) {
      errors.push(invalid(`${at}/route`, 'route must be a route pattern such as /campaigns/:campaignId.'))
    }
    if (typeof e.occurredAt !== 'string' || !ISO_TIME.test(e.occurredAt) || Number.isNaN(Date.parse(e.occurredAt))) {
      errors.push(invalid(`${at}/occurredAt`, 'occurredAt must be an ISO-8601 UTC timestamp.'))
    }
    if (e.status !== undefined && (!Number.isInteger(e.status) || (e.status as number) < 100 || (e.status as number) > 599)) {
      errors.push(invalid(`${at}/status`, 'status must be an HTTP status code.'))
    }
    if (e.requestId !== undefined && !isRequestId(e.requestId)) errors.push(invalid(`${at}/requestId`, 'requestId is not a valid request id.'))
  })

  return errors.length ? { ok: false, status: 422, errors } : { ok: true, events: events as ClientLogEvent[] }
}

/**
 * Fixed-window rate limit per client key (IP): at most `limit` batches per
 * window. In-memory, so per server instance; with several instances a shared
 * store such as Redis would hold the counters.
 */
export function createRateLimiter(limit: number, windowMs = 60_000) {
  const windows = new Map<string, { start: number; count: number }>()
  return (key: string, now = Date.now()): boolean => {
    const current = windows.get(key)
    if (!current || now - current.start >= windowMs) {
      if (windows.size > 10_000) windows.clear() // bound memory under abuse
      windows.set(key, { start: now, count: 1 })
      return true
    }
    current.count++
    return current.count <= limit
  }
}
