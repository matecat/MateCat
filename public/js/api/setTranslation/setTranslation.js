import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
/**
 * Set segment to translation on review extended issue panel
 *
 * @param {Object} objRequest
 * @returns {Promise<object>}
 */
export const setTranslation = async (objRequest) => {
  const dataParams = Object.fromEntries(
    Object.entries(objRequest).filter(([_, v]) => v != null),
  )

  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/set-translation`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  // Every failure after this point carries the response: the server answered, so the connection is
  // not what failed. An unreadable body (such as a proxy error page) is one of them.
  const body =
    response.headers.get('Content-Length') !== '0'
      ? await response.json().catch(() => undefined)
      : undefined

  if (!response.ok) {
    return Promise.reject({response, errors: body?.errors ?? body})
  }
  if (!body) return Promise.reject({response})

  const {errors, ...data} = body
  if (errors && errors.length > 0) return Promise.reject({response, errors})

  return data
}
