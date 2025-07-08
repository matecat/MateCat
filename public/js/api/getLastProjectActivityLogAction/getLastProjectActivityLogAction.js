import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch last activity project informations
 *
 * @param {object} object
 * @param {string} object.id
 * @param {string} object.password
 * @returns {Promise<object>}
 */
export const getLastProjectActivityLogAction = async ({id, password}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/activity/project/${id}/${password}/last`,
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
