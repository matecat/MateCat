import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Delete contribution
 *
 * @param {Object} options
 * @param {string} options.source
 * @param {string} options.target
 * @param {number} options.id
 * @param {string} [sourceLanguage=config.source_rfc]
 * @param {string} [targetLanguage=config.target_rfc]
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @param {string} [currentPassword=config.currentPassword]
 * @param {string} [idTranslator=config.id_translator]
 * @returns {Promise<object>}
 */
export const deleteContribution = async ({
  source,
  target,
  id,
  sourceLanguage = config.source_rfc,
  targetLanguage = config.target_rfc,
  idJob = config.id_job,
  password = config.password,
  currentPassword = config.currentPassword,
  idTranslator = config.id_translator,
}) => {
  const dataParams = {
    action: 'deleteContribution',
    source_lang: sourceLanguage,
    target_lang: targetLanguage,
    id_job: idJob,
    password: password,
    seg: source,
    tra: target,
    id_translator: idTranslator,
    id_match: id,
    current_password: currentPassword,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}?action=deleteContribution`,
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
