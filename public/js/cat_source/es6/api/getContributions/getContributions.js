import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Get contributions - NOTE: actually this ajax request
 * is dispatch from APP.doRequest.
 *
 * @param {string} idSegment
 * @param {string} target
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @param {string} [idTranslator=config.id_translator]
 * @param {string} [idClient=config.id_client]
 * @param {string} [currentPassword=config.currentPassword]
 * @returns {Promise<object>}
 */
export const getContributions = async (
  idSegment,
  target,
  idJob = config.id_job,
  password = config.password,
  idTranslator = config.id_translator,
  idClient = config.id_client,
  currentPassword = config.currentPassword,
) => {
  const contextBefore = UI.getContextBefore(idSegment)
  const idBefore = UI.getIdBefore(idSegment)
  const contextAfter = UI.getContextAfter(idSegment)
  const idAfter = UI.getIdAfter(idSegment)
  const txt = TagUtils.prepareTextToSend(target)

  const dataParams = {
    action: 'getContribution',
    password: password,
    is_concordance: 0,
    id_segment: idSegment,
    text: txt,
    id_job: idJob,
    num_results: UI.numContributionMatchesResults,
    id_translator: idTranslator,
    context_before: contextBefore,
    id_before: idBefore,
    context_after: contextAfter,
    id_after: idAfter,
    id_client: idClient,
    current_password: currentPassword,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
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
