<script setup lang="ts">
import type { ApplicationStatus } from '~/types/applicant'

const props = defineProps<{ status: ApplicationStatus }>()

const SEVERITY: Record<ApplicationStatus, 'secondary' | 'success' | 'danger'> = {
  pending: 'secondary',
  shortlisted: 'success',
  rejected: 'danger',
}
const severity = computed(() => SEVERITY[props.status])
</script>

<template>
  <Tag :value="$t(`status.${status}`)" :severity="severity" rounded />
</template>
