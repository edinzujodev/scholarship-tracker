import type { H3Event } from 'h3'
import { FetchError, ofetch } from 'ofetch'
import { MOCK_TOKENS, MockApiError } from './mockApplicants'
import { resolvePartnerToken } from './partnerSession'
import { log, pathPattern } from './logger'
import { REQUEST_ID_HEADER, requestIdOf } from './requestId'
import type { JsonApiError } from '../../types/applicant'

const JSON_API = 'application/vnd.api+json'

/**
 * Backend-for-frontend helper. When NUXT_BACKEND_URL is set, forwards the call
 * to Laravel with the partner token resolved for this request (see
 * partnerSession.ts); otherwise the mock checks the same token and runs as
 * that partner.
 * Either way the browser only ever sees JSON:API documents from /api/portal.
 */
export async function portalCall<T>(
  event: H3Event,
  backendPath: string,
  init: { method?: 'GET' | 'PATCH'; query?: Record<string, unknown>; body?: unknown },
  mock: (partnerId: string) => T,
): Promise<T> {
  const { backendUrl } = useRuntimeConfig(event)
  const partnerToken = resolvePartnerToken(event)
  const requestId = requestIdOf(event)
  const method = init.method ?? 'GET'
  const started = performance.now()
  setResponseHeader(event, 'Content-Type', JSON_API)
  setResponseHeader(event, 'Cache-Control', 'private, no-store')

  /** One line per call to the API (or the mock), so slowness can be placed. */
  const logUpstream = (target: 'laravel' | 'mock', status: number) =>
    log(status >= 500 ? 'error' : status >= 400 ? 'notice' : 'info', 'upstream', 'API call', {
      request_id: requestId,
      target,
      method,
      path: pathPattern(backendPath),
      status,
      duration_ms: Math.round((performance.now() - started) * 100) / 100,
    })

  /** Error documents created here carry the request id, like Laravel's. */
  const errorDocument = (status: number, errors: JsonApiError[]) => {
    setResponseStatus(event, status)
    return { jsonapi: { version: '1.1' }, errors, ...(requestId ? { meta: { requestId } } : {}) } as T
  }

  if (!backendUrl) {
    // Same rule as the Laravel middleware: unknown or missing token → 401.
    const partnerId = partnerToken ? MOCK_TOKENS[partnerToken] : undefined
    if (!partnerId) {
      setResponseHeader(event, 'WWW-Authenticate', 'Bearer')
      logUpstream('mock', 401)
      log('warning', 'security', 'Authentication failed', { request_id: requestId, status: 401, path: pathPattern(backendPath) })
      return errorDocument(401, [{ status: '401', code: 'unauthenticated', title: 'Unauthenticated', detail: 'A valid partner bearer token is required.' }])
    }
    try {
      const doc = mock(partnerId)
      logUpstream('mock', 200)
      return doc
    } catch (error) {
      if (error instanceof MockApiError) {
        logUpstream('mock', error.status)
        return errorDocument(error.status, error.errors)
      }
      throw error
    }
  }

  try {
    const doc = await ofetch<T>(backendPath, {
      baseURL: backendUrl,
      method,
      query: init.query,
      body: init.body as Record<string, unknown> | undefined,
      headers: {
        Accept: JSON_API,
        'Content-Type': JSON_API,
        Authorization: `Bearer ${partnerToken ?? ''}`,
        // Laravel reuses this id, so both services log the same request_id.
        ...(requestId ? { [REQUEST_ID_HEADER]: requestId } : {}),
      },
      timeout: 5000,
    })
    logUpstream('laravel', 200)
    return rewriteLinks(doc, backendUrl)
  } catch (error) {
    // Pass JSON:API error documents from Laravel through unchanged (they carry meta.requestId).
    if (error instanceof FetchError && error.response) {
      logUpstream('laravel', error.response.status)
      setResponseStatus(event, error.response.status)
      return error.data as T
    }
    logUpstream('laravel', 502)
    log('error', 'app', 'API unreachable', { request_id: requestId, path: pathPattern(backendPath), error })
    return errorDocument(502, [{ status: '502', code: 'upstream_unavailable', title: 'Bad gateway', detail: 'The applicant service is unavailable.' }])
  }
}

type Links = Record<string, string | null>

/**
 * Laravel builds absolute links (pagination and per-resource) with its own
 * internal host. Rewrite them to the public /api/portal paths so the internal
 * hostname never reaches the browser and the links stay usable from the client.
 */
function rewriteLinks<T>(doc: T, backendUrl: string): T {
  const prefix = `${backendUrl.replace(/\/+$/, '')}/api/v1/`
  const rewrite = (links: Links | undefined) => {
    if (!links) return
    for (const [key, href] of Object.entries(links)) {
      if (typeof href === 'string' && href.startsWith(prefix)) {
        links[key] = `/api/portal/${href.slice(prefix.length)}`
      }
    }
  }

  const body = doc as { links?: Links; data?: { links?: Links } | { links?: Links }[] } | null
  rewrite(body?.links)
  const data = body?.data
  for (const resource of Array.isArray(data) ? data : data ? [data] : []) rewrite(resource.links)
  return doc
}
