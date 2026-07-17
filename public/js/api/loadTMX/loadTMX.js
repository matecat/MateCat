import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Import TMX file
 *
 * @param {Object} options
 * @param {string} options.key
 * @param {string} options.name
 * @returns {Promise<object>}
 */
export const loadTMX = async ({key, name, uuid}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/mymemory/tmx/import/status/${uuid}`,
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
