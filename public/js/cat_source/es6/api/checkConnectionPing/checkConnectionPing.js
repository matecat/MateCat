import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Check network connection status
 *
 * @returns {Promise<object>}
 */
export const checkConnectionPing = async () => {
  const dataParams = {
    action: 'ajaxUtils',
    exec: 'ping',
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}?action=ajaxUtils`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
