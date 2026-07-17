import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * create new billing model template
 *
 * @param {Object} template
 * @returns {Promise<object>}
 */
export const createBillingModelTemplate = async (template) => {
  const response = await fetch(`${getMatecatApiDomain()}api/v2/payable_rate`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(template),
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
