import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get volume analysis of job
 *
 * @param {string} [idProject=config.id_project]
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */
export const getJobVolumeAnalysis = async (
  idProject = config.id_project,
  idJob = config.id_job,
  password = config.job_password,
) => {
  const dataParams = {
    id_project: idProject,
    id_job: idJob,
    password: password,
  }

  const formData = new FormData()
  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })

  const response = await fetch(
    `${getMatecatApiDomain()}api/app/get-volume-analysis`,
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
