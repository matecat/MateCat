import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get completation status of project
 *
 * @param {string} [idProject=config.id_project]
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */

export const getCompletionStatus = async (
  idProject = config.id_project,
  password = config.password,
) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/projects/${idProject}/${password}/completion_status`,
    {
      method: 'GET',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
