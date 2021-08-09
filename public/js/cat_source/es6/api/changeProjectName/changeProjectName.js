import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Change name of project
 *
 * @param {string} idOrg
 * @param {string} idProject
 * @param {string} name
 * @returns {Promise<object>}
 */
export const changeProjectName = async (idOrg, idProject, name) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/teams/${idOrg}/projects/${idProject}`,
    {
      method: 'PUT',
      credentials: 'include',
      body: JSON.stringify({name}),
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
