import { expect, test, type Page } from '@playwright/test'

const MAIN_CAMPAIGN = '9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01'

async function hydrated(page: Page) {
  await page.waitForFunction(() => Boolean((document.querySelector('#__nuxt') as { __vue_app__?: unknown } | null)?.__vue_app__))
}

test.describe('Logging and request correlation', () => {
  test('pages and portal API responses carry an X-Request-Id', async ({ page }) => {
    const apiResponse = page.waitForResponse(r => r.url().includes('/api/portal/') && r.request().method() === 'GET')
    const pageResponse = await page.goto('/')

    expect(pageResponse?.headers()['x-request-id']).toMatch(/^[A-Za-z0-9-]{8,64}$/)
    await page.getByTestId('campaign-link').first().click()
    expect((await apiResponse).headers()['x-request-id']).toMatch(/^[A-Za-z0-9-]{8,64}$/)
  })

  test('a failed status change shows a support reference and reports to /api/portal/logs', async ({ page }) => {
    await page.route('**/api/portal/applications/*', route =>
      route.fulfill({
        status: 503,
        contentType: 'application/vnd.api+json',
        headers: { 'X-Request-Id': 'e2e-ref-0001' },
        body: JSON.stringify({
          errors: [{ status: '503', code: 'unavailable', title: 'Unavailable', detail: 'Review service is down.' }],
          meta: { requestId: 'e2e-ref-0001' },
        }),
      }),
    )
    await page.goto(`/campaigns/${MAIN_CAMPAIGN}`)
    await hydrated(page)

    // The browser reporter batches and sends within a few seconds.
    const report = page.waitForRequest(r => r.url().endsWith('/api/portal/logs') && r.method() === 'POST', { timeout: 15_000 })
    await page.locator('[data-testid="applicant-card"][data-status="pending"]').first().getByTestId('reject-button').click()

    await expect(page.getByText('Reference: e2e-ref-0001')).toBeVisible()

    const batch = JSON.parse((await report).postData() ?? '{}') as { events: Array<Record<string, unknown>> }
    expect(batch.events).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ type: 'status_rollback', status: 503, requestId: 'e2e-ref-0001', route: '/campaigns/:campaignId' }),
      ]),
    )
  })

  test('the access check failure page shows the reference of the failed check', async ({ page }) => {
    const response = await page.goto('/campaigns/11111111-2222-4333-8444-555555555555')

    expect(response?.status()).toBe(404)
    await expect(page.getByTestId('error-reference')).toHaveText(/^[A-Za-z0-9-]{8,64}$/)
  })
})
