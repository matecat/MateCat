import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Delete segment issue
 *
 * @param {Object} options
 * @param {string} options.idSegment
 * @param {string} options.idIssue
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.reviewPassword=config.review_password]
 * @returns {Promise<object>}
 */
export const deleteSegmentIssue = async ({
  idSegment,
  idIssue,
  idJob = config.id_job,
  reviewPassword = config.review_password,
}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${reviewPassword}/segments/${idSegment}/translation-issues/${idIssue}`,
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
