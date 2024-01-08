import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Retrieve all supported languages
 *
 * @returns {Promise<object>}
 */
export const getSupportedLanguages = async () => {
  const response = await fetch(`${getMatecatApiDomain()}api/app/languages`, {
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

  return await response.json()
}
