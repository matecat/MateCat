import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const removeTeamUser = async (team, userId) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/teams/${team.id}/members/${userId}`,
    {
      method: 'DELETE',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors) return Promise.reject(errors)

  return data
}
