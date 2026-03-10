import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Tm ajax utils
 *
 * @param {Object} options
 * @param {string} options.tmKey
 * @returns {Promise<object>}
 */
export const checkTMKey = async ({tmKey}) => {
  const paramsData = {
    tm_key: tmKey,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}api/app/check-tm-key`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
