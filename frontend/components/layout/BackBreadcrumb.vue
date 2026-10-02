<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'

/**
 * Breadcrumb navigation back to a parent page, shown above page content.
 * Defaults to the campaign list, the parent of every campaign page.
 */
withDefaults(
  defineProps<{
    to?: RouteLocationRaw
    /** Defaults to the translated "All campaigns". */
    label?: string
  }>(),
  { to: '/', label: undefined },
)
</script>

<template>
  <nav :aria-label="$t('nav.breadcrumb')">
    <NuxtLink
      :to="to"
      class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500"
      data-testid="back-to-campaigns"
    >
      <i class="pi pi-arrow-left text-xs" aria-hidden="true" /> {{ label ?? $t('nav.allCampaigns') }}
    </NuxtLink>
  </nav>
</template>
