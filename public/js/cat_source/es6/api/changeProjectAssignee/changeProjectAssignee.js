import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Change or remove assignee to the project
 *
 * @param {string} idOrg
 * @param {string} idProject
 * @param {string} idAssignee
 * @returns {Promise<object>}
 */
export const changeProjectAssignee = async (idOrg, idProject, idAssignee) => {
  const dataParams = {
    id_assignee: idAssignee,
  }

  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/teams/${idOrg}/projects/${idProject}`,
    {
      method: 'PUT',
      credentials: 'include',
      body: JSON.stringify(dataParams),
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
