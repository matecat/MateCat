import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Description
 *
 * @param {string} idProject
 * @param {string} passwordProject
 * @param {string} idJob
 * @param {string} passwordJob
 * @returns {Promise<object>}
 */
export const getSecondPassReview = async (
  idProject,
  passwordProject,
  idJob,
  passwordJob,
) => {
  const dataParams = {
    id_job: idJob,
    password: passwordJob,
  }

  const formData = new FormData()
  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/projects/${idProject}/${passwordProject}/r2`,
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
