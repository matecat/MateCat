import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get feedback for translation by AI (will receive response socket channel)
 * @param {string} [sourceLanguage=config.source_code]
 * @param {string} [targetLanguage=config.target_code]
 * @param {string} [idClient=config.id_client]
 * @param {string} id_segment
 * @param {string} text
 * @param {string} translation
 * @param {string} style
 * @returns {Promise<object>}
 */

export const aiFeedback = async ({
  sourceLanguage = config.source_code,
  targetLanguage = config.target_code,
  idClient = config.id_client,
  idSegment,
  source,
  target,
  style,
}) => {
  const dataParams = {
    source_language: sourceLanguage,
    target_language: targetLanguage,
    id_client: idClient,
    id_segment: idSegment,
    text: source,
    translation: target,
    style,
  }

  const response = await fetch(
    `${getMatecatApiDomain()}api/app/ai-assistant/feedback`,
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
