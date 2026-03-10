import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get suggestion for phrase by AI
 * @param {string} idSegment
 * @param {string} words
 * @param {string} phrase
 * @param {string} [targetLanguage=config.target_code]
 * @param {string} [idClient=config.id_client]
 * @param {string} [idClient=config.idJob]
 * @param {string} [idClient=config.password]
 * @returns {Promise<object>}
 */

export const aiSuggestion = async ({
  idSegment,
  words,
  phrase,
  targetLanguage = config.target_code,
  idClient = config.id_client,
  idJob = config.id_job,
  password = config.password,
}) => {
  const dataParams = {
    id_segment: idSegment,
    target: targetLanguage,
    word: words,
    phrase,
    id_client: idClient,
    id_job: idJob,
    password,
  }

  const response = await fetch(`${getMatecatApiDomain()}api/app/ai-assistant`, {
    method: 'POST',
    credentials: 'include',
    body: JSON.stringify(dataParams),
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
