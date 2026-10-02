/**
 * Display formatting shared by every component, driven by the i18n locale
 * (formats are defined in i18n/i18n.config.ts).
 */
export function useFormatters() {
  const { t, d } = useI18n()

  /** "15 Aug 2026" from an ISO-8601 timestamp; a translated fallback for invalid input. */
  function formatDay(iso: string): string {
    const date = new Date(iso)
    return Number.isNaN(date.getTime()) ? t('common.unknownDate') : d(date, 'day')
  }

  /** One decimal for averages, or a dash when there is nothing to average. */
  function formatAverage(value: number, hasData: boolean): string {
    return hasData ? value.toFixed(1) : t('common.noValue')
  }

  return { formatDay, formatAverage }
}
