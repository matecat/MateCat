import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * create new filters params template
 *
 * @param {Object} template
 * @returns {Promise<object>}
 */
export const createFiltersParamsTemplate = async (template) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/filters-config-template/`,
    {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(template),
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
