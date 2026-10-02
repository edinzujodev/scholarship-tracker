/*
 * Calendar days as the API expects them (YYYY-MM-DD). Pure functions, not a
 * composable: they hold no state and need no component context.
 */

/** YYYY-MM-DD from the *local* calendar date the user picked (toISOString would shift it via UTC). */
export function toIsoDay(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

/** Local Date for a YYYY-MM-DD day, for the date picker. */
export function fromIsoDay(day: string): Date {
  const [y, m, d] = day.split('-').map(Number)
  return new Date(y!, m! - 1, d!)
}
