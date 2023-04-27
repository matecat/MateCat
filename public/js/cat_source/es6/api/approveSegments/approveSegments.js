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
  revisionNumber = config.revisionNumber,
) => {
  const dataParams = flattenObject({
    segments_id: segments,
    status: 'approved',
    client_id: config.id_client,
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
