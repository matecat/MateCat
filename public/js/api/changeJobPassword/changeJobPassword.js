import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
/**
 * Change the password for the job
 * @param job
 * @param password
 * @param revision_number
 * @param undo
 * @param old_pass
 */
export const changeJobPassword = async (
  job,
  password,
  revision_number,
  undo,
  old_pass,
) => {
  const params = {
    id: job.id,
    password: password,
    new_password: old_pass,
    revision_number,
    undo: undo,
  }
  const formData = new FormData()

  Object.keys(params).forEach((key) => {
    formData.append(key, params[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/change-password`,
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
