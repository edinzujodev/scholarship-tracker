import { afterEach, describe, expect, it, vi } from 'vitest'
import { captureLogs, log, pathPattern, type LogEntry } from '~~/server/utils/logger'
import { createRateLimiter, parseClientLogBatch } from '~~/server/utils/clientLogs'
import { isRequestId } from '~~/server/utils/requestId'
import { createClientLogQueue, normaliseRoutePattern } from '~/utils/clientLogQueue'
import type { ClientLogBatch } from '~/types/clientLog'

const validEvent = {
  type: 'status_rollback',
  message: 'Status change failed',
  route: '/campaigns/:campaignId',
  occurredAt: '2026-10-01T09:00:00.000Z',
  status: 503,
  requestId: 'portal-7f3c2a9e',
}

describe('server logger', () => {
  let restore: (() => void) | undefined
  afterEach(() => restore?.())

  it('writes structured entries and redacts secrets and personal data', () => {
    const entries: LogEntry[] = []
    restore = captureLogs(entry => entries.push(entry))

    log('warning', 'upstream', 'Call for ada@example.test failed', {
      request_id: 'portal-7f3c2a9e',
      Authorization: 'Bearer demo-partner-token',
      nested: { cookie: 'portal_session=abc', note: 'token Bearer abc.def', url: '/x?token=s3cret&page=2' },
    })

    expect(entries).toHaveLength(1)
    const [entry] = entries
    expect(entry).toMatchObject({ level: 'warning', channel: 'upstream', service: 'portal', message: 'Call for [REDACTED] failed' })
    expect(entry!.context).toEqual({
      request_id: 'portal-7f3c2a9e',
      Authorization: '[REDACTED]',
      nested: { cookie: '[REDACTED]', note: 'token Bearer [REDACTED]', url: '/x?token=[REDACTED]&page=2' },
    })
  })

  it('logs route patterns, never filled-in ids', () => {
    expect(pathPattern('/api/portal/campaigns/9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01/applicant-summary?min_score=80')).toBe(
      '/api/portal/campaigns/:id/applicant-summary',
    )
  })

  it('accepts only short, plain request ids (safe to echo in headers and logs)', () => {
    expect(isRequestId('portal-7f3c2a9e')).toBe(true)
    expect(isRequestId('9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01')).toBe(true)
    expect(isRequestId('short')).toBe(false)
    expect(isRequestId('bad id\r\nX-Injected: 1')).toBe(false)
    expect(isRequestId('x'.repeat(65))).toBe(false)
  })
})

describe('client log endpoint validation', () => {
  it('accepts a valid batch', () => {
    const parsed = parseClientLogBatch(JSON.stringify({ events: [validEvent] }))
    expect(parsed.ok).toBe(true)
  })

  it.each([
    ['not JSON', '{"events":', 400, ''],
    ['an array body', '[]', 400, ''],
    ['unknown top-level member', JSON.stringify({ events: [validEvent], extra: 1 }), 422, '/extra'],
    ['no events', JSON.stringify({ events: [] }), 422, '/events'],
    ['too many events', JSON.stringify({ events: Array(21).fill(validEvent) }), 422, '/events'],
    ['unknown event member', JSON.stringify({ events: [{ ...validEvent, html: '<b>x</b>' }] }), 422, '/events/0/html'],
    ['unknown type', JSON.stringify({ events: [{ ...validEvent, type: 'debug' }] }), 422, '/events/0/type'],
    ['message too long', JSON.stringify({ events: [{ ...validEvent, message: 'x'.repeat(501) }] }), 422, '/events/0/message'],
    ['full URL instead of a route', JSON.stringify({ events: [{ ...validEvent, route: 'https://evil.test/' }] }), 422, '/events/0/route'],
    ['local time', JSON.stringify({ events: [{ ...validEvent, occurredAt: '2026-10-01 09:00' }] }), 422, '/events/0/occurredAt'],
    ['impossible status', JSON.stringify({ events: [{ ...validEvent, status: 999 }] }), 422, '/events/0/status'],
    ['bad request id', JSON.stringify({ events: [{ ...validEvent, requestId: 'x y' }] }), 422, '/events/0/requestId'],
  ])('rejects %s', (_label, raw, status, pointer) => {
    const parsed = parseClientLogBatch(raw)
    expect(parsed.ok).toBe(false)
    if (parsed.ok) return
    expect(parsed.status).toBe(status)
    expect(parsed.errors[0]!.source?.pointer).toBe(pointer)
  })

  it('rejects bodies over 16 KB', () => {
    const parsed = parseClientLogBatch(JSON.stringify({ events: [validEvent], pad: 'x'.repeat(17_000) }))
    expect(parsed).toMatchObject({ ok: false, status: 413 })
  })

  it('rate-limits per client and resets after the window', () => {
    const allow = createRateLimiter(2, 60_000)
    expect([allow('1.2.3.4', 0), allow('1.2.3.4', 1), allow('1.2.3.4', 2)]).toEqual([true, true, false])
    expect(allow('5.6.7.8', 3)).toBe(true)
    expect(allow('1.2.3.4', 60_001)).toBe(true)
  })
})

describe('client log queue (browser)', () => {
  afterEach(() => vi.useRealTimers())

  it('batches, scrubs and caps reports before anything leaves the browser', () => {
    vi.useFakeTimers()
    const sent: ClientLogBatch[] = []
    const queue = createClientLogQueue({ send: batch => sent.push(batch), maxBatch: 3, maxPerSession: 4, flushDelayMs: 1000 })

    queue.report({ type: 'api_error', message: 'Bearer abc.def failed for ada@example.test', route: '/campaigns/:campaignId', status: 503 })
    expect(sent).toHaveLength(0) // waits for more or for the timer
    vi.advanceTimersByTime(1000)
    expect(sent).toHaveLength(1)
    expect(sent[0]!.events[0]!.message).toBe('Bearer [REDACTED] failed for [REDACTED]')
    expect(sent[0]!.events[0]!.occurredAt).toMatch(/Z$/)

    queue.report({ type: 'vue_error', message: 'a', route: '/' })
    queue.report({ type: 'vue_error', message: 'b', route: '/' })
    queue.report({ type: 'vue_error', message: 'c', route: '/' }) // 4th accepted: cap reached
    expect(queue.report({ type: 'vue_error', message: 'd', route: '/' })).toBe(false)
    queue.flush()
    expect(sent.flatMap(b => b.events).map(e => e.message)).toEqual(['Bearer [REDACTED] failed for [REDACTED]', 'a', 'b', 'c'])
  })

  it('normalises Vue Router patterns so the server accepts them', () => {
    expect(normaliseRoutePattern('/campaigns/:campaignId()')).toBe('/campaigns/:campaignId')
    expect(normaliseRoutePattern('/files/:path(.*)*')).toBe('/files/:path')
    expect(normaliseRoutePattern('/:slug?')).toBe('/:slug')
    for (const route of ['/campaigns/:campaignId()', '/files/:path(.*)*']) {
      const parsed = parseClientLogBatch(JSON.stringify({ events: [{ ...validEvent, route: normaliseRoutePattern(route) }] }))
      expect(parsed.ok).toBe(true)
    }
  })

  it('truncates long messages to the server limit', () => {
    const sent: ClientLogBatch[] = []
    const queue = createClientLogQueue({ send: batch => sent.push(batch), maxBatch: 1 })
    queue.report({ type: 'vue_error', message: 'x'.repeat(2000), route: '/' })
    expect(sent[0]!.events[0]!.message).toHaveLength(500)
  })
})
