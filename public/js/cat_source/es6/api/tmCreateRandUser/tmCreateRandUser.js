import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Called from TM panel "new resource"
 *
 * @returns {Promise<object>}
 */
export const tmCreateRandUser = async () => {
  const paramsData = {}
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/create-random-user`,
    {
      method: 'POST',
      body: formData,
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
