/**
 * Any RFC 4122 UUID (the same rule as Laravel's `uuid` validator). Shared by
 * the page access middleware and the mock API, so both agree on what a
 * well-formed campaign id is.
 */
export const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

export function isUuid(value: unknown): value is string {
  return typeof value === 'string' && UUID_PATTERN.test(value)
}
