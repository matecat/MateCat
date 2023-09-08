import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Retrieve MT engines list
 *
 * @returns {Promise<object>}
 */
export const getMTEngines = async () => {
  const response = await fetch(`${getMatecatApiDomain()}api/v2/engines/list`, {
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  const data = await response.json()
  //if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
