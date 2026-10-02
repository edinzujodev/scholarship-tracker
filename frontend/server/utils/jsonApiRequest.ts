import type { H3Event } from 'h3'
import type { JsonApiError } from '../../types/applicant'
import { requestIdOf } from './requestId'

const JSON_API = 'application/vnd.api+json'

type RequestCheck = { ok: true; body: Record<string, unknown> } | { ok: false; status: number; errors: JsonApiError[] }

/**
 * Strict checks for write requests arriving at the proxy, mirroring the
 * Laravel EnsureJsonApiMediaType middleware:
 *   415 unless Content-Type is application/vnd.api+json (only ext/profile params)
 *   400 unless the body is a well-formed JSON object
 * The proxy has to parse the body to forward it, so these checks live here too.
 */
export async function readJsonApiDocument(event: H3Event): Promise<RequestCheck> {
  const [type, ...params] = (getRequestHeader(event, 'content-type') ?? '').split(';').map(part => part.trim().toLowerCase())
  const badParam = params.some(param => param !== '' && !/^(ext|profile)=/.test(param))
  if (type !== JSON_API || badParam) {
    return {
      ok: false,
      status: 415,
      errors: [{ status: '415', code: 'unsupported_media_type', title: 'Unsupported media type', detail: `Request bodies must be sent as ${JSON_API}.` }],
    }
  }

  const raw = (await readRawBody(event, 'utf8')) ?? ''
  const malformed = (detail: string): RequestCheck => ({
    ok: false,
    status: 400,
    errors: [{ status: '400', code: 'malformed_body', title: 'Bad request', detail, source: { pointer: '' } }],
  })
  if (raw.trim() === '') return malformed('The request body is empty; a JSON:API document is required.')

  let parsed: unknown
  try {
    parsed = JSON.parse(raw)
  } catch {
    return malformed('The request body is not valid JSON.')
  }
  if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
    return malformed('The request body must be a JSON object (a JSON:API document).')
  }
  return { ok: true, body: parsed as Record<string, unknown> }
}

export function jsonApiErrorReply(event: H3Event, status: number, errors: JsonApiError[]) {
  setResponseStatus(event, status)
  setResponseHeader(event, 'Content-Type', JSON_API)
  const requestId = requestIdOf(event)
  return { jsonapi: { version: '1.1' }, errors, ...(requestId ? { meta: { requestId } } : {}) }
}
