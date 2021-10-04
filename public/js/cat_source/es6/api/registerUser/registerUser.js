import {flattenObject} from '../../utils/queryString'

/**
 * Register user
 *
 * @param {Object} user
 * @param {string} user.firstname
 * @param {string} user.surname
 * @param {string} user.email
 * @param {string} user.password
 * @param {string} user.passwordConfirmation
 * @param {string} user.wantedUrl
 * @returns {Promise<object>}
 */
export const registerUser = async ({
  firstname,
  surname,
  email,
  password,
  passwordConfirmation,
  wantedUrl,
}) => {
  const paramsData = flattenObject({
    user: {
      first_name: firstname,
      last_name: surname,
      email,
      password,
      password_confirmation: passwordConfirmation,
      wanted_url: wantedUrl,
    },
  })
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`/api/app/user`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  try {
    const {errors, ...data} = await response.json()
    if (errors && errors.length > 0) return Promise.reject(errors)
    return data
  } catch (error) {
    return response
  }
}
