import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const getDeepLGlosssaries = async ({engineId}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/deepl/${engineId}/glossaries`,
    {
      method: 'GET',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const list = await response.json()

  return list
}
