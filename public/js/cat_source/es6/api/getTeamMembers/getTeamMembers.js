import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
export const getTeamMembers = async (teamId) => {
  let url = `${getMatecatApiDomain()}api/v2/teams/${teamId}/members`

  const res = await fetch(url, {
    credentials: 'include',
  })

  if (!res.ok) {
    return Promise.reject(res)
  }

  const {errors, ...restData} = await res.json()

  if (errors) {
    return Promise.reject(errors)
  }

  return restData
}
