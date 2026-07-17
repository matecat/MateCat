import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const createUserApiKey = async () => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/api-key/create`,
    {
      method: 'POST',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors) return Promise.reject(errors)

  return data
}
