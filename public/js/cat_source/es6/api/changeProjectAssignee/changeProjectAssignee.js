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
