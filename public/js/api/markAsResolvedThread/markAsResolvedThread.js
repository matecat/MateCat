import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Mark as resolved a segment thread
 *
 * @param {Object} options
 * @param {number} options.idSegment
 * @param {string} options.username
 * @param {string} options.isAnonymous
 * @param {string} [options.idClient=config.id_client]
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.currentPassword]
 * @returns {Promise<object>}
 */
export const markAsResolvedThread = async ({
  idSegment,
  username,
  isAnonymous,
  idJob = config.id_job,
  password = config.currentPassword,
}) => {
  // No source_page: the thread is resolved in the phase this password resolves to.
  const dataParams = {
    id_job: idJob,
    id_segment: idSegment,
    username,
    password,
    is_anonymous: isAnonymous,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/comment/resolve`,
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
