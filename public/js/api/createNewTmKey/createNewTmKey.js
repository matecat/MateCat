import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Create new one TM key
 *
 * @param {Object} options
 * @param {string} options.key
 * @param {string} options.description
 * @returns {Promise<object>}
 */
export const createNewTmKey = async ({key, description}) => {
  const paramsData = {
    key,
    description,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/user-keys-new-key`,
    {
      method: 'POST',
      body: formData,
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
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
