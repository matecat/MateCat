import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Send type of issue of segment
 *
 * @param {string} idSegment
 * @param {Object} issueDetails
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.currentPassword]
 * @returns {Promise<object>}
 */
export const sendSegmentVersionIssue = async ({
  idSegment,
  issueDetails,
  idJob = config.id_job,
  password = config.currentPassword,
}) => {
  // No revision_number: the phase the issue belongs to is derived server side from this password.
  const dataParams = {
    ...issueDetails,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${password}/segments/${idSegment}/translation-issues`,
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
  if (errors && errors.length > 0) return Promise.reject({errors})

  return data
}
