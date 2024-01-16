import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const deleteDeepLGlossary = async ({engineId, id}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/deepl/${engineId}/glossaries/${id}`,
    {
      method: 'DELETE',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const data = await response.json()
  if (typeof data?.id === 'undefined') return Promise.reject(data)

  return data
}
