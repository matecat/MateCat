import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const sendRevisionFeedback = async (
  idJob,
  revisionNumber,
  password,
  text,
) => {
  const params = {
    id_job: idJob,
    revision_number: revisionNumber,
    password: password,
    feedback: text,
  }
  const formData = new FormData()

  Object.keys(params).forEach((key) => {
    formData.append(key, params[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}api/v3/feedback`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
