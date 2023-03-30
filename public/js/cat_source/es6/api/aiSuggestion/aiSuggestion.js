import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get suggestion for phrase by AI
 * @param {string} idSegment
 * @param {string} words
 * @param {string} phrase
 * @param {string} [targetLanguage=config.target_code]
 * @param {string} [idClient=config.id_client]
 * @returns {Promise<object>}
 */

export const aiSuggestion = async ({
  idSegment,
  words,
  phrase,
  targetLanguage = config.target_code,
  idClient = config.id_client,
}) => {
  const dataParams = {
    id_segment: idSegment,
    target: targetLanguage,
    word: words,
    phrase,
    id_client: idClient,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })

  const response = await fetch(`${getMatecatApiDomain()}api/app/ai-assistant`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
