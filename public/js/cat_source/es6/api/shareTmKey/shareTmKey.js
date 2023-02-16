import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Share TM key
 *
 * @param {Object} options
 * @param {string} options.key
 * @param {string} options.emails
 * @returns {Promise<object>}
 */
export const shareTmKey = async ({key, emails}) => {
  const paramsData = {
    action: 'userKeys',
    exec: 'share',
    key,
    emails,
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
