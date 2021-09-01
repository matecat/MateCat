import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const addJobTranslator = async (email, date, timezone, job) => {
  const params = {
    email: email,
    delivery_date: Math.round(date / 1000),
    timezone: timezone,
  }

  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${job.id}/${job.password}/translator`,
    {
      method: 'POST',
      credentials: 'include',
      body: JSON.stringify(params),
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
