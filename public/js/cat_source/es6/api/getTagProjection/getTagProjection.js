import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const getTagProjection = async ({
  password,
  id_job,
  source,
  target,
  source_lang,
  target_lang,
  suggestion,
  id_segment,
}) => {
  const paramsData = {
    action: 'getTagProjection',
    id_job,
    password,
    source,
    target,
    source_lang,
    target_lang,
    suggestion,
    id_segment,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })

  const response = await fetch(
    `${getMatecatApiDomain()}?action=${paramsData.action}`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
