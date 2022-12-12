import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const loadGlossaryFile = async ({id}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/glossaries/import/status/${id}`,
    {
      credentials: 'include',
    },
  )

  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({response, errors: data.errors ?? data})
    } else {
      return Promise.reject({response})
    }
  }

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject({response, errors})

  return data
}
