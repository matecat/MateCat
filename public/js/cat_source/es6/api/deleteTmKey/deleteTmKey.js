import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Delete TM key
 *
 * @param {Object} options
 * @param {string} options.key
 * @returns {Promise<object>}
 */
export const deleteTmKey = async ({key, removeFrom}) => {
  const paramsData = {
    action: 'userKeys',
    exec: 'delete',
    key,
    remove_from: removeFrom,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}?action=${paramsData.action}`,
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
