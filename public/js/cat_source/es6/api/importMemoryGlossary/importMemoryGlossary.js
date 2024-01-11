import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const importMemoryGlossary = async ({engineId, glossary, memoryId}) => {
  const formData = new FormData()

  const params = {
    glossary,
    memoryId,
  }
  Object.keys(params).forEach((key) => {
    formData.append(key, params[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/mmt/${engineId}/import-glossary`,
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
