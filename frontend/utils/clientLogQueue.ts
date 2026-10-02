import type { ClientLogBatch, ClientLogEvent } from '~/types/clientLog'
import { CLIENT_LOG_LIMITS } from '~/types/clientLog'
import { scrubString } from '~/utils/redact'

export type ClientLogInput = Omit<ClientLogEvent, 'occurredAt'> & { occurredAt?: string }

interface Options {
  /** Delivers one batch (sendBeacon in the browser). Must not throw. */
  send: (batch: ClientLogBatch) => void
  /** Flush as soon as this many events are waiting. */
  maxBatch?: number
  /** Hard cap per page session, so a crash loop cannot flood the logs. */
  maxPerSession?: number
  /** Otherwise flush after this delay. */
  flushDelayMs?: number
}

/**
 * Batches browser error reports. Each event is scrubbed (tokens, emails) and
 * trimmed to the server's limits before it leaves the browser.
 */
export function createClientLogQueue({ send, maxBatch = 10, maxPerSession = 50, flushDelayMs = 5_000 }: Options) {
  const queue: ClientLogEvent[] = []
  let accepted = 0
  let timer: ReturnType<typeof setTimeout> | undefined

  function flush(): void {
    clearTimeout(timer)
    timer = undefined
    while (queue.length) {
      send({ events: queue.splice(0, Math.min(maxBatch, CLIENT_LOG_LIMITS.maxEventsPerBatch)) })
    }
  }

  function report(input: ClientLogInput): boolean {
    if (accepted >= maxPerSession) return false
    accepted++
    queue.push({
      ...input,
      message: scrubString(input.message || 'Unknown error').slice(0, CLIENT_LOG_LIMITS.maxMessageLength),
      route: input.route.slice(0, CLIENT_LOG_LIMITS.maxRouteLength) || '/',
      occurredAt: input.occurredAt ?? new Date().toISOString(),
    })
    if (queue.length >= maxBatch) flush()
    else if (timer === undefined) timer = setTimeout(flush, flushDelayMs)
    return true
  }

  return { report, flush, pending: () => queue.length }
}

/**
 * "/campaigns/:campaignId()" → "/campaigns/:campaignId": drops Vue Router
 * regex groups and param modifiers so the pattern passes the server's
 * strict route rule.
 */
export function normaliseRoutePattern(path: string): string {
  const cleaned = path.replace(/\([^)]*\)/g, '').replace(/[?*+]/g, '')
  return cleaned.startsWith('/') ? cleaned : `/${cleaned}`
}

/** A readable message from anything thrown. */
export function messageOf(error: unknown): string {
  if (error instanceof Error) return `${error.name}: ${error.message}`
  if (typeof error === 'string') return error
  try {
    return JSON.stringify(error) ?? 'Unknown error'
  } catch {
    return 'Unknown error'
  }
}
