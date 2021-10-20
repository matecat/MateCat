import {getQueryStringFromProps} from '../../utils/queryString'

/**
 * Return leqixa warnings
 *
 * @param {Object} options
 * @param {string} [options.lexiqaDomain=config.lexiqaServer]
 * @returns {Promise<object>}
 */
/* export const lexiqaTooltipwarnings = async ({
  lexiqaDomain = config.lexiqaServer,
} = {}) => {
  const response = await fetch(
    `${lexiqaDomain}/tooltipwarnings${getQueryStringFromProps(pluginOptions)}`,
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
} */

export const pluginOptions = {}

// TEMP MOCK lexiQA response
export const lexiqaTooltipwarnings = async ({
  lexiqaDomain = config.lexiqaServer,
} = {}) => {
  const response = await fetch(
    `${lexiqaDomain}/tooltipwarnings${getQueryStringFromProps(pluginOptions)}`,
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return {
    ...data,
    styleGuideMessages: {
      uber_itIT_sd1: {
        msg: 'Test message 1',
      },
      uber_itIT_sd2: {
        msg: 'Test message 2',
      },
      uber_itIT_sd3: {
        msg: 'Test message 3',
      },
    },
  }
}

$(document).ready(function () {
  const superDoLexiQA = $.lexiqaAuthenticator.doLexiQA
  $.lexiqaAuthenticator.doLexiQA = function (options, callback) {
    const callbackModified = (error, result) => {
      callback(error, {
        ...result,
        styleGuideMessages: ['uber_itIT_sd1', 'uber_itIT_sd2', 'uber_itIT_sd3'],
      })
    }
    superDoLexiQA.call($.lexiqaAuthenticator, options, callbackModified)
  }
})
