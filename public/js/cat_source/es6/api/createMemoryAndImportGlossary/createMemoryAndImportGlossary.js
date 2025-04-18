import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const createMemoryAndImportGlossary = async ({
  engineId,
  glossary,
  name,
}) => {
  const formData = new FormData()

  const params = {
    glossary,
    name,
  }
  Object.keys(params).forEach((key) => {
    formData.append(key, params[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/mmt/${engineId}/glossary/create-memory-and-import`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({response, errors: data.error ?? data})
    } else {
      return Promise.reject({response})
    }
  }

  const data = await response.json()
  if (typeof data?.id === 'undefined') return Promise.reject(data)

  return data
}
