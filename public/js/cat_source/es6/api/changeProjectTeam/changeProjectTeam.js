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

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
