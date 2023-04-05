import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import TagUtils from '../../utils/tagUtils'

/**
 * Get concordance
 *
 * @param {string} query
 * @param {number} type
 * @param {string} [idJob=config.job_id]
 * @param {string} [idTranslator=config.id_translator]
 * @param {string} [password=config.password]
 * @param {string} [idClient=config.id_client]
 * @param {string} [currentPassword=config.currentPassword]
 * @returns {Promise<object>}
 */
export const getConcordance = async (
  query,
  type,
  idJob = config.job_id,
  password = config.password,
  idClient = config.id_client,
  currentPassword = config.currentPassword,
) => {
  const dataParams = {
    action: 'getContribution',
    is_concordance: 1,
    from_target: type,
    id_segment: UI.currentSegmentId,
    text: TagUtils.prepareTextToSend(query),
    id_job: idJob,
    num_results: UI.numMatchesResults,
    password: password,
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
