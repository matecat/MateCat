import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

let count = 0
const fakeFetch = () =>
  new Promise((resolve) => {
    resolve({
      ok: true,
      json: () =>
        new Promise((resolve) => {
          setTimeout(() => {
            resolve({
              id: '00000000-0000-0000-0000-0000003e9dc3',
              memory: 37790,
              size: 6,
              begin: 960994456,
              end: 960994461,
              dataChannel: 0,
              progress: count === 4 ? 1 : 0,
            })
            count++
          }, 2000)
        }),
    })
  })

export const getStatusMemoryGlossaryImport = async ({engineId, uuid}) => {
  const response = await fakeFetch(
    `${getMatecatApiDomain()}/api/v3/mmt/${engineId}/job-status/${uuid}`,
    {
      method: 'GET',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const data = await response.json()

  return data
}
