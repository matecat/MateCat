import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const createTeam = async (teamName, members) => {
  const params = {
    type: 'general',
    name: teamName,
    members: members,
  }
  const formData = new FormData()

  Object.keys(params).forEach((key) => {
    formData.append(key, params[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}api/v2/teams`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors) return Promise.reject(errors)

  return data
}
