import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch the specific project
 *
 * @param {string} id
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */
export const getProject = async (id, password = config.password) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/projects/${id}/${password}`,
    {
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors) return Promise.reject(errors)

  return data
}
