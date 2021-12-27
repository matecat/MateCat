import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Send type of issue of segment
 *
 * @param {string} idSegment
 * @param {Object} issueDetails
 * @param {string} [idJob=config.id_job]
 * @param {string} [reviewPassword=config.review_password]
 * @param {number} [revisionNumber=config.revisionNumber]
 * @returns {Promise<object>}
 */
export const sendSegmentVersionIssue = async (
  idSegment,
  issueDetails,
  idJob = config.id_job,
  reviewPassword = config.review_password,
  revisionNumber = config.revisionNumber,
) => {
  const dataParams = {
    ...issueDetails,
    revision_number: revisionNumber,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${reviewPassword}/segments/${idSegment}/translation-issues`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({response, errors: data.errors ?? data})
    } else {
      return Promise.reject({response})
    }
  }

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject({response, errors})

  return data
}
