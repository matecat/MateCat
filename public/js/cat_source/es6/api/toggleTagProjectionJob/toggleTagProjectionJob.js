import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Enable or disable tag projection inside job
 *
 * @param {Object} options
 * @param {boolean} options.enabled
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @returns {Promise<object>}
 */
export const toggleTagProjectionJob = async ({
  enabled,
  idJob = config.id_job,
  password = config.password,
}) => {
  const dataParams = {
    tag_projection: enabled,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${password}/options`,
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
