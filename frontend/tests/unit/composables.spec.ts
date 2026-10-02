import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'

/** Runs a composable inside a mounted component (they need the Nuxt and i18n context). */
async function withSetup<T>(composable: () => T): Promise<T> {
  let result!: T
  await mountSuspended(
    defineComponent({
      setup() {
        result = composable()
        return () => h('div')
      },
    }),
  )
  return result
}

describe('useLazyPaging', () => {
  it('maps 1-based API pages to the 0-based first row of the paginator', async () => {
    const pageNumber = ref(3)
    const paging = await withSetup(() => useLazyPaging({ pageNumber, pageSize: 6, goToPage: vi.fn(), setPageSize: vi.fn() }))

    expect(paging.firstRow.value).toBe(12)
    pageNumber.value = 1
    expect(paging.firstRow.value).toBe(0)
  })

  it('turns a page click into goToPage and a rows change into setPageSize', async () => {
    const goToPage = vi.fn()
    const setPageSize = vi.fn()
    const paging = await withSetup(() => useLazyPaging({ pageNumber: 1, pageSize: 6, goToPage, setPageSize }))

    paging.onPage({ page: 2, rows: 6 })
    paging.onPage({ page: 0, rows: 12 })

    expect(goToPage).toHaveBeenCalledExactlyOnceWith(3)
    expect(setPageSize).toHaveBeenCalledExactlyOnceWith(12)
  })

  it('builds the page report from the locale, leaving PrimeVue placeholders in place', async () => {
    const paging = await withSetup(() => useLazyPaging({ pageNumber: 1, pageSize: 6, goToPage: vi.fn(), setPageSize: vi.fn() }))

    expect(paging.reportTemplate.value).toBe('{first}–{last} of {totalRecords}')
    expect(paging.template).toContain('RowsPerPageDropdown')
  })
})

describe('useFormatters', () => {
  it('formats days in British English and UTC, with a translated fallback', async () => {
    const { formatDay } = await withSetup(() => useFormatters())

    expect(formatDay('2026-09-14T23:30:00+00:00')).toBe('14 Sept 2026')
    expect(formatDay('not a date')).toBe('Unknown date')
  })

  it('shows averages to one decimal, or a dash when there is no data', async () => {
    const { formatAverage } = await withSetup(() => useFormatters())

    expect(formatAverage(63.14, true)).toBe('63.1')
    expect(formatAverage(0, false)).toBe('–')
  })
})

describe('isoDay', () => {
  it('round-trips the local calendar day without a UTC shift', () => {
    const picked = new Date(2026, 8, 1, 0, 30)

    expect(toIsoDay(picked)).toBe('2026-09-01')
    expect(toIsoDay(fromIsoDay('2026-09-01'))).toBe('2026-09-01')
  })
})
