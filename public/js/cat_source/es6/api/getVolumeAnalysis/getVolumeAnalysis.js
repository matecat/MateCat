import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get volume analysis of project
 *
 * @param {string} [idProject=config.id_project]
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */
export const getVolumeAnalysis = async (
  idProject = config.id_project,
  password = config.password,
) => {
  const dataParams = {
    pid: idProject,
    ppassword: password,
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
