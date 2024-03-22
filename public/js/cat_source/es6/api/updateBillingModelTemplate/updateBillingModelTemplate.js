import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * modify existing billing model template
 *
 * @param {number} id
 * @param {Object} template
 * @returns {Promise<object>}
 */
export const updateBillingModelTemplate = async ({id, template}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/payable_rate/${id}`,
    {
      method: 'PUT',
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
