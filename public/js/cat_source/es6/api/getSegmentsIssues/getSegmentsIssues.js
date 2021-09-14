import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Retrieve segments issues
 *
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */
export const getSegmentsIssues = async (
  idJob = config.id_job,
  password = config.password,
) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${password}/translation-issues`,
    {
      method: 'GET',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
