<script setup lang="ts">
/**
 * PrimeVue Paginator for server-side (JSON:API) paging. Speaks in 1-based
 * page numbers like the API; the 0-based row maths stays inside.
 */
const props = defineProps<{
  pageNumber: number
  pageSize: number
  total: number
  pageSizeOptions: number[]
}>()

const emit = defineEmits<{
  goToPage: [pageNumber: number]
  setPageSize: [pageSize: number]
}>()

const { firstRow, onPage, template, reportTemplate } = useLazyPaging({
  pageNumber: () => props.pageNumber,
  pageSize: () => props.pageSize,
  goToPage: page => emit('goToPage', page),
  setPageSize: size => emit('setPageSize', size),
})
</script>

<template>
  <Paginator
    :first="firstRow"
    :rows="pageSize"
    :total-records="total"
    :rows-per-page-options="pageSizeOptions"
    :template="template"
    :current-page-report-template="reportTemplate"
    @page="onPage"
  />
</template>
