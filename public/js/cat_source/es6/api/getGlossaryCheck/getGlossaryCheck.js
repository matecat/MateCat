import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Retrieve glossary match
 *
 * @param {Object} options
 * @param {string} options.target
 * @param {string} options.source
 * @param {string} options.idSegment
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {string} [options.idClient=config.id_client]
 * @param {string} [options.sourceLanguage=config.source_code]
 * @param {string} [options.targetLanguage=config.target_code]
 * @param {array} [options.keys]
 * @returns {Promise<object>}
 */
export const getGlossaryCheck = async ({
  target,
  source,
  idSegment,
  idJob = config.id_job,
  password = config.password,
  idClient = config.id_client,
  sourceLanguage = config.source_code,
  targetLanguage = config.target_code,
  keys,
}) => {
  const dataParams = {
    target,
    source,
    id_segment: idSegment,
    id_job: idJob,
    password,
    id_client: idClient,
    source_language: sourceLanguage,
    target_language: targetLanguage,
    keys,
  }

  const response = await fetch(
    `${getMatecatApiDomain()}api/app/glossary/_check`,
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
