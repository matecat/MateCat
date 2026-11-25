import {NUM_CONCORDANCE_RESULTS} from '../../constants/Constants'
import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import SegmentStore from '../../stores/SegmentStore'

/**
 * Get concordance
 *
 * @param {string} query
 * @param {number} type
 * @param {string} [idJob=config.id_job]
 * @param {string} [idTranslator=config.id_translator]
 * @param {string} [currentPassword=config.currentPassword]
 * @param {string} [idClient=config.id_client]
 * @returns {Promise<object>}
 */
export const getConcordance = async (
  query,
  type,
  idJob = config.id_job,
  currentPassword = config.currentPassword,
  idClient = config.id_client,
) => {
  const dataParams = {
    is_concordance: 1,
    from_target: type,
    id_segment: SegmentStore.getCurrentSegmentId(),
    text: query,
    id_job: idJob,
    num_results: NUM_CONCORDANCE_RESULTS,
    password: currentPassword,
    id_client: idClient,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
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
