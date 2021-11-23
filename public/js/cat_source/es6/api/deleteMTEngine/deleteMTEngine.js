import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Delete MT engine
 *
 * @param {Object} options
 * @param {string} options.id
 * @returns {Promise<object>}
 */
export const deleteMTEngine = async ({id}) => {
  const paramsData = {
    action: 'engine',
    exec: 'delete',
    id,
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
