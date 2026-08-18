import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Delete segment issue
 *
 * @param {Object} options
 * @param {string} options.idSegment
 * @param {string} options.idIssue
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.currentPassword]
 * @returns {Promise<object>}
 */
export const deleteSegmentIssue = async ({
  idSegment,
  idIssue,
  idJob = config.id_job,
  password = config.currentPassword,
}) => {
  // No revision_number: the phase is derived server side from this password.
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${password}/segments/${idSegment}/translation-issues/${idIssue}`,
    {
      method: 'DELETE',
      credentials: 'include',
    },
  )
  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({errors: data.errors ?? data})
    } else {
      return Promise.reject()
    }
  } else {
    return true
  }
}
