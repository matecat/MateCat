import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Submit comment regarding specific segment
 *
 * @param {Object} options
 * @param {number} options.idSegment
 * @param {string} options.username
 * @param {string} options.message
 * @param {string} [options.idClient=config.id_client]
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.currentPassword]
 * @returns {Promise<object>}
 */
export const submitComment = async ({
  idSegment,
  username,
  message,
  idJob = config.id_job,
  password = config.currentPassword,
  isAnonymous = false,
}) => {
  // No source_page and no revision_number: the server attributes the comment to the phase this
  // password resolves to, so the current password of that phase has to be sent.
  const dataParams = {
    id_job: idJob,
    id_segment: idSegment,
    username,
    password,
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
