import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Create project api
 *
 * @param {Object} options
 * @returns {Promise<object>}
 */
export const createProject = async (options) => {
  const paramsData = {
    ...options,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`${config.basepath}?action=createProject`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
