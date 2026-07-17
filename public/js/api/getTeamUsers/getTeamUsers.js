import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get the team members list for the current job, for comment attribution.
 *
 * Authorized by the job password capability (unguessable) rather than a
 * guessable team name, so team member names cannot be harvested by guessing
 * team ids/names (CWE-639). The backend resolves job -> project -> team and
 * returns the public projection (uid + first/last name, no email).
 *
 * @param {Object} [options]
 * @param {number|string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @returns {Promise<object>}
 */
export const getTeamUsers = async ({
  idJob = config.id_job,
  password = config.password,
} = {}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/jobs/${idJob}/${password}/team-members`,
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
