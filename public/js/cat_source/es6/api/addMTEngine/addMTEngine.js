import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Create new one MT engine
 *
 * @param {Object} options
 * @param {string} options.name
 * @param {string} options.provider
 * @param {string} options.dataMt
 * @returns {Promise<object>}
 */
export const addMTEngine = async ({name, provider, dataMt}) => {
  const paramsData = {
    action: 'engine',
    exec: 'add',
    name,
    provider,
    data: dataMt,
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
