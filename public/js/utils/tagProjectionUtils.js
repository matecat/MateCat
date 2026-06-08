import UserStore from '../stores/UserStore'
import {
  removeTagsFromText,
  hasDataOriginalTags,
} from '../components/segments/utils/DraftMatecatUtils/tagUtils'

export const checkTPSupportedLanguage = () => {
  const languagesKey = `${config.source_code.split('-')[0]}-${config.target_code.split('-')[0]}`
  const languagesKeyRev = `${config.target_code.split('-')[0]}-${config.source_code.split('-')[0]}`
  return Object.keys(config.tag_projection_languages).some(
    (key) => key === languagesKey || key === languagesKeyRev,
  )
}

export const checkTPEnabled = () => {
  return (
    checkTPSupportedLanguage() &&
    UserStore.getUserMetadata()?.guess_tags === 1 &&
    !!!config.isReview
  )
}

export const checkCurrentSegmentTPEnabled = (segment) => {
  if (!segment) return false
  if (!checkTPEnabled()) return false
  const segmentNoTags = removeTagsFromText(segment.segment)
  const tagProjectionEnabled =
    hasDataOriginalTags(segment.segment) &&
    !segment.tagged &&
    segmentNoTags !== ''
  return tagProjectionEnabled && !segment.tagged
}
