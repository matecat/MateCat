import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Edit segment issue
 *
 * @param {Object} options
 * @param {string} options.idSegment
 * @param {string} options.issueId
 * @param {Object} issueDetails
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.currentPassword]
 * @returns {Promise<object>}
 */
export const editSegmentIssue = async ({
  idSegment,
  issueId,
  issueDetails,
  idJob = config.id_job,
  password = config.currentPassword,
}) => {
  // No revision_number: the phase is derived server side from this password.
  const dataParams = {
    ...issueDetails,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })

  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${password}/segments/${idSegment}/translation-issues/${issueId}`,
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
