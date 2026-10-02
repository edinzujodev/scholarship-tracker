/**
 * Masks secrets and personal data inside free text. Shared by the browser
 * error reporter (scrubbed before anything leaves the browser) and the Nuxt
 * server logger. Same rules as App\Logging\RedactSensitiveData in Laravel.
 */
export const REDACTED = '[REDACTED]'

const BEARER = /Bearer\s+[A-Za-z0-9._~+/=-]+/gi
const QUERY_SECRET = /([?&](?:token|access_token|api_key|password)=)[^&\s]+/gi
const EMAIL = /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi

export function scrubString(value: string): string {
  return value.replace(BEARER, `Bearer ${REDACTED}`).replace(QUERY_SECRET, `$1${REDACTED}`).replace(EMAIL, REDACTED)
}
