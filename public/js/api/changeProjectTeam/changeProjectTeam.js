import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Move project into specific team
 *
 * @param {string} newTeamId
 * @param {Object} project
 * @returns {Promise<object>}
 */
export const changeProjectTeam = async (newTeamId, project) => {
  const dataParams = JSON.stringify({
    id_team: newTeamId,
  })

  // const formData = new FormData()
  // Object.keys(dataParams).forEach((key) => {
  //   formData.append(key, dataParams[key])
  // })

  const {id, id_team} = project

  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/teams/${id_team}/projects/${id}`,
    {
      method: 'PUT',
      credentials: 'include',
      body: dataParams,
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
