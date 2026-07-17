import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
/**
 * Set segment to translation on review extended issue panel
 *
 * @param {Object} objRequest
 * @returns {Promise<object>}
 */
export const setTranslation = async (objRequest) => {
  const dataParams = Object.fromEntries(
    Object.entries(objRequest).filter(([_, v]) => v != null),
  )

  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/set-translation`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
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
  if (errors && errors.length > 0) return Promise.reject({errors})

  return data
}
