import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const updateTeamName = async (team, newName) => {
  const params = {
    name: newName,
  }

  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/teams/${team.id}`,
    {
      method: 'PUT',
      credentials: 'include',
      body: JSON.stringify(params),
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors) return Promise.reject(errors)

  return data
}
