import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const getLocalWarnings = async ({
  id,
  id_job,
  password,
  src_content,
  trg_content,
  segment_status,
  characters_counter,
}) => {
  const paramsData = {
    action: 'getWarning',
    id,
    id_job,
    password,
    src_content,
    trg_content,
    segment_status,
    characters_counter,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })

  const response = await fetch(
    `${getMatecatApiDomain()}?action=${paramsData.action}&type=local`,
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
