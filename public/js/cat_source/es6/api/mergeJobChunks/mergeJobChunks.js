import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import {flattenObject} from '../../utils/queryString'

export const mergeJobChunks = async (project, job) => {
  const params = flattenObject({
    exec: 'merge',
    project_id: project.id,
    project_pass: project.password,
    job_id: job.id,
  })
  const formData = new FormData()

  Object.keys(params).forEach((key) => {
    formData.append(key, params[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}?action=splitJob`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
