import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get segments comments
 *
 * @param {Object} options
 * @param {boolean} options.firstSegment
 * @param {boolean} options.lastSegment
 * @param {string} [options.idJob=config.id_job]
 * @returns {Promise<object>}
 */
export const getComments = async ({
  firstSegment,
  lastSegment,
  idJob = config.id_job,
  password = config.password,
}) => {
  const dataParams = {
    action: 'comment',
    _sub: 'getRange',
    first_seg: firstSegment,
    last_seg: lastSegment,
    id_job: idJob,
    password,
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
