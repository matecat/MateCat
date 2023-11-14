import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

const fakeFetch = (path, props) =>
  new Promise((resolve) => {
    resolve({
      ok: true,
      json: () =>
        new Promise((resolve) => {
          setTimeout(() => {
            resolve({
              status: 200,
              data: {
                id: '00000000-0000-0000-0000-0000000379fc',
                memory: parseInt(props.body.get('memoryId')),
                size: 18818,
                progress: 0,
              },
            })
          }, 2000)
        }),
    })
  })

export const importMemoryGlossary = async ({engineId, glossary, memoryId}) => {
  const formData = new FormData()

  const params = {
    glossary,
    memoryId,
  }
  Object.keys(params).forEach((key) => {
    formData.append(key, params[key])
  })
  const response = await fakeFetch(
    `${getMatecatApiDomain()}api/v3/mmt/${engineId}/import-glossary`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (data.status !== 200) return Promise.reject(errors)

  return data
}
