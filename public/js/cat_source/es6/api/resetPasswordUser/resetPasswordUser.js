/**
 * Reset password user
 *
 * @param {string} password
 * @param {string} passwordConfimation
 * @returns {Promise<object>}
 */
export const resetPasswordUser = async (password, passwordConfimation) => {
  const paramsData = {
    password,
    password_confirmation: passwordConfimation,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`/api/app/user/password`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  return response
}
