import { storeToRefs } from 'pinia'
import { useSocialStore } from '@/store/social'

export function usePrivacySettings() {
  const socialStore = useSocialStore()
  const { privacySettings } = storeToRefs(socialStore)
  const { fetchPrivacySettings, updatePrivacySettings } = socialStore

  return {
    privacySettings,
    fetchPrivacySettings,
    updatePrivacySettings
  }
}
