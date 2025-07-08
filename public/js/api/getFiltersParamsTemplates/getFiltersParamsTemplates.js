import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch list of filters params templates
 *
 * @returns {Promise<object>}
 */
export const getFiltersParamsTemplates = async () => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/filters-config-template/`,
    {
      method: 'GET',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
