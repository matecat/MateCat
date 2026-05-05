import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get alternative translations by AI (will receive response socket channel)
 * @param {string} [sourceLanguage=config.source_code]
 * @param {string} [targetLanguage=config.target_code]
 * @param {string} [idClient=config.id_client]
 * @param {string} sourceSentence
 * @param {string} sourceContextSentencesString
 * @param {string} targetSentence
 * @param {string} targetContextSentencesString
 * @param {string} excerpt
 * @param {string} styleInstructions
 * @returns {Promise<object>}
 */

export const aiAlternartiveTranslations = async ({
  id_job = config.id_job,
  password = config.password,
  sourceLanguage = config.source_code,
  targetLanguage = config.target_code,
  idClient = config.id_client,
  idSegment,
  sourceSentence,
  sourceContextSentencesString,
  targetSentence,
  targetContextSentencesString,
  excerpt,
  styleInstructions,
}) => {
  const dataParams = {
    id_job: id_job,
    password: password,
    source_language: sourceLanguage,
    target_language: targetLanguage,
    id_client: idClient,
    id_segment: idSegment,
    source_sentence: sourceSentence,
    target_sentence: targetSentence,
    source_context_sentences_string: sourceContextSentencesString,
    target_context_sentences_string: targetContextSentencesString,
    excerpt,
    style_instructions: styleInstructions,
  }

  const response = await fetch(
    `${getMatecatApiDomain()}api/app/ai-assistant/alternative-translations`,
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
