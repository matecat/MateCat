import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Update TM key
 *
 * @param {Object} options
 * @param {string} options.key
 * @param {string} options.description
 * @returns {Promise<object>}
 */
export const updateTmKey = async ({key, description, penalty}) => {
  const paramsData = {
    key,
    description,
    penalty,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/user-keys-update`,
    {
      method: 'POST',
      body: formData,
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
