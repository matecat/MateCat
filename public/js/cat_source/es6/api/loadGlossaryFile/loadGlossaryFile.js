import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const loadGlossaryFile = async ({key, name}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/glossaries/import/status/${key}/${name}`,
    {
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
