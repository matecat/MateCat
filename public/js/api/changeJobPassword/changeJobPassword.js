import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
/**
 * Change the password for the job
 * @param job
 * @param password the current password of the resource being changed: the translate password to
 *        change the job password, or the current password of a revision step to change that one
 * @param undo
 * @param old_pass
 */
export const changeJobPassword = async (job, password, undo, old_pass) => {
  // No revision_number: the password sent above is what decides which password gets rotated.
  const params = {
    id: job.id,
    password: password,
    new_password: old_pass,
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
