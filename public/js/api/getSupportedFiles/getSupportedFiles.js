import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Retrieve all supported files
 *
 * @returns {Promise<object>}
 */
export const getSupportedFiles = async () => {
  const response = await fetch(`${getMatecatApiDomain()}api/app/files`, {
    method: 'GET',
    credentials: 'include',
  })

  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({errors: data.errors ?? data})
    } else {
      return Promise.reject()
    }
  }

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject({errors})

  return data
}
