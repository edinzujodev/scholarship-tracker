import { storeToRefs } from 'pinia'
import { useToast } from 'primevue/usetoast'

/**
 * Shows a toast whenever an optimistic status change fails and is rolled back.
 * The store records the failure; this turns it into a translated message.
 */
export function useStatusErrorToast(group: string) {
  const { t } = useI18n()
  const toast = useToast()
  const { actionError } = storeToRefs(useApplicantReviewStore())

  watch(
    () => actionError.value?.seq,
    () => {
      const error = actionError.value
      if (!error) return
      const detail = t('review.statusError.detail', {
        action: t(`review.statusError.action.${error.attemptedStatus}`),
        name: error.applicantName,
        message: error.message,
      })
      toast.add({
        group,
        severity: 'error',
        summary: t('review.statusError.summary'),
        detail: error.requestId ? `${detail} ${t('common.reference', { reference: error.requestId })}` : detail,
        life: 6000,
      })
    },
  )
}
