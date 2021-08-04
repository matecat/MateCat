import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch the specific project
 *
 * @param {string} id
 * @param {string} [password=window.config.password]
 * @returns {Promise<object>}
 */
export const getProject = async (id, password = config.password) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/projects/${id}/${password}`,
    {
      credentials: 'include',
    },
  )

  return await response.json()
}
