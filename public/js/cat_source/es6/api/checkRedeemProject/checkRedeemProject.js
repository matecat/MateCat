/**
 * Check redeem project
 *
 * @returns {Promise<object>}
 */
export const checkRedeemProject = async () => {
  const response = await fetch(`/api/app/user/redeem_project`, {
    method: 'POST',
    credentials: 'include',
  })

  return response
}
