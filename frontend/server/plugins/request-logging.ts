import { log, pathPattern, queryNames } from '../utils/logger'
import { REQUEST_ID_HEADER, requestIdOf, resolveRequestId } from '../utils/requestId'

/**
 * Every request to the Nuxt server (pages and /api/portal) gets a correlation
 * id (X-Request-Id), which is forwarded to Laravel and written on every log
 * line, and one structured access-log line when the response is sent.
 * Static assets are skipped.
 */
const isAsset = (path: string) => path.startsWith('/_nuxt/') || path.startsWith('/__nuxt') || path.startsWith('/favicon')

export default defineNitroPlugin(nitro => {
  nitro.hooks.hook('request', event => {
    const id = resolveRequestId(event)
    event.context.requestId = id
    event.context.startedAt = performance.now()
    setResponseHeader(event, REQUEST_ID_HEADER, id)
  })

  nitro.hooks.hook('afterResponse', event => {
    const path = event.path ?? ''
    if (isAsset(path)) return

    const status = getResponseStatus(event)
    const started = event.context.startedAt as number | undefined
    const level = status >= 500 ? 'error' : status >= 400 ? 'notice' : 'info'
    log(level, 'http', path.startsWith('/api/') ? 'Portal API request' : 'Page request', {
      request_id: requestIdOf(event),
      method: event.method,
      path: pathPattern(path),
      status,
      duration_ms: started === undefined ? null : Math.round((performance.now() - started) * 100) / 100,
      query_params: queryNames(getQuery(event)),
    })
  })

  nitro.hooks.hook('error', (error, { event }) => {
    const status = (error as { statusCode?: number }).statusCode ?? 500
    // 4xx are expected outcomes and already in the access log.
    if (status < 500) return
    log('error', 'app', 'Unhandled server error', {
      request_id: event ? requestIdOf(event) : undefined,
      path: event ? pathPattern(event.path ?? '') : undefined,
      error,
    })
  })
})
