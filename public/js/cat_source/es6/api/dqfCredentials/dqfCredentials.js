import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import {flattenObject} from '../../utils/queryString'

/**
 * Submit DQF credentials login
 *
 * @param {string} username
 * @param {string} password
 * @returns {Promise<object>}
 */
export const submitDqfCredentials = async (username, password) => {
  const paramsData = flattenObject({
    metadata: {
      dqf_username: username,
      dqf_password: password,
    },
  })
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`/api/app/user/metadata`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}

/**
 * Clear DQF credentials logout
 *
 * @returns {Promise<object>}
 */
export const clearDqfCredentials = async () => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/dqf/user/metadata`,
    {
      method: 'DELETE',
      credentials: 'include',
    },
  )

  return response
}
