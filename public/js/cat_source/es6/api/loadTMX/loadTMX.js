import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Import TMX file
 *
 * @param {Object} options
 * @param {string} options.key
 * @param {string} options.name
 * @returns {Promise<object>}
 */
export const loadTMX = async ({key, name}) => {
  const paramsData = {
    action: 'loadTMX',
    exec: 'uploadStatus',
    tm_key: key,
    name,
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

  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({response, errors: data.errors ?? data})
    } else {
      return Promise.reject({response})
    }
  }

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject({response, errors})

  return data
}
