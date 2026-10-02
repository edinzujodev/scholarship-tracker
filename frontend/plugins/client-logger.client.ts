import type { ClientLogBatch } from '~/types/clientLog'
import { createClientLogQueue, messageOf, normaliseRoutePattern, type ClientLogInput } from '~/utils/clientLogQueue'

const ENDPOINT = '/api/portal/logs'

/**
 * Browser error reporting. Captures Vue errors, unhandled errors and promise
 * rejections, and lets app code report handled problems (failed API calls,
 * rolled-back status changes) via `useNuxtApp().$clientLog.report(...)`.
 * Batches go to POST /api/portal/logs with sendBeacon, which also works while
 * the page is closing.
 */
export default defineNuxtPlugin(nuxtApp => {
  const router = useRouter()
  // The route *pattern* (/campaigns/:campaignId), never the filled-in URL.
  // Vue Router patterns carry regex groups and modifiers (":campaignId()",
  // ":slug?"), which the strict server-side route rule would reject.
  const routePattern = () => normaliseRoutePattern(router.currentRoute.value.matched.at(-1)?.path || '/')

  const send = (batch: ClientLogBatch) => {
    const body = JSON.stringify(batch)
    try {
      if (navigator.sendBeacon?.(ENDPOINT, new Blob([body], { type: 'application/json' }))) return
    } catch {
      // fall through to fetch
    }
    fetch(ENDPOINT, { method: 'POST', body, headers: { 'Content-Type': 'application/json' }, keepalive: true }).catch(() => {})
  }

  const queue = createClientLogQueue({ send })
  const report = (input: Omit<ClientLogInput, 'route'> & { route?: string }) => queue.report({ ...input, route: input.route ?? routePattern() })

  nuxtApp.hook('vue:error', error => {
    report({ type: 'vue_error', message: messageOf(error) })
  })
  nuxtApp.hook('app:error', error => {
    // 4xx app errors (e.g. "Page not accessible") are expected outcomes.
    const status = (error as { statusCode?: number }).statusCode
    if (status !== undefined && status < 500) return
    report({ type: 'unhandled_error', message: messageOf(error), status })
  })
  window.addEventListener('error', event => report({ type: 'unhandled_error', message: event.message || messageOf(event.error) }))
  window.addEventListener('unhandledrejection', event => report({ type: 'unhandled_rejection', message: messageOf(event.reason) }))
  window.addEventListener('pagehide', () => queue.flush())
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') queue.flush()
  })

  return { provide: { clientLog: { report, flush: queue.flush } } }
})
