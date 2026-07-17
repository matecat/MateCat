import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch project activity log
 *
 * @param {string} id
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */
export const getActivityLog = async (id, password = config.password) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/activity/project/${id}/${password}`,
    {
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
