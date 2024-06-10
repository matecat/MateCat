import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const modifyUserInfo = async (firstName, lastName) => {
  const params = {
    first_name: firstName,
    last_name: lastName,
  }

  const response = await fetch(`${getMatecatApiDomain()}api/v2/user`, {
    method: 'PUT',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(params),
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
