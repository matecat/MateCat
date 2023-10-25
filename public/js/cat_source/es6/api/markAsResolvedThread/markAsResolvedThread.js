import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Mark as resolved a segment thread
 *
 * @param {Object} options
 * @param {number} options.idSegment
 * @param {string} options.username
 * @param {string} options.sourcePage
 * @param {string} [options.idClient=config.id_client]
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @returns {Promise<object>}
 */
export const markAsResolvedThread = async ({
  idSegment,
  username,
  sourcePage,
  idJob = config.id_job,
  password = config.password,
}) => {
  const dataParams = {
    action: 'comment',
    _sub: 'resolve',
    id_job: idJob,
    id_segment: idSegment,
    username,
    password,
    source_page: sourcePage,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}?action=comment`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
