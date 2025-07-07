import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Submit comment regarding specific segment
 *
 * @param {Object} options
 * @param {number} options.idSegment
 * @param {string} options.username
 * @param {string} options.sourcePage
 * @param {string} options.message
 * @param {string} [options.idClient=config.id_client]
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {string} [options.revisionNumber=config.revisionNumber]
 * @returns {Promise<object>}
 */
export const submitComment = async ({
  idSegment,
  username,
  sourcePage,
  message,
  idJob = config.id_job,
  password = config.password,
  revisionNumber = config.revisionNumber,
  isAnonymous = false,
}) => {
  const dataParams = {
    id_job: idJob,
    id_segment: idSegment,
    revision_number: revisionNumber,
    username,
    password,
    source_page: sourcePage,
    message,
    is_anonymous: isAnonymous,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/comment/create`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
