import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Retrieve Intento routing
 *
 * @param {number} id Intento engine
 * @returns {Promise<object>}
 */
export const getIntentoRouting = async (id) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/intento/routing/${id}`,
    {
      method: 'GET',
      credentials: 'include',
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
