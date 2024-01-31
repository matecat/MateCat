import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const updateMemoryGlossary = async ({engineId, memoryId, name}) => {
  const formData = new FormData()

  const params = {
    name,
  }
  Object.keys(params).forEach((key) => {
    formData.append(key, params[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/mmt/${engineId}/update-memory/${memoryId}`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) return Promise.reject(response)

  const data = await response.json()
  if (typeof data?.id === 'undefined') return Promise.reject(data)

  return data
}
