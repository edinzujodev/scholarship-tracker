import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BackBreadcrumb from '~/components/layout/BackBreadcrumb.vue'

describe('BackBreadcrumb', () => {
  it('links back to the campaign list by default, inside a labelled breadcrumb nav', async () => {
    const wrapper = await mountSuspended(BackBreadcrumb)

    expect(wrapper.get('nav').attributes('aria-label')).toBe('Breadcrumb')
    const link = wrapper.get('[data-testid="back-to-campaigns"]')
    expect(link.attributes('href')).toBe('/')
    expect(link.text()).toBe('All campaigns')
    expect(link.get('i').attributes('aria-hidden')).toBe('true')
  })

  it('accepts another target and label', async () => {
    const wrapper = await mountSuspended(BackBreadcrumb, { props: { to: '/reports', label: 'All reports' } })

    const link = wrapper.get('[data-testid="back-to-campaigns"]')
    expect(link.attributes('href')).toBe('/reports')
    expect(link.text()).toBe('All reports')
  })
})
