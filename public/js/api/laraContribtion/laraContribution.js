import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

export const laraContribution = async ({
  source,
  translation,
  contextListBefore,
  contextListAfter,
  sid,
  jobId,
}) => {
  const obj = {
    source,
    translation,
    contextListBefore,
    contextListAfter,
    sid,
    jobId,
  }
  const dataParams = Object.fromEntries(
    Object.entries(obj).filter(([_, v]) => v != null),
  )

  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}lara/contributions`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({response, errors: data.errors ?? data})
    } else {
      return Promise.reject({response})
    }
  }
  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
