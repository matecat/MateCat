import {getAuthToken} from './getAuthToken'

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
  const authToken = await getAuthToken()
  if (!authToken.ok) {
    return Promise.reject(authToken)
  }
  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`/api/app/user/login`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
    headers: {
      'xsrf-token': authToken.token,
    },
  })

  if (!response.ok) return Promise.reject(response)
  return response
}
