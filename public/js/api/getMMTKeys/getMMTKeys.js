import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const getMMTKeys = async ({engineId}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/mmt/${engineId}/keys`,
    {
      method: 'GET',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const list = await response.json()

  return list.filter(({has_glossary}) => has_glossary)
}
