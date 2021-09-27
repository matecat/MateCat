import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Reload quality report
 *
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */
export const reloadQualityReport = async (
  idJob = config.id_job,
  password = config.password,
) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/jobs/${idJob}/${password}/quality-report`,
    {
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)
  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
