import { REDACTED, scrubString } from '../../utils/redact'

/**
 * Structured JSON logging for the Nuxt server (one object per line on stdout),
 * the same shape and redaction rules as the Laravel API, so a log platform
 * can query both by `request_id`.
 */
type Level = 'debug' | 'info' | 'notice' | 'warning' | 'error'
export type LogChannel = 'http' | 'upstream' | 'security' | 'client' | 'app'
export interface LogEntry {
  datetime: string
  level: Level
  channel: LogChannel
  service: 'portal'
  message: string
  context: Record<string, unknown>
}

const MASK = REDACTED
const SENSITIVE_KEY = /(authorization|token|password|passwd|secret|cookie|session|api[_-]?key|email|e-mail)/i

export { scrubString }

export function redact(value: unknown, depth = 0): unknown {
  if (depth > 6) return '[DEPTH]'
  if (typeof value === 'string') return scrubString(value)
  if (value instanceof Error) return { name: value.name, message: scrubString(value.message) }
  if (Array.isArray(value)) return value.map(item => redact(item, depth + 1))
  if (value && typeof value === 'object') {
    return Object.fromEntries(
      Object.entries(value).map(([key, item]) => [key, SENSITIVE_KEY.test(key) ? MASK : redact(item, depth + 1)]),
    )
  }
  return value
}

type Sink = (entry: LogEntry) => void
const stdout: Sink = entry => console.log(JSON.stringify(entry))
let sink: Sink = process.env.VITEST ? () => {} : stdout

/** Test hook: capture entries instead of printing them. Returns a restore function. */
export function captureLogs(capture: Sink): () => void {
  const previous = sink
  sink = capture
  return () => {
    sink = previous
  }
}

export function log(level: Level, channel: LogChannel, message: string, context: Record<string, unknown> = {}): void {
  sink({
    datetime: new Date().toISOString(),
    level,
    channel,
    service: 'portal',
    message: scrubString(message),
    context: redact(context) as Record<string, unknown>,
  })
}

/** Route patterns, not raw paths: ids become `:id`, so logs group and never echo values. */
export function pathPattern(path: string): string {
  return path
    .split('?')[0]!
    .replace(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/gi, ':id')
}

export function queryNames(query: Record<string, unknown>): string[] {
  return Object.keys(query)
}
