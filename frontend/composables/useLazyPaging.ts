import type { MaybeRefOrGetter } from 'vue'

interface LazyPagingOptions {
  /** Current page, 1-based (JSON:API page[number]). */
  pageNumber: MaybeRefOrGetter<number>
  pageSize: MaybeRefOrGetter<number>
  goToPage: (pageNumber: number) => unknown
  /** A new page size always restarts from page 1. */
  setPageSize: (pageSize: number) => unknown
}

/** PrimeVue's paginator layout, shared by every paged list. */
export const PAGINATOR_TEMPLATE = 'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport'

/**
 * Connects a PrimeVue paginator (Paginator, DataTable) to server-side,
 * JSON:API page-based paging: the paginator thinks in 0-based rows, the API
 * in 1-based page numbers. Used by the applicant board and the campaign list.
 */
export function useLazyPaging(options: LazyPagingOptions) {
  const { t } = useI18n()

  const firstRow = computed(() => (toValue(options.pageNumber) - 1) * toValue(options.pageSize))

  function onPage(event: { page: number; rows: number }) {
    if (event.rows !== toValue(options.pageSize)) options.setPageSize(event.rows)
    else options.goToPage(event.page + 1)
  }

  // PrimeVue fills {first}, {last} and {totalRecords}; the wording comes from the locale.
  const reportTemplate = computed(() => t('pagination.report', { first: '{first}', last: '{last}', total: '{totalRecords}' }))

  return { firstRow, onPage, template: PAGINATOR_TEMPLATE, reportTemplate }
}
