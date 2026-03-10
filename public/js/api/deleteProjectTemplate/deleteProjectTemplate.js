import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * delete specific project template
 *
 * @param {Object} template
 * @returns {Promise<object>}
 */
export const deleteProjectTemplate = async (id) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/project-template/${id}`,
    {
      method: 'DELETE',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
