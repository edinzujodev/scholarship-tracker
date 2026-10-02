import { defineConfig, devices } from '@playwright/test'

const PORT = 3200

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list']],
  use: {
    baseURL: `http://localhost:${PORT}`,
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    // Production build served by Nitro, using the in-repo mock API (no PHP needed).
    command: 'npx nuxt build && node .output/server/index.mjs',
    env: { PORT: String(PORT), NUXT_BACKEND_URL: '' },
    url: `http://localhost:${PORT}`,
    // A cold production build can take several minutes on a busy machine.
    timeout: 480_000,
    reuseExistingServer: !process.env.CI,
  },
})
