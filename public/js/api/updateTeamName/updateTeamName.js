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
