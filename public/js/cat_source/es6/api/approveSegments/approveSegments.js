import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import {flattenObject} from '../../utils/queryString'

/**
 * Mark approved filtered segments
 *
 * @param {Array} segments
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @param {string} [idClient=config.id_client]
 * @param {number} [revisionNumber=config.revisionNumber]
 * @returns {Promise<object>}
 */
export const approveSegments = async (
  segments,
  idJob = config.id_job,
  password = config.password,
  idClient = config.id_client,
  revisionNumber = config.revisionNumber,
) => {
  const dataParams = flattenObject({
    segments_id: segments,
    status: 'approved',
    client_id: idClient,
    revision_number: revisionNumber,
  })
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${password}/segments/status`,
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
