<script setup lang="ts">
import type { CampaignResource } from '~/types/applicant'

const { campaigns, total, pageSize, pageSizeOptions, loading, refreshing, errorMessage, refresh, paging, campaignPath, openCampaign } =
  await useCampaignList()
const { formatDay, formatAverage } = useFormatters()

function onRowClick(event: { originalEvent: Event; data: CampaignResource }) {
  // Clicks on the link itself are handled by the link.
  if ((event.originalEvent.target as HTMLElement | null)?.closest('a')) return
  openCampaign(event.data)
}
</script>

<template>
  <section class="flex flex-col gap-6" aria-labelledby="campaigns-title">
    <header>
      <h1 id="campaigns-title" class="text-2xl font-semibold text-slate-900">{{ $t('campaigns.title') }}</h1>
      <p class="mt-1 text-sm text-slate-600">{{ $t('campaigns.subtitle') }}</p>
    </header>

    <BaseLoadError v-if="errorMessage" :message="errorMessage" data-testid="campaigns-error" @retry="refresh()" />

    <div
      v-else-if="loading"
      class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5"
      role="status"
      :aria-label="$t('campaigns.loading')"
    >
      <Skeleton height="1.5rem" />
      <Skeleton v-for="n in 3" :key="n" height="2.5rem" />
    </div>

    <DataTable
      v-else
      :value="campaigns"
      data-key="id"
      lazy
      paginator
      :always-show-paginator="false"
      :first="paging.firstRow.value"
      :rows="pageSize"
      :total-records="total"
      :rows-per-page-options="pageSizeOptions"
      :paginator-template="paging.template"
      :current-page-report-template="paging.reportTemplate.value"
      row-hover
      table-class="min-w-[48rem]"
      :row-class="() => 'group cursor-pointer'"
      :pt="{ bodyRow: { 'data-testid': 'campaign-row' }, tableContainer: { class: 'overflow-x-auto' } }"
      :class="['overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-opacity', { 'opacity-60': refreshing }]"
      :aria-busy="refreshing"
      aria-labelledby="campaigns-title"
      data-testid="campaign-table"
      @page="paging.onPage"
      @row-click="onRowClick"
    >
      <template #empty>
        <p class="py-10 text-center text-sm text-slate-600" data-testid="campaigns-empty">{{ $t('campaigns.empty') }}</p>
      </template>

      <Column :header="$t('campaigns.columns.campaign')">
        <template #body="{ data: campaign }">
          <!-- The link keeps keyboard and screen-reader navigation; the row click is a mouse shortcut. -->
          <NuxtLink
            :to="campaignPath(campaign)"
            class="font-semibold text-slate-900 hover:text-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500"
            data-testid="campaign-link"
            :data-campaign-id="campaign.id"
          >
            {{ campaign.attributes.name }}
          </NuxtLink>
          <div class="text-xs text-slate-500">{{ $t('campaigns.created', { date: formatDay(campaign.attributes.createdAt) }) }}</div>
        </template>
      </Column>

      <Column :header="$t('campaigns.columns.applicants')" header-class="[&>div]:justify-end" body-class="text-right tabular-nums">
        <template #body="{ data: campaign }">
          <span data-testid="campaign-applicants">{{ campaign.attributes.applicantCount }}</span>
        </template>
      </Column>

      <Column :header="$t('campaigns.columns.eligible')" header-class="[&>div]:justify-end" body-class="text-right tabular-nums">
        <template #body="{ data: campaign }">
          <span data-testid="campaign-eligible">
            {{ campaign.attributes.eligibleCount }}
            <span v-if="campaign.attributes.applicantCount" class="text-xs text-slate-500">({{ campaign.attributes.eligibleRate }}%)</span>
          </span>
        </template>
      </Column>

      <Column :header="$t('campaigns.columns.averageScore')" header-class="[&>div]:justify-end" body-class="text-right tabular-nums">
        <template #body="{ data: campaign }">
          {{ formatAverage(campaign.attributes.averageAiScore, campaign.attributes.applicantCount > 0) }}
        </template>
      </Column>

      <Column :header="$t('campaigns.columns.progress')">
        <template #body="{ data: campaign }">
          <CampaignProgressTags
            :pending="campaign.attributes.pendingCount"
            :shortlisted="campaign.attributes.shortlistedCount"
            :rejected="campaign.attributes.rejectedCount"
          />
        </template>
      </Column>

      <!-- Visual cue that the row opens the campaign; the name link is the accessible target. -->
      <Column body-class="w-10 text-right" header-class="w-10">
        <template #body>
          <i class="pi pi-chevron-right text-sm text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-emerald-600" aria-hidden="true" data-testid="campaign-row-chevron" />
        </template>
      </Column>
    </DataTable>
  </section>
</template>
