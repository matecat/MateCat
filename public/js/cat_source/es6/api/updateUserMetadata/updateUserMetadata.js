import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Modify user metadata property
 *
 * @param {string} key
 * @param {any} value
 * @returns {Promise<object>}
 */
export const updateUserMetadata = async (key, value) => {
  const response = await fetch(`${getMatecatApiDomain()}api/v2/user/metadata`, {
    method: 'PUT',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({key, value}),
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
