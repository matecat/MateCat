/**
 * Login user
 *
 * @param {string} email
 * @param {string} password
 * @returns {Promise<object>}
 */
export const loginUser = async (email, password) => {
  const paramsData = {
    email,
    password,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`/api/app/user/login`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  return response
}
