import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import {flattenObject} from '../../utils/queryString'
/**
 * Fetch the specific project
 *
 * @param {string} idProject
 * @param {string} password
 * @param {int} jid
 * @param {string} jpassword
 * @param {string} fixedDelivery
 * @param {string} typeOfService
 * @param {string} timezone
 * @param {string} currency
 * @returns {Promise<object>}
 */
export const getOutsourceQuote = async (
  idProject,
  password,
  jid,
  jpassword,
  fixedDelivery,
  typeOfService,
  timezone,
  currency,
) => {
  const data = flattenObject({
    action: 'outsourceTo',
    pid: idProject,
    currency: currency,
    ppassword: password,
    fixedDelivery: fixedDelivery,
    typeOfService: typeOfService,
    timezone: timezone,
    jobs: [
      {
        jid: jid,
        jpassword: jpassword,
      },
    ],
  })
  const formData = new FormData()

  Object.keys(data).forEach((key) => {
    formData.append(key, data[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}?action=outsourceTo`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...restData} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return restData
}
