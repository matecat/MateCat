import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import {Base64} from 'js-base64'

/**
 * Get team users list
 *
 * @param {Object} options
 * @param {number} options.teamId
 * @returns {Promise<object>}
 */
export const getTeamUsers = async ({teamId, teamName}) => {
  const teamNameBase64 = Base64.encode(teamName)
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/teams/${teamId}/${teamNameBase64}/members/public`,
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
