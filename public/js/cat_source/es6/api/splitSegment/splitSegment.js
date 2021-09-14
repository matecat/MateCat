import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Split segment
 *
 * @param {string} idSegment
 * @param {string} source
 * @param {string} [jobId=config.id_job]
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */
export const splitSegment = async (
  idSegment,
  source,
  jobId = config.id_job,
  password = config.password,
) => {
  const dataParams = {
    segment: source,
    id_segment: idSegment,
    id_job: jobId,
    password: password,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}?action=setSegmentSplit`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
