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
