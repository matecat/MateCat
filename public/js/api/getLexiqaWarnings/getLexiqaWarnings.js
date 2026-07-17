/**
 * Return lexiqa warnings
 *
 * @param {Object} options
 * @param {string} options.partnerId
 * @param {string} [options.lexiqaDomain=config.lexiqaServer]
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @returns {Promise<object>}
 */
export const getLexiqaWarnings = async ({
  partnerId,
  lexiqaDomain = config.lexiqaServer,
  idJob = config.id_job,
  password = config.password,
}) => {
  const response = await fetch(
    `${lexiqaDomain}/matecaterrors?id=${partnerId}-${idJob}-${password}`,
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
