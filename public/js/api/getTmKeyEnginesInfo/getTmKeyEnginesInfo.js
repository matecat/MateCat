import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get tm key engines info
 *
 * @param {number} key
 * @returns {Promise<object>}
 */
export const getTmKeyEnginesInfo = async (key) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/tm-keys/engines/info/${key}`,
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
