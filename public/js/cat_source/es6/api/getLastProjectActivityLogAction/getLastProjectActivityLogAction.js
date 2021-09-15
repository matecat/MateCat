import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch last activity project informations
 *
 * @param {object} object
 * @param {string} object.id
 * @param {string} object.password
 * @param {AbortController} controller
 * @param {AbortSignal} controller.signal
 * @returns {Promise<object>}
 */
export const getLastProjectActivityLogAction = async (
  {id, password},
  {signal},
) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/activity/project/${id}/${password}/last`,
    {
      credentials: 'include',
      signal,
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
