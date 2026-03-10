import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const deleteUserApiKey = async () => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/api-key/delete`,
    {
      method: 'DELETE',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors) return Promise.reject(errors)

  return data
}
