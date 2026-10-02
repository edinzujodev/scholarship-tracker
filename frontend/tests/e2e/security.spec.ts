import { expect, test } from '@playwright/test'

const BOARD = '/campaigns/9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01'

test.describe('Browser security headers and CSP', () => {
  test('pages send a strict CSP and the hardening headers', async ({ page }) => {
    const response = await page.goto(BOARD)
    const headers = response!.headers()
    const csp = headers['content-security-policy']!

    // Scripts: nonce + strict-dynamic only, never 'unsafe-inline'.
    const scriptSrc = csp.split(';').map(d => d.trim()).find(d => d.startsWith('script-src '))!
    expect(scriptSrc).toMatch(/'nonce-[A-Za-z0-9+/=]+'/)
    expect(scriptSrc).toContain("'strict-dynamic'")
    expect(scriptSrc).not.toContain("'unsafe-inline'")
    expect(csp).toContain("script-src-attr 'none'")
    expect(csp).toContain("frame-ancestors 'none'")
    expect(csp).toContain("object-src 'none'")
    expect(csp).toContain("base-uri 'none'")
    expect(csp).toContain("default-src 'none'")

    expect(headers['strict-transport-security']).toContain('max-age=63072000')
    expect(headers['x-content-type-options']).toBe('nosniff')
    expect(headers['x-frame-options']).toBe('DENY')
    expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin')
    expect(headers['permissions-policy']).toContain('camera=()')
  })

  test('every page load gets a fresh nonce', async ({ request }) => {
    const nonce = async () => /'nonce-([^']+)'/.exec((await request.get(BOARD)).headers()['content-security-policy']!)?.[1]
    expect(await nonce()).not.toBe(await nonce())
  })

  test('if escaping were ever missed, injected markup still cannot run script', async ({ page }) => {
    const violations: string[] = []
    page.on('console', m => {
      if (/Content Security Policy/i.test(m.text())) violations.push(m.text())
    })
    await page.goto(BOARD)
    await page.waitForFunction(() => Boolean((document.querySelector('#__nuxt') as { __vue_app__?: unknown } | null)?.__vue_app__))

    // Simulate attacker-controlled applicant text rendered as raw HTML.
    await page.evaluate(() => {
      document.querySelector('[data-testid="applicant-name"]')!.innerHTML =
        'Ada<img src="x" onerror="window.__xssHandler=1"><a id="xss-link" href="javascript:window.__xssLink=1">x</a>'
    })
    await page.click('#xss-link')
    await page.waitForTimeout(300)

    expect(await page.evaluate(() => (window as { __xssHandler?: number }).__xssHandler)).toBeUndefined()
    expect(await page.evaluate(() => (window as { __xssLink?: number }).__xssLink)).toBeUndefined()
    expect(violations.length).toBeGreaterThanOrEqual(2)
  })

  test('the app still works under the CSP (no violations in normal use)', async ({ page }) => {
    const violations: string[] = []
    page.on('console', m => {
      if (/Content Security Policy/i.test(m.text())) violations.push(m.text())
    })
    await page.goto(BOARD)
    await page.waitForFunction(() => Boolean((document.querySelector('#__nuxt') as { __vue_app__?: unknown } | null)?.__vue_app__))
    const id = await page.locator('[data-testid="applicant-card"][data-status="pending"]').first().getAttribute('data-application-id')
    await page.locator(`[data-application-id="${id}"]`).getByTestId('shortlist-button').click()
    await expect(page.locator(`[data-application-id="${id}"]`)).toHaveAttribute('data-status', 'shortlisted')

    expect(violations).toEqual([])
  })
})
