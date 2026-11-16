/**
 * Return lexiqa supported locales
 *
 * @returns {Promise<object>}
 */
export const getLexiqaSupportedLocales = async ({
  lexiqaDomain = config.lexiqaServer,
} = {}) => {
  const response = await fetch(`${lexiqaDomain}/supportedLocales`)

  if (!response.ok) return Promise.reject(response)

  const data = await response.json()

  return data
}
