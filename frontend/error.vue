<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()

type Reason = 'invalid-id' | 'unauthorized' | 'not-found' | 'unavailable' | 'other'

// `data` may arrive as an object (client) or a JSON string (SSR payload).
const data = computed(() => {
  const raw = props.error.data
  const parsed = typeof raw === 'string' ? (() => { try { return JSON.parse(raw) } catch { return {} } })() : raw
  return (parsed ?? {}) as { reason?: string; requestId?: string }
})
const reference = computed(() => data.value.requestId)

const reason = computed<Reason>(() => {
  const value = data.value.reason
  if (value === 'invalid-id' || value === 'unauthorized' || value === 'not-found' || value === 'unavailable') return value
  if (props.error.statusCode === 401 || props.error.statusCode === 403) return 'unauthorized'
  if (props.error.statusCode === 404) return 'not-found'
  return 'other'
})

// Title and body per reason come from the locale (errorPage.reason.<reason>).
const ICONS: Record<Reason, string> = {
  'invalid-id': 'pi-link',
  unauthorized: 'pi-lock',
  'not-found': 'pi-search',
  unavailable: 'pi-cloud',
  other: 'pi-exclamation-triangle',
}

const { t } = useI18n()
const copy = computed(() => ({
  title: t(`errorPage.reason.${reason.value}.title`),
  body: t(`errorPage.reason.${reason.value}.body`),
  icon: ICONS[reason.value],
}))

usePageTitle(() => t('errorPage.pageTitle'))

function backToCampaigns() {
  clearError({ redirect: '/' })
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-16 font-sans text-slate-900">
    <section
      class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm"
      role="alert"
      data-testid="page-not-accessible"
      :data-status="error.statusCode"
      :data-reason="reason"
    >
      <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
        <i class="pi text-xl text-slate-500" :class="copy.icon" aria-hidden="true" />
      </div>
      <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $t('errorPage.eyebrow') }}</p>
      <h1 class="mt-2 text-2xl font-semibold" data-testid="not-accessible-title">{{ copy.title }}</h1>
      <p class="mt-3 text-sm text-slate-600">{{ copy.body }}</p>
      <p class="mt-4 text-xs text-slate-400">
        {{ $t('errorPage.code', { status: error.statusCode }) }}<template v-if="reference">
          ·
          <i18n-t keypath="common.reference" tag="span" scope="global">
            <template #reference><span data-testid="error-reference">{{ reference }}</span></template>
          </i18n-t>
        </template>
      </p>
      <button
        type="button"
        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
        data-testid="back-to-campaigns"
        @click="backToCampaigns"
      >
        <i class="pi pi-arrow-left text-xs" aria-hidden="true" /> {{ $t('errorPage.back') }}
      </button>
    </section>
  </main>
</template>
