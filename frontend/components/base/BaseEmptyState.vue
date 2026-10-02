<script setup lang="ts">
/** Dashed placeholder for a list with nothing to show. */
defineProps<{
  title?: string
  /** PrimeIcons class, e.g. "pi-filter-slash". */
  icon?: string
}>()
</script>

<template>
  <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
    <i v-if="icon" class="pi text-3xl text-slate-400" :class="icon" aria-hidden="true" />
    <h3 v-if="title" class="text-lg font-semibold text-slate-900">{{ title }}</h3>
    <p class="max-w-md text-sm text-slate-600">
      <slot />
    </p>
    <slot name="actions" />
  </div>
</template>
