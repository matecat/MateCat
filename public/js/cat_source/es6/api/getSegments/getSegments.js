import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const getSegments = async ({jid, password, step, segment, where}) => {
  const paramsData = {
    jid,
    password,
    step,
    segment,
    where,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })

  const response = await fetch(`${getMatecatApiDomain()}api/app/get-segments`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
