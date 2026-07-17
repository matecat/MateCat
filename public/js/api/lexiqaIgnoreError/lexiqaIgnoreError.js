import {flattenObject} from '../../utils/queryString'
/**
 * Send ignore error to Lexiqa server
 *
 * @param {Object} options
 * @param {string} options.errorId
 * @param {string} [options.lexiqaDomain=config.lexiqaServer]
 * @returns {Promise<object>}
 */
export const lexiqaIgnoreError = async ({
  errorId,
  lexiqaDomain = config.lexiqaServer,
}) => {
  const dataParams = flattenObject({
    data: {
      errorid: errorId,
    },
  })

  const response = await fetch(`${lexiqaDomain}/ignoreerror`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams(dataParams),
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
