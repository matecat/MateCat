import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const getGlobalWarnings = async ({id_job, password}) => {
  const paramsData = {
    id_job,
    password,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })

  const response = await fetch(
    `${getMatecatApiDomain()}api/app/get-global-warning`,
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
