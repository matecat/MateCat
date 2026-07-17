/**
 * Send email with the instructions to create a new password
 *
 * @param {string} email
 * @param {string} wantedUrl
 * @returns {Promise<object>}
 */
export const forgotPassword = async (email, wantedUrl) => {
  const paramsData = {
    email,
    wanted_url: wantedUrl,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`/api/app/user/forgot_password`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject({response})

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject({errors})
  return data
}
