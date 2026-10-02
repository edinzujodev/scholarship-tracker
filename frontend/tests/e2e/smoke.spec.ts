import { expect, test, type Page } from '@playwright/test'

/** Navigate and wait until Vue has hydrated the SSR markup (clicks before that are ignored). */
async function open(page: Page, url = '/') {
  await page.goto(url)
  await page.waitForFunction(() => Boolean((document.querySelector('#__nuxt') as { __vue_app__?: unknown } | null)?.__vue_app__))
}

const EMPTY_CAMPAIGN = '3c6e2b8a-1f4d-4e5a-8b7c-2d9e0f1a2b3c'
const MAIN_CAMPAIGN = '9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01'
const BOARD = `/campaigns/${MAIN_CAMPAIGN}`

test.describe('Applicant review portal (smoke)', () => {
  test('the campaign table is server-rendered with its rows, not an empty state', async ({ request }) => {
    const html = await (await request.get('/', { headers: { Accept: 'text/html' } })).text()

    expect(html.match(/data-testid="campaign-row"/g)).toHaveLength(3)
    expect(html).not.toContain('data-testid="campaigns-empty"')
  })

  test('campaign list links to each campaign and back', async ({ page }) => {
    await open(page, '/')

    await expect(page.getByTestId('campaign-row')).toHaveCount(3)
    await expect(page.getByText('Northbridge Bursary 2026')).toHaveCount(0)

    await page.getByTestId('campaign-row').filter({ hasText: 'Global Scholars Programme 2026' }).getByTestId('campaign-link').click()
    await expect(page).toHaveURL(new RegExp(`/campaigns/${MAIN_CAMPAIGN}$`))
    await expect(page.getByTestId('applicant-card')).toHaveCount(6)

    await page.getByTestId('back-to-campaigns').click()
    await expect(page.getByTestId('campaign-row')).toHaveCount(3)

    // Clicking anywhere on the row (not just the name) opens the campaign too.
    await page.getByTestId('campaign-row').filter({ hasText: 'Global Scholars Programme 2026' }).getByTestId('campaign-applicants').click()
    await expect(page).toHaveURL(new RegExp(`/campaigns/${MAIN_CAMPAIGN}$`))
  })

  test('renders server-side applicant cards with scores and highlights', async ({ page }) => {
    await open(page, BOARD)

    // The title exists for screen readers only (visually hidden).
    await expect(page.locator('#board-title')).toHaveClass(/sr-only/)
    await expect(page.getByTestId('summary-total')).toHaveText('28')
    await expect(page.getByTestId('applicant-card')).toHaveCount(6)
    await expect(page.getByTestId('applicant-card').first().getByTestId('highlights').locator('li')).not.toHaveCount(0)
    await expect(page.getByTestId('loading-skeleton')).toHaveCount(0)
  })

  test('the score slider filters the list and shows an empty state at 100', async ({ page }) => {
    await open(page, BOARD)
    const slider = page.getByLabel('Minimum score')

    await slider.fill('90')
    await expect(page.getByTestId('min-score-value')).toHaveText('90')
    await expect(page.getByTestId('applicant-card').first()).toBeVisible()
    await expect
      .poll(async () => (await page.getByTestId('score-badge').allTextContents()).every(t => Number.parseInt(t, 10) >= 90))
      .toBe(true)

    await slider.fill('100')
    await expect(page.getByTestId('empty-state')).toBeVisible()
    await expect(page.getByTestId('applicant-card')).toHaveCount(0)

    await page.getByRole('button', { name: 'Reset filter' }).click()
    await expect(page.getByTestId('applicant-card')).toHaveCount(6)
  })

  test('shortlisting updates the card status', async ({ page }) => {
    await open(page, BOARD)
    const card = page.locator('[data-testid="applicant-card"][data-status="pending"]').first()
    const id = await card.getAttribute('data-application-id')
    const target = page.locator(`[data-application-id="${id}"]`)

    await target.getByTestId('shortlist-button').click()

    await expect(target).toHaveAttribute('data-status', 'shortlisted')
    await expect(target.getByTestId('status')).toHaveText('Shortlisted')
    await expect(target.getByTestId('shortlist-button')).toBeDisabled()

    await target.getByTestId('pending-button').click()
    await expect(target).toHaveAttribute('data-status', 'pending')
    await expect(target.getByTestId('status')).toHaveText('Pending review')
    await expect(target.getByTestId('pending-button')).toBeDisabled()
  })

  test('a failed reject is rolled back and a toast explains why', async ({ page }) => {
    await page.route('**/api/portal/applications/*', route =>
      route.fulfill({
        status: 503,
        contentType: 'application/vnd.api+json',
        body: JSON.stringify({ errors: [{ status: '503', code: 'unavailable', title: 'Unavailable', detail: 'Review service is down.' }] }),
      }),
    )
    await open(page, BOARD)
    const id = await page.locator('[data-testid="applicant-card"][data-status="pending"]').first().getAttribute('data-application-id')
    const target = page.locator(`[data-application-id="${id}"]`)

    await target.getByTestId('reject-button').click()

    await expect(page.getByText("Couldn't do the operation")).toBeVisible()
    await expect(page.getByText(/Review service is down./)).toBeVisible()
    await expect(target).toHaveAttribute('data-status', 'pending')
  })

  test('status filter and sort (right side of the filter bar) update the list', async ({ page }) => {
    await open(page, BOARD)
    await expect(page.getByTestId('applicant-card')).toHaveCount(6)

    await page.getByTestId('status-filter').click()
    await page.getByRole('option', { name: 'Shortlisted' }).click()
    await expect
      .poll(async () => page.locator('[data-testid="applicant-card"]').evaluateAll(cards => cards.map(c => c.getAttribute('data-status'))))
      .toEqual(expect.arrayContaining(['shortlisted']))
    await expect(page.locator('[data-testid="applicant-card"]:not([data-status="shortlisted"])')).toHaveCount(0)

    await page.getByTestId('sort-select').click()
    await page.getByRole('option', { name: 'Last name: A to Z' }).click()
    // Sorted by last name, then first name (demo names have no middle name).
    const key = (name: string) => `${name.trim().split(' ').at(-1)} ${name.trim().split(' ')[0]}`
    await expect
      .poll(async () => {
        const names = await page.getByTestId('applicant-name').allTextContents()
        return names.length > 0 && names.join('|') === [...names].sort((a, b) => key(a).localeCompare(key(b))).join('|')
      })
      .toBe(true)
  })

  test('an empty campaign shows the empty state', async ({ page }) => {
    await open(page, `/campaigns/${EMPTY_CAMPAIGN}`)

    await expect(page.getByTestId('empty-state')).toContainText('This campaign has no applicants yet.')
    await expect(page.getByTestId('summary-total')).toHaveText('0')
  })
})
