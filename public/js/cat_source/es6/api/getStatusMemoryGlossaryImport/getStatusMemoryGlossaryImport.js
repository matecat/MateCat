import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const getStatusMemoryGlossaryImport = async ({engineId, uuid}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/mmt/${engineId}/job-status/${uuid}`,
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
