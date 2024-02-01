import Cookies from 'js-cookie'

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

  const tokenResponse = await fetch('/api/app/user/login/token')

  if (tokenResponse.ok) {
    const token = Cookies.get('xsrf-token')

    Object.keys(paramsData).forEach((key) => {
      formData.append(key, paramsData[key])
    })
    const response = await fetch(`/api/app/user/login`, {
      method: 'POST',
      body: formData,
      credentials: 'include',
      headers: {
        'xsrf-token': token,
      },
    })

    if (!response.ok) return Promise.reject(response)

    return response
  } else {
    return Promise.reject(tokenResponse)
  }
}
