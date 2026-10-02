<script setup lang="ts">
/** One labelled figure in a summary row. Use inside a <dl>. */
defineProps<{ label: string }>()
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-4">
    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ label }}</dt>
    <dd class="mt-1 text-2xl font-semibold tabular-nums">
      <slot />
    </dd>
  </div>
</template>
