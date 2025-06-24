import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Glossary for segment
 *
 * @param {Object} options
 * @param {string} options.idSegment
 * @param {string} options.source
 * @param {string} [options.idClient=config.id_client]
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {string} [options.sourceLanguage=config.source_code]
 * @param {string} [options.targetLanguage=config.target_code]
 * @returns {Promise<object>}
 */
export const getGlossaryForSegment = async ({
  idSegment,
  source,
  idClient = config.id_client,
  idJob = config.id_job,
  password = config.password,
  sourceLanguage = config.source_code,
  targetLanguage = config.target_code,
}) => {
  const dataParams = {
    id_segment: idSegment,
    source,
    id_client: idClient,
    id_job: idJob,
    password,
    source_language: sourceLanguage,
    target_language: targetLanguage,
  }

  const response = await fetch(
    `${getMatecatApiDomain()}api/app/glossary/_get`,
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
