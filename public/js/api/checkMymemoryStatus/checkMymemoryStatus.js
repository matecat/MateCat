import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Upload glossary files
 *
 * @param {Object} options
 * @param {string} options.uuid
 * @returns {Promise<object>}
 */
export const checkMymemoryStatus = async ({uuid}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/mymemory/status/${uuid}`,
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
