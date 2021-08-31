import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import {flattenObject} from '../../utils/queryString'

export const addUserTeam = async (team, userEmail) => {
  var email = typeof userEmail === 'string' ? [userEmail] : userEmail
  const params = flattenObject({
    members: email,
  })
  const formData = new FormData()

  Object.keys(params).forEach((key) => {
    formData.append(key, params[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/teams/${team.id}/members`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors) return Promise.reject(errors)

  return data
}
