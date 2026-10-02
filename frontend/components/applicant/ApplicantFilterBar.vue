<script setup lang="ts">
import type { ApplicationSort, ApplicationStatus } from '~/types/applicant'

/**
 * Filters (left) and sorting (far right) on one line. Presentational: it
 * shows the values it is given and emits changes; useApplicantFilters owns
 * the state and talks to the store.
 */
defineProps<{
  minScore: number
  dateRange: Date[] | null
  status: ApplicationStatus | null
  sort: ApplicationSort
  hasActiveFilter: boolean
}>()

const emit = defineEmits<{
  'update:minScore': [value: number | number[]]
  'update:dateRange': [value: Date | Date[] | (Date | null)[] | null | undefined]
  'update:status': [value: ApplicationStatus | null]
  'update:sort': [value: ApplicationSort]
  clear: []
}>()

const { t } = useI18n()

const STATUSES: ApplicationStatus[] = ['pending', 'shortlisted', 'rejected']
const SORTS: ApplicationSort[] = ['-aiScore', 'aiScore', '-appliedAt', 'appliedAt', 'name', '-name']

const statusOptions = computed(() => STATUSES.map(value => ({ value, label: t(`status.${value}`) })))
const sortOptions = computed(() => SORTS.map(value => ({ value, label: t(`review.sort.${value}`) })))
</script>

<template>
  <div
    class="flex flex-wrap items-center gap-x-5 gap-y-3 rounded-xl border border-slate-200 bg-white px-4 py-3"
    role="search"
    :aria-label="$t('review.filters.region')"
  >
    <span class="text-sm font-semibold text-slate-900">{{ $t('review.filters.heading') }}</span>

    <div class="flex items-center gap-2">
      <label id="min-score-label" for="min-score" class="whitespace-nowrap text-xs font-medium text-slate-600">{{ $t('review.filters.minScore') }}</label>
      <Slider
        input-id="min-score"
        :model-value="minScore"
        :min="0"
        :max="100"
        :step="5"
        class="ml-2 w-24"
        aria-labelledby="min-score-label"
        data-testid="min-score-slider"
        @update:model-value="value => emit('update:minScore', value)"
      />
      <output class="w-7 text-right text-xs font-semibold tabular-nums text-slate-900" data-testid="min-score-value" for="min-score">{{ minScore }}</output>
    </div>

    <div class="flex items-center gap-2">
      <label for="applied-range" class="sr-only">{{ $t('review.filters.appliedDate') }}</label>
      <DatePicker
        input-id="applied-range"
        :model-value="dateRange"
        selection-mode="range"
        :manual-input="false"
        date-format="d M yy"
        :placeholder="$t('review.filters.appliedDate')"
        show-icon
        icon-display="input"
        show-button-bar
        size="small"
        class="w-48"
        data-testid="date-filter"
        @update:model-value="value => emit('update:dateRange', value)"
      />
    </div>

    <div class="flex items-center gap-2">
      <label for="status-filter" class="sr-only">{{ $t('review.filters.status') }}</label>
      <Select
        label-id="status-filter"
        input-id="status-filter"
        :model-value="status"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        :placeholder="$t('review.filters.status')"
        show-clear
        size="small"
        class="w-36"
        data-testid="status-filter"
        @update:model-value="(value: ApplicationStatus | null) => emit('update:status', value ?? null)"
      />
    </div>

    <Button
      v-if="hasActiveFilter"
      :label="$t('review.filters.clear')"
      icon="pi pi-filter-slash"
      size="small"
      text
      severity="secondary"
      data-testid="clear-filters"
      @click="emit('clear')"
    />

    <div class="ml-auto flex items-center gap-2" data-testid="sort-control">
      <label for="sort-select" class="whitespace-nowrap text-xs font-medium text-slate-600">{{ $t('review.filters.sortBy') }}</label>
      <Select
        label-id="sort-select"
        input-id="sort-select"
        :model-value="sort"
        :options="sortOptions"
        option-label="label"
        option-value="value"
        size="small"
        class="w-44"
        data-testid="sort-select"
        @update:model-value="(value: ApplicationSort) => emit('update:sort', value)"
      />
    </div>
  </div>
</template>
