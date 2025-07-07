import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Send type of issue of segment
 *
 * @param {string} [idJob]
 * @param {string} [password]
 * @param {number} [file_id]
 * @param {number} [file_type]
 * @returns {Promise<object>}
 */
export const getFileSegments = async ({
  idJob,
  password,
  file_id,
  file_type,
}) => {
  let dataParams = {}
  if (file_type && file_type === 'file_part') {
    dataParams.file_part_id = file_id
  } else {
    dataParams.file_id = file_id
  }

  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })

  const response = await fetch(
    `${getMatecatApiDomain()}api/app/jobs/${idJob}/${password}/segments`,
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
