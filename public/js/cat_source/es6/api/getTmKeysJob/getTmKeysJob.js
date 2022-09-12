import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get tm keys specific job
 *
 * @param {Object} options
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @returns {Promise<object>}
 */
export const getTmKeysJob = async ({
  idJob = config.id_job,
  password = config.password,
} = {}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/tm-keys/${idJob}/${password}`,
    {
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const responseData = await response.json()
  if (!Array.isArray(responseData) && responseData.errors)
    return Promise.reject(responseData.errors)

  return responseData
}
