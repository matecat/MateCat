/**
 * Login user
 *
 * @returns {Promise<object>}
 */
export const getSocketAuthToken = async () => {
  const tokenResponse = await fetch('/api/app/user/login/socket')
  if (tokenResponse.ok) {
    return Promise.resolve({ok: true, token: tokenResponse.headers.get('xsrf-token')})
  } else {
    return Promise.reject(tokenResponse)
  }
}
