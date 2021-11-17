import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Update job keys from settings panel of job
 *
 * @param {Object} options
 * @param {string} [options.idJob=config.job_id]
 * @param {string} [options.password=config.password]
 * @param {string} [options.currentPassword=config.currentPassword]
 * @param {string} options.getPublicMatches
 * @param {string} options.dataTm
 * @returns {Promise<object>}
 */
export const updateJobKeys = async ({
  idJob = config.job_id,
  password = config.password,
  currentPassword = config.currentPassword,
  getPublicMatches,
  dataTm,
}) => {
  const paramsData = {
    action: 'updateJobKeys',
    job_id: idJob,
    job_pass: password,
    get_public_matches: getPublicMatches,
    data: dataTm,
    current_password: currentPassword,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}?action=${paramsData.action}`,
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
