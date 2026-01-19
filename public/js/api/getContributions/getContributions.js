import {NUM_CONTRIBUTION_RESULTS} from '../../constants/Constants'
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
  translation,
  crossLanguages,
  idJob = config.id_job,
  password = config.password,
  idClient = config.id_client,
  currentPassword = config.currentPassword,
  contextListBefore,
  contextListAfter,
}) => {
  const contextBefore = globalFunctions.getContextBefore(idSegment)
  const idBefore = globalFunctions.getIdBefore(idSegment)
  const contextAfter = globalFunctions.getContextAfter(idSegment)
  const idAfter = globalFunctions.getIdAfter(idSegment)

  const obj = {
    password: password,
    is_concordance: 0,
    id_segment: idSegment,
    text: target,
    translation,
    id_job: idJob,
    num_results: NUM_CONTRIBUTION_RESULTS,
    context_before: contextBefore ? contextBefore : '',
    id_before: idBefore ? idBefore : '',
    context_after: contextAfter,
    id_after: idAfter,
    id_client: idClient,
    cross_language: crossLanguages,
    current_password: currentPassword,
    context_list_before: JSON.stringify(contextListBefore),
    context_list_after: JSON.stringify(contextListAfter),
  }
  const dataParams = Object.fromEntries(
    Object.entries(obj).filter(([_, v]) => v != null),
  )

  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/get-contribution`,
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
