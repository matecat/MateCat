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
export const changeJobsOrProjectStatus = async (
  type,
  {id, password},
  status,
) => {
  const dataParams = {
    new_status: status,
    res: type, //Project or Job:
    id: id, // Job or Project Id
    password: password, // Job or Project Password
  }

  const formData = new FormData()
  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })

  const response = await fetch(
    `${getMatecatApiDomain()}?action=changeJobsStatus`,
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
