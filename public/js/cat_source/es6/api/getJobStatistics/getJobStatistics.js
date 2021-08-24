import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const getJobStatistics = async (idJob, password) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/jobs/${idJob}/${password}/stats`,
    {
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
