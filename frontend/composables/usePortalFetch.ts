import type { FetchOptions } from 'ofetch'

/**
 * $fetch for /api/portal calls.
 *
 * During SSR it forwards the page request's cookie / Authorization header
 * (useRequestFetch) and the page's X-Request-Id, so every API call made while
 * rendering a page logs under the same request id as the page itself.
 * In the browser it is plain $fetch; each call gets its own id from the server.
 */
/**
 * A deliberately plain signature. Nitro types $fetch per server route (it
 * matches the URL against every route to infer the response). Comparing or
 * casting to that generic type makes the TypeScript server give up with
 * "Excessive stack depth comparing types"; callers here always name the
 * response type themselves, so the route inference adds nothing.
 */
export type PortalFetch = <T>(url: string, options?: FetchOptions<'json'>) => Promise<T>

export function usePortalFetch(): PortalFetch {
  // Through `unknown`, so TypeScript does not compare the two function types.
  const requestFetch = useRequestFetch() as unknown as PortalFetch
  const requestId = import.meta.server ? (useRequestEvent()?.context.requestId as string | undefined) : undefined
  if (!requestId) return requestFetch

  return <T>(url: string, options: FetchOptions<'json'> = {}) =>
    requestFetch<T>(url, {
      ...options,
      headers: { ...((options.headers as Record<string, string> | undefined) ?? {}), 'X-Request-Id': requestId },
    })
}

/** The request id of a failed API call, from the error document or the response header. */
export function requestIdFromError(error: unknown): string | undefined {
  const e = error as { data?: { meta?: { requestId?: unknown } }; response?: { headers?: Headers } } | null
  const fromBody = e?.data?.meta?.requestId
  if (typeof fromBody === 'string') return fromBody
  return e?.response?.headers?.get('x-request-id') ?? undefined
}
