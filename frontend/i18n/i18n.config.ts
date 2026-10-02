// Runtime vue-i18n options. The messages themselves live in locales/en.json.
export default defineI18nConfig(() => ({
  fallbackLocale: 'en-GB',
  // Fixed time zone, so server and client render the same date (no hydration mismatch).
  datetimeFormats: {
    'en-GB': {
      day: { dateStyle: 'medium', timeZone: 'UTC' },
    },
  },
}))
