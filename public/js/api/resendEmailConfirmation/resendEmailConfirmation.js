/**
 * Resend email confirmation (registration process)
 *
 * @param {string} email
 * @returns {Promise<object>}
 */
export const resendEmailConfirmation = async (email) => {
  const paramsData = {
    email,
  }

  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`/api/app/user/resend_email_confirm`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  return response
}
