/**
 * Return leqixa warnings
 *
 * @param {string} [lexiqaDomain=config.lexiqaServer]
 * @returns {Promise<object>}
 */
export const lexiqaTooltipwarnings = async (
  lexiqaDomain = config.lexiqaServer,
) => {
  const response = await fetch(`${lexiqaDomain}/tooltipwarnings`)

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
