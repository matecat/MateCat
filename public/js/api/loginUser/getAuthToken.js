import Cookies from 'js-cookie'

/**
 * Login user
 *
 * @returns {Promise<object>}
 */
export const getAuthToken = async () => {
  const tokenResponse = await fetch('/api/app/user/login/token')
  if (tokenResponse.ok) {
    return Promise.resolve({ok: true, token: Cookies.get('xsrf-token')})
  } else {
    return Promise.reject(tokenResponse)
  }
}
