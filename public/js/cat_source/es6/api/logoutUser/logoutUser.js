/**
 * Logout user
 *
 * @returns {Promise<object>}
 */
export const logoutUser = async () => {
  const response = await fetch(`/api/app/user/logout`, {
    method: 'POST',
    credentials: 'include',
  })

  return response
}
