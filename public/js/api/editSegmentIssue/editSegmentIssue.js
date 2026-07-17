import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Edit segment issue
 *
 * @param {Object} options
 * @param {string} options.idSegment
 * @param {string} options.issueId
 * @param {Object} issueDetails
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.reviewPassword=config.review_password]
 * @param {number} [revisionNumber=config.revisionNumber]
 * @returns {Promise<object>}
 */
export const editSegmentIssue = async ({
  idSegment,
  issueId,
  issueDetails,
  idJob = config.id_job,
  reviewPassword = config.review_password,
  revisionNumber = config.revisionNumber,
}) => {
  const dataParams = {
    ...issueDetails,
    revision_number: revisionNumber,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })

  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${reviewPassword}/segments/${idSegment}/translation-issues/${issueId}`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )
  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({errors: data.errors ?? data})
    } else {
      return Promise.reject()
    }
  }

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject({response, errors})

  return data
}
