import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const createAndImportDeepLGlossary = async ({
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
    `${getMatecatApiDomain()}api/v3/deepl/${engineId}/glossaries`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) return Promise.reject(response)

  const data = await response.json()
  if (typeof data?.ready === 'undefined') return Promise.reject(data)

  return data
}
