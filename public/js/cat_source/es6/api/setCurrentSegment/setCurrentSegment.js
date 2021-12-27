import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const setCurrentSegment = async ({
  password,
  revision_number,
  id_segment,
  id_job,
}) => {
  const paramsData = {
    action: 'setCurrentSegment',
    password,
    revision_number,
    id_segment,
    id_job,
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
