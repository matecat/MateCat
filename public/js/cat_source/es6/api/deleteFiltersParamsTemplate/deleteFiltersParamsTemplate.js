import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * delete specific filters params template
 *
 * @param {integer} id
 * @returns {Promise<object>}
 */
export const deleteFiltersParamsTemplate = async (id) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/filters-config-template/${id}`,
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
