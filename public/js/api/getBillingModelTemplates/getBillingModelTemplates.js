import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch list of billing models templates
 *
 * @returns {Promise<object>}
 */
export const getBillingModelTemplates = async () => {
  const response = await fetch(`${getMatecatApiDomain()}api/v2/payable_rate`, {
    method: 'GET',
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}

/**
 * Return default Billing template
 *
 * @returns {Promise<object>}
 */
export const getBillingModelTemplateDefault = async () => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/payable_rate/default`,
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
