import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch the specific project
 *
 * @param {string} id
 * @param {string} [project_access_token=config.password]
 * @returns {Promise<object>}
 */
export const getProjectByToken = async (
  id,
  project_access_token = config.project_access_token,
) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/projects/${id}/token/${project_access_token}`,
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
