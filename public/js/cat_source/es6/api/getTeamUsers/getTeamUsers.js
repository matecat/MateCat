import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get team users list
 *
 * @param {Object} options
 * @param {number} options.teamId
 * @returns {Promise<object>}
 */
export const getTeamUsers = async ({teamId}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/teams/${teamId}/members/public`,
    {
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const responseData = await response.json()
  if (!Array.isArray(responseData) && responseData.errors)
    return Promise.reject(responseData.errors)

  return responseData
}
