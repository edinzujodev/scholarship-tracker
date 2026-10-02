import tailwindcss from '@tailwindcss/vite'
import Aura from '@primeuix/themes/aura'

const isDev = process.env.NODE_ENV === 'development'

export default defineNuxtConfig({
  compatibilityDate: '2026-09-01',
  // Keep components/, types/, tests/ at the project root (matches the
  // submission layout) instead of Nuxt 4's default app/ directory.
  srcDir: '.',
  devtools: { enabled: false },
  modules: ['@pinia/nuxt', '@primevue/nuxt-module', '@nuxt/test-utils/module', 'nuxt-security', '@nuxtjs/i18n'],
  // components/{base,layout,campaign,applicant}/ group files by role; the
  // folder is not added to the component name (<BaseEmptyState>, not
  // <BaseBaseEmptyState>), so every file name is the tag you write.
  components: [{ path: '~/components', pathPrefix: false }],
  // Every user-facing label lives in i18n/locales/en.json (British English;
  // the locale code also drives date formatting).
  // tests/unit/i18nKeys.spec.ts fails on a missing key. Generated message types
  // (dev only) give editor autocompletion for keys.
  i18n: {
    defaultLocale: 'en-GB',
    locales: [{ code: 'en-GB', language: 'en-GB', file: 'en.json', name: 'English' }],
    strategy: 'no_prefix',
    detectBrowserLanguage: false,
    vueI18n: './i18n.config.ts',
    experimental: { typedOptionsAndMessages: 'default' },
  },
  css: ['primeicons/primeicons.css', '~/assets/css/main.css'],
  vite: { plugins: [tailwindcss()] },
  primevue: {
    options: {
      // Read from frontend/.env (gitignored) so the key is never committed.
      // PrimeVue 5 reads `license` at runtime, but @primevue/nuxt-module's
      // option types do not declare it yet, hence the typed spread.
      ...({ license: process.env.PRIMEUI_LICENSE_KEY } as Record<string, unknown>),
      theme: {
        preset: Aura,
        options: {
          darkModeSelector: '.app-dark',
          // Tailwind utilities must be able to override PrimeVue styles.
          cssLayer: { name: 'primevue', order: 'theme, base, primevue, components, utilities' },
        },
      },
    },
  },
  runtimeConfig: {
    // Server-only. The browser never sees the partner token: it talks to the
    // Nitro BFF under /api/portal, which proxies to Laravel (or the mock).
    backendUrl: '', // NUXT_BACKEND_URL, e.g. http://127.0.0.1:8000
    partnerToken: 'demo-partner-token', // demo-partner-token, or the line removed	partner-demo	Engineering Fellowship, Global Scholars, Spring Pilot other-partner-token	partner-other	Only Northbridge Bursary 2026 anything else, e.g. abc	nobody	"You don't have access"
    public: {
      demoCampaignId: '9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01', // NUXT_PUBLIC_DEMO_CAMPAIGN_ID
    },
  },
  typescript: { strict: true },
  /*
   * Browser hardening (TASK_REPORT.md, "Additions and decisions beyond the brief").
   * The portal renders applicant-supplied text; the CSP is the safety net if
   * escaping is ever missed: no script runs unless it carries this request's
   * nonce, and nothing may frame the portal.
   */
  security: {
    nonce: true,
    sri: true,
    headers: {
      contentSecurityPolicy: {
        'default-src': ["'none'"],
        // Nonce + strict-dynamic only. Deliberately no 'unsafe-inline' and no
        // https: fallback: inline or injected scripts without the nonce never run.
        'script-src': ["'self'", "'strict-dynamic'", "'nonce-{{nonce}}'"],
        'script-src-attr': ["'none'"],
        // PrimeVue injects theme <style> at runtime and SSR emits style=""
        // attributes, which nonces cannot cover. Styles cannot execute code.
        'style-src': ["'self'", "'unsafe-inline'"],
        'img-src': ["'self'", 'data:'],
        'font-src': ["'self'", 'data:'],
        // Same origin only: the browser talks to /api/portal, never to Laravel.
        // ws: for Vite hot reload in development.
        'connect-src': isDev ? ["'self'", 'ws:'] : ["'self'"],
        'object-src': ["'none'"],
        'base-uri': ["'none'"],
        'form-action': ["'self'"],
        'frame-ancestors': ["'none'"],
        'frame-src': ["'none'"],
        'worker-src': ["'self'"],
        'manifest-src': ["'self'"],
        // Would break plain-HTTP localhost; production is HTTPS only.
        'upgrade-insecure-requests': !isDev,
      },
      strictTransportSecurity: { maxAge: 63072000, includeSubdomains: true, preload: true },
      xContentTypeOptions: 'nosniff',
      xFrameOptions: 'DENY',
      referrerPolicy: 'strict-origin-when-cross-origin',
      permissionsPolicy: {
        camera: [],
        microphone: [],
        geolocation: [],
        payment: [],
        usb: [],
        'display-capture': [],
        fullscreen: [],
      },
      crossOriginOpenerPolicy: 'same-origin',
      crossOriginResourcePolicy: 'same-origin',
    },
    // Already handled, stricter, by the portal's own code: per-endpoint
    // validation and the /api/portal/logs rate limit. A generic XSS filter
    // would also reject legitimate text such as "<" in error reports.
    rateLimiter: false,
    xssValidator: false,
  },
})
