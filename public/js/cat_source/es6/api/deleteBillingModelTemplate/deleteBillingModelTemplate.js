import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * delete specific billing model template
 *
 * @param {integer} id
 * @returns {Promise<object>}
 */
export const deleteBillingModelTemplate = async (id) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/payable_rate/${id}`,
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
