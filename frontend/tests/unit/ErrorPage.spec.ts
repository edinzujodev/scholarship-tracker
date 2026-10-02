import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ErrorPage from '~/error.vue'

const mountWith = (statusCode: number, data?: unknown) =>
  mountSuspended(ErrorPage, { props: { error: { statusCode, statusMessage: '', message: '', data } as never } })

describe('error.vue ("Page not accessible")', () => {
  it('shows the support reference when the failed check returned one', async () => {
    const wrapper = await mountWith(404, { reason: 'not-found', requestId: 'portal-7f3c2a9e' })

    expect(wrapper.get('[data-testid="error-reference"]').text()).toBe('portal-7f3c2a9e')
  })

  it.each([
    [400, { reason: 'invalid-id' }, 'invalid-id', 'This link is not valid'],
    [401, { reason: 'unauthorized' }, 'unauthorized', "You don't have access"],
    [403, undefined, 'unauthorized', "You don't have access"],
    [404, JSON.stringify({ reason: 'not-found' }), 'not-found', 'Campaign not found'],
    [503, { reason: 'unavailable' }, 'unavailable', 'Campaign service unavailable'],
    [500, undefined, 'other', 'Something went wrong'],
  ])('status %i renders the dedicated page', async (status, data, reason, title) => {
    const wrapper = await mountWith(status, data)
    const panel = wrapper.get('[data-testid="page-not-accessible"]')

    expect(panel.attributes('role')).toBe('alert')
    expect(panel.attributes('data-status')).toBe(String(status))
    expect(panel.attributes('data-reason')).toBe(reason)
    expect(wrapper.text()).toContain('Page not accessible')
    expect(wrapper.get('[data-testid="not-accessible-title"]').text()).toBe(title)
    expect(wrapper.text()).toContain(`Error ${status}`)
  })
})
