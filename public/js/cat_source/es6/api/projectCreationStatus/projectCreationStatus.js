import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Project creation status
 *
 * @param {string} idProject
 * @param {string} password
 * @returns {Promise<object>}
 */
export const projectCreationStatus = async (idProject, password) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/projects/${idProject}/${password}/creation_status`,
    {
      credentials: 'include',
    },
  )
  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  const {status} = response
  return {data, status}
}
