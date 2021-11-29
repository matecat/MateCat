import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Copy all source to target (segments)
 *
 * @param {Object} options
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {string} [options.revisionNumber=config.revisionNumber]
 * @returns {Promise<object>}
 */
export const copyAllSourceToTarget = async ({
  idJob = config.id_job,
  password = config.password,
  revisionNumber = config.revisionNumber,
} = {}) => {
  const paramsData = {
    action: 'copyAllSource2Target',
    id_job: idJob,
    pass: password,
    revision_number: revisionNumber,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}?action=copyAllSource2Target`,
    {
      method: 'POST',
      body: formData,
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
