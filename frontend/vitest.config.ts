import { defineVitestConfig } from '@nuxt/test-utils/config'

export default defineVitestConfig({
  test: {
    environment: 'nuxt',
    include: ['tests/unit/**/*.spec.ts'],
    environmentOptions: {
      nuxt: { domEnvironment: 'happy-dom' },
    },
  },
})
