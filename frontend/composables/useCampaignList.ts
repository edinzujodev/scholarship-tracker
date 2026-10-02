import type { CampaignListDocument, CampaignResource } from '~/types/applicant'
import { describeError } from '~/stores/applicantReview'

export const CAMPAIGN_PAGE_SIZE_OPTIONS = [6, 12, 24]

/**
 * The partner's campaigns, one API page at a time.
 *
 * GET /api/portal/campaigns → Laravel GET /api/v1/campaigns (only the
 * partner's own campaigns). Paging is done by the API (JSON:API
 * page[size]/page[number]); the table runs in lazy mode and only renders the
 * page it is given. Await the result in <script setup> so SSR renders data.
 */
export function useCampaignList() {
  const { t } = useI18n()
  const pageNumber = ref(1)
  const pageSize = ref(CAMPAIGN_PAGE_SIZE_OPTIONS[0]!)

  // The query is reactive, so a page change refetches. One cache entry per
  // page, so pages never overwrite each other's data.
  const request = useFetch<CampaignListDocument>('/api/portal/campaigns', {
    key: computed(() => `campaign-list:${pageSize.value}:${pageNumber.value}`),
    query: computed(() => ({ 'page[size]': pageSize.value, 'page[number]': pageNumber.value })),
  })
  const { data, status, error, refresh } = request

  // A new page has its own (empty) cache entry until it arrives; keep showing
  // the last page, dimmed, instead of flashing skeletons on every page change.
  // Current data always wins: watchers do not run during SSR, so `previous`
  // alone would render an empty table on the server.
  const previous = shallowRef(data.value)
  watch(data, next => {
    if (next) previous.value = next
  })
  const shown = computed(() => data.value ?? previous.value)

  const campaigns = computed(() => shown.value?.data ?? [])
  const total = computed(() => shown.value?.meta.page.total ?? 0)
  const loading = computed(() => status.value === 'pending' && !shown.value)
  const refreshing = computed(() => status.value === 'pending' && !!shown.value)
  const errorMessage = computed(() => (error.value ? describeError(error.value, t('campaigns.loadError')) : null))

  const paging = useLazyPaging({
    pageNumber,
    pageSize,
    goToPage: page => (pageNumber.value = page),
    setPageSize: size => {
      pageSize.value = size
      pageNumber.value = 1
    },
  })

  const campaignPath = (campaign: CampaignResource) => `/campaigns/${campaign.id}`
  const openCampaign = (campaign: CampaignResource) => navigateTo(campaignPath(campaign))

  // Everything reactive is set up before the first await, so it stays bound
  // to the calling component and is cleaned up with it.
  return request.then(() => ({
    campaigns,
    total,
    pageSize,
    pageSizeOptions: CAMPAIGN_PAGE_SIZE_OPTIONS,
    loading,
    refreshing,
    errorMessage,
    refresh,
    paging,
    campaignPath,
    openCampaign,
  }))
}
