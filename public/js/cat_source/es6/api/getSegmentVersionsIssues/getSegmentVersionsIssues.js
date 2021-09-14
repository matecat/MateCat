import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Description
 *
 * @param {string} idSegment
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */
export const getSegmentVersionsIssues = async (
  idSegment,
  idJob = config.id_job,
  password = config.password,
) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${password}/revise/segments/${idSegment}/translation-versions`,
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
