import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const laraAuth = async ({idJob, password}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/jobs/${idJob}/${password}/lara/auth`,
    {
      credentials: 'include',
    },
  )

  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({response, errors: data.errors[0] ?? data})
    } else {
      return Promise.reject({response})
    }
  }

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
