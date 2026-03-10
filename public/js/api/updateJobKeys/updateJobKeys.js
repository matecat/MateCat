import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Update job keys from settings panel of job
 *
 * @param {Object} options
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {string} [options.currentPassword=config.currentPassword]
 * @param {string} options.getPublicMatches
 * @param {string} options.dataTm
 * @returns {Promise<object>}
 */
export const updateJobKeys = async ({
  idJob = config.id_job,
  password = config.password,
  currentPassword = config.currentPassword,
  getPublicMatches,
  publicTmPenalty,
  dataTm,
}) => {
  const paramsData = Object.entries({
    action: 'updateJobKeys',
    job_id: idJob,
    job_pass: password,
    get_public_matches: getPublicMatches,
    public_tm_penalty: publicTmPenalty,
    data: dataTm,
    current_password: currentPassword,
  })
    .filter(([, value]) => typeof value !== 'undefined')
    .reduce((acc, [key, value]) => ({...acc, [key]: value}), {})
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/update-job-keys`,
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
