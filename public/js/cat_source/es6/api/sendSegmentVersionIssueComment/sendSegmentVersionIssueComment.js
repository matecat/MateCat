import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Send a comment for a specific issue
 *
 * @param {string} idSegment
 * @param {string} idIssue
 * @param {Object} paramsToSend
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */
export const sendSegmentVersionIssueComment = async (
  idSegment,
  idIssue,
  paramsToSend,
  idJob = config.id_job,
  password = config.password,
) => {
  const dataParams = {...paramsToSend}
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${password}/segments/${idSegment}/translation-issues/${idIssue}/comments`,
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
