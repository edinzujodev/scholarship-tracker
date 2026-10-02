import type { H3Event } from 'h3'

/** Mock session cookie holding the partner's API token (httpOnly in real life). */
export const SESSION_COOKIE = 'portal_session'

/**
 * Mock authentication for the portal. The partner token for this request is,
 * in order of precedence:
 *   1. an `Authorization: Bearer <token>` header on the incoming request
 *      (curl, Playwright, or a gateway in front of the portal),
 *   2. the `portal_session` cookie,
 *   3. the configured demo token (NUXT_PARTNER_TOKEN). Set it to an empty
 *      string to require an explicit header or cookie.
 *
 * The token never reaches browser JavaScript: the Nitro server forwards it to
 * Laravel (or checks it in the mock), which answers 401 for unknown tokens.
 */
export function resolvePartnerToken(event: H3Event): string | null {
  const header = getRequestHeader(event, 'authorization')
  const bearer = header?.match(/^Bearer\s+(.+)$/i)?.[1]?.trim()
  if (bearer) return bearer

  const cookie = getCookie(event, SESSION_COOKIE)?.trim()
  if (cookie) return cookie

  const { partnerToken } = useRuntimeConfig(event)
  return partnerToken ? String(partnerToken) : null
}
