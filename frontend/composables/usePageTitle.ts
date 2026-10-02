/** Sets the browser tab title to "<page> · Partner portal", both from the locale. */
export function usePageTitle(page: () => string) {
  const { t } = useI18n()
  useHead({ title: () => t('app.pageTitle', { page: page() }) })
}
