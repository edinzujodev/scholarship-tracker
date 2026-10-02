import type { H3Event } from 'h3'
import { randomUUID } from 'node:crypto'

export const REQUEST_ID_HEADER = 'X-Request-Id'
/** Same rule as the Laravel API: short, plain ids only, safe to log and echo. */
const ACCEPTED = /^[A-Za-z0-9][A-Za-z0-9-]{7,63}$/

export function isRequestId(value: unknown): value is string {
  return typeof value === 'string' && ACCEPTED.test(value)
}

/** The correlation id for this request: a trusted incoming one, or a new UUID. */
export function resolveRequestId(event: H3Event): string {
  const incoming = getRequestHeader(event, REQUEST_ID_HEADER)
  return isRequestId(incoming) ? incoming : randomUUID()
}

export function requestIdOf(event: H3Event): string | undefined {
  return event.context.requestId as string | undefined
}
