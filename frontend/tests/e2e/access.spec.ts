import { expect, test, type Page } from '@playwright/test'

const MAIN_CAMPAIGN = '9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01'
const OTHER_PARTNER_CAMPAIGN = '7a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d'
const UNKNOWN_CAMPAIGN = '11111111-2222-4333-8444-555555555555'

async function hydrated(page: Page) {
  await page.waitForFunction(() => Boolean((document.querySelector('#__nuxt') as { __vue_app__?: unknown } | null)?.__vue_app__))
}

async function expectNotAccessible(page: Page, status: number, reason: string) {
  const panel = page.getByTestId('page-not-accessible')
  await expect(panel).toBeVisible()
  await expect(panel).toHaveAttribute('data-status', String(status))
  await expect(panel).toHaveAttribute('data-reason', reason)
  await expect(page.getByText('Page not accessible')).toBeVisible()
  // The normal page never rendered.
  await expect(page.getByTestId('applicant-card')).toHaveCount(0)
  await expect(page.getByTestId('filter-bar')).toHaveCount(0)
}

test.describe('Campaign page access checks (before render)', () => {
  test('a malformed campaign id renders "Page not accessible" with 400', async ({ page }) => {
    const response = await page.goto('/campaigns/not-a-uuid')

    expect(response?.status()).toBe(400)
    await expectNotAccessible(page, 400, 'invalid-id')
  })

  test('an unknown campaign renders "Page not accessible" with 404', async ({ page }) => {
    const response = await page.goto(`/campaigns/${UNKNOWN_CAMPAIGN}`)

    expect(response?.status()).toBe(404)
    await expectNotAccessible(page, 404, 'not-found')
  })

  test("another partner's campaign is the same 404 (no way to probe ids)", async ({ page }) => {
    const response = await page.goto(`/campaigns/${OTHER_PARTNER_CAMPAIGN}`)

    expect(response?.status()).toBe(404)
    await expectNotAccessible(page, 404, 'not-found')
  })

  test('an invalid Authorization header renders "Page not accessible" with 401', async ({ browser }) => {
    const context = await browser.newContext({ extraHTTPHeaders: { Authorization: 'Bearer wrong-token' } })
    const page = await context.newPage()

    const response = await page.goto(`/campaigns/${MAIN_CAMPAIGN}`)

    expect(response?.status()).toBe(401)
    await expectNotAccessible(page, 401, 'unauthorized')
    await context.close()
  })

  test('an invalid session cookie renders "Page not accessible" with 401', async ({ page, baseURL }) => {
    await page.context().addCookies([{ name: 'portal_session', value: 'wrong-token', url: baseURL! }])

    const response = await page.goto(`/campaigns/${MAIN_CAMPAIGN}`)

    expect(response?.status()).toBe(401)
    await expectNotAccessible(page, 401, 'unauthorized')
  })

  test("a session for the other partner opens their campaign, not ours", async ({ page, baseURL }) => {
    await page.context().addCookies([{ name: 'portal_session', value: 'other-partner-token', url: baseURL! }])

    expect((await page.goto(`/campaigns/${OTHER_PARTNER_CAMPAIGN}`))?.status()).toBe(200)
    await expect(page.getByTestId('applicant-card')).toHaveCount(3)

    expect((await page.goto(`/campaigns/${MAIN_CAMPAIGN}`))?.status()).toBe(404)
    await expectNotAccessible(page, 404, 'not-found')
  })

  test('the check also runs on in-app navigation, before the page renders', async ({ page }) => {
    await page.goto(`/campaigns/${MAIN_CAMPAIGN}`)
    await hydrated(page)
    await expect(page.getByTestId('applicant-card')).toHaveCount(6)

    // Client-side route change: no full page load, the middleware still guards it.
    // The blocked navigation rejects push(); that is expected, so it is caught here.
    await page.evaluate(id => {
      const app = (document.querySelector('#__nuxt') as unknown as { __vue_app__: { config: { globalProperties: { $router: { push: (to: string) => Promise<unknown> } } } } }).__vue_app__
      return app.config.globalProperties.$router.push(`/campaigns/${id}`).then(() => 'navigated', () => 'blocked')
    }, UNKNOWN_CAMPAIGN)

    await expectNotAccessible(page, 404, 'not-found')
  })

  test('"Back to campaigns" leaves the error page', async ({ page }) => {
    await page.goto('/campaigns/not-a-uuid')
    await hydrated(page)

    await page.getByTestId('back-to-campaigns').click()

    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByTestId('campaign-row')).toHaveCount(3)
  })
})
