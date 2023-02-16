import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Check if job keys have glossary
 *
 * @param {Object} options
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {string} [options.idClient=config.id_client]
 *  @param {string} [options.sourceLanguage=config.source_code]
 * @param {string} [options.targetLanguage=config.target_code]
 * @returns {Promise<object>}
 */
export const checkJobKeysHaveGlossary = async ({
  idJob = config.id_job,
  password = config.password,
  idClient = config.id_client,
  sourceLanguage = config.source_code,
  targetLanguage = config.target_code,
} = {}) => {
  const dataParams = {
    id_job: idJob,
    password: password,
    id_client: idClient,
    source_language: sourceLanguage,
    target_language: targetLanguage,
  }

  const response = await fetch(
    `${getMatecatApiDomain()}api/app/glossary/_keys`,
    {
      method: 'POST',
      credentials: 'include',
      body: JSON.stringify(dataParams),
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
