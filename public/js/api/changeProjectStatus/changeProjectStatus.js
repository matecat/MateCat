import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch change jobs or project status
 * ex. Archive job, cancel job, ecc..
 *
 * @param {string} type
 * @param {object} object
 * @param {string} object.id
 * @param {password} object.password
 * @param {string} status
 * @returns {Promise<object>}
 */
export const changeProjectStatus = async (pid, password, status) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/projects/${pid}/${password}/${status}`,
    {
      method: 'POST',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
