import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get tm keys logged user
 *
 * @returns {Promise<object>}
 */
export const getTmKeysUser = async () => {
  const response = await fetch(`${getMatecatApiDomain()}api/v3/tm-keys/list`, {
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  const responseData = await response.json()
  if (!Array.isArray(responseData) && responseData.errors)
    return Promise.reject(responseData.errors)

  return responseData
}
