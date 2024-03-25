import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const deleteMemoryGlossary = async ({engineId, memoryId}) => {
  const formData = new FormData()

  const params = {
    memoryId,
  }
  Object.keys(params).forEach((key) => {
    formData.append(key, params[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/mmt/${engineId}/delete-memory/${memoryId}`,
    {
      method: 'GET',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const data = await response.json()
  if (typeof data?.id === 'undefined') return Promise.reject(data)

  return data
}
