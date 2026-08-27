import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const sendRevisionFeedback = async (idJob, password, text) => {
  // No revision_number: the revision phase the feedback belongs to is derived server side from
  // this password, which must be the current password of the phase being reviewed.
  const params = {
    id_job: idJob,
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
