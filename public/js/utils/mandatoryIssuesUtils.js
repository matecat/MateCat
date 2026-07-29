import CatToolStore from '../stores/CatToolStore'

/**
 * Whether the current revision round requires an issue before a segment can be approved.
 *
 * A job with no `mandatory_issues` entry predates the setting, so every round requires an
 * issue. Job metadata that failed to load is treated the same way, so a network error can
 * never silently drop the requirement.
 *
 * @returns {boolean}
 */
export const isIssueMandatoryForCurrentRevision = () => {
  const mandatoryIssues = CatToolStore.getJobMetadata()?.job?.mandatory_issues

  if (!Array.isArray(mandatoryIssues)) return true

  const currentRevisionKey = `r${config.revisionNumber}`

  return mandatoryIssues.some(
    (value) => typeof value === 'string' && value === currentRevisionKey,
  )
}
