import {getQueryStringFromProps} from '../../utils/queryString'

/**
 * Return leqixa warnings
 *
 * @param {Object} options
 * @param {string} [options.lexiqaDomain=config.lexiqaServer]
 * @returns {Promise<object>}
 */
export const lexiqaTooltipwarnings = async ({
  lexiqaDomain = config.lexiqaServer,
} = {}) => {
  const response = await fetch(
    `${lexiqaDomain}/tooltipwarnings${getQueryStringFromProps(pluginOptions)}`,
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}

export const pluginOptions = {}
