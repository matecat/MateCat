/**
 * Reset password user
 *
 * @param {string} old_password
 * @param {string} password
 * @param {string} passwordConfimation
 * @param {bool} isLoggedIn
 * @returns {Promise<object>}
 */
export const resetPasswordUser = async (
  old_password,
  password,
  passwordConfimation,
  isLoggedIn,
) => {
  const paramsData = {
    old_password,
    password,
    password_confirmation: passwordConfimation,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })

  let url = `/api/app/user/password`
  if (isLoggedIn) {
    url = `/api/app/user/password/change`
  }

  const response = await fetch(url, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  return response
}
