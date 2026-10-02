<script setup lang="ts">
/** A failed load: the message, the support reference (request id) if any, and a retry button. */
defineProps<{
  message: string
  reference?: string | null
}>()

defineEmits<{ retry: [] }>()
</script>

<template>
  <Message severity="error">
    <div class="flex items-center gap-3">
      <span>{{ message }}</span>
      <span v-if="reference" class="text-xs opacity-80" data-testid="load-error-reference">
        {{ $t('common.reference', { reference }) }}
      </span>
      <Button :label="$t('common.retry')" size="small" text @click="$emit('retry')" />
    </div>
  </Message>
</template>
