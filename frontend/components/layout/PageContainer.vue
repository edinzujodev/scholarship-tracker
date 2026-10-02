<script setup lang="ts">
/** Page frame shared by every portal page: background, gutters and the centred content column. */
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 font-sans text-slate-900">
    <div class="mx-auto flex max-w-6xl flex-col gap-4">
      <slot />
    </div>
  </main>
</template>
