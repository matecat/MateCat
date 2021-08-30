import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get volume analysis of job
 *
 * @param {string} [idProject=config.id_project]
 * @param {string} [password=config.jpassword]
 * @returns {Promise<object>}
 */
export const getJobVolumeAnalysis = async (
  idProject = config.id_project,
  password = config.jpassword,
) => {
  const dataParams = {
    pid: idProject,
    jpassword: password,
  }

  const formData = new FormData()
  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })

  const response = await fetch(
    `${getMatecatApiDomain()}?action=getVolumeAnalysis`,
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
