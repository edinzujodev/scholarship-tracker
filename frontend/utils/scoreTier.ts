import type { ScoreTier } from '~/types/applicant'

/*
 * Presentation only. The frontend never decides which tier a score is in:
 * the API sends each application's `tier`, and the numeric thresholds
 * (top tier, strong, standout) arrive in `meta.thresholds`.
 * The single source of truth is backend/app/Enums/ScoreTier.php.
 * Tier names are translated labels: `tier.<tier>` in i18n/locales/en.json.
 */

/** Tailwind classes for the score badge; contrast checked against WCAG AA for small text. */
export const TIER_BADGE: Record<ScoreTier, string> = {
  top: 'bg-emerald-100 text-emerald-800 ring-emerald-600/30',
  strong: 'bg-amber-100 text-amber-900 ring-amber-600/30',
  review: 'bg-rose-100 text-rose-800 ring-rose-600/30',
}
