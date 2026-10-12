import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Delete contribution
 *
 * @param {Object} options
 * @param {string} options.source
 * @param {string} options.target
 * @param {number} options.id
 * @param {string} [sourceLanguage=config.source_code]
 * @param {string} [targetLanguage=config.target_code]
 * @param {string} [idJob=config.id_job]
 * @param {string} [currentPassword=config.currentPassword]
 * @param {string} [idTranslator=config.id_translator]
 * @returns {Promise<object>}
 */
export const deleteContribution = async ({
  source,
  target,
  id,
  sourceLanguage = config.source_code,
  targetLanguage = config.target_code,
  idJob = config.id_job,
  currentPassword = config.currentPassword,
  idTranslator = config.id_translator,
  sid,
}) => {
  const dataParams = {
    source_lang: sourceLanguage,
    target_lang: targetLanguage,
    id_job: idJob,
    seg: source,
    tra: target,
    id_translator: idTranslator,
    id_match: id,
    password: currentPassword,
    id_segment: sid,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/delete-contribution`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
