import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
/**
 * Get contributions
 *
 * @param {Object} options
 * @param {string} options.idSegment
 * @param {string} options.target
 * @param {Array} options.crossLanguages
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {string} [options.idTranslator=config.id_translator]
 * @param {string} [options.idClient=config.id_client]
 * @param {string} [options.currentPassword=config.currentPassword]
 * @returns {Promise<object>}
 */
export const getContributions = async ({
  idSegment,
  target,
  crossLanguages,
  idJob = config.id_job,
  password = config.password,
  idClient = config.id_client,
  currentPassword = config.currentPassword,
}) => {
  const contextBefore = UI.getContextBefore(idSegment)
  const idBefore = UI.getIdBefore(idSegment)
  const contextAfter = UI.getContextAfter(idSegment)
  const idAfter = UI.getIdAfter(idSegment)
  const txt = target

  const obj = {
    action: 'getContribution',
    password: password,
    is_concordance: 0,
    id_segment: idSegment,
    text: txt,
    id_job: idJob,
    num_results: UI.numContributionMatchesResults,
    context_before: contextBefore ? contextBefore : '',
    id_before: idBefore ? idBefore : '',
    context_after: contextAfter,
    id_after: idAfter,
    id_client: idClient,
    cross_language: crossLanguages,
    current_password: currentPassword,
  }
  const dataParams = Object.fromEntries(
    Object.entries(obj).filter(([_, v]) => v != null),
  )

  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}?action=getContribution`,
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
