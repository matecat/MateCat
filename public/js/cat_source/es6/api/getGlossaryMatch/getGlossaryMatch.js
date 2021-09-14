import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Retrieve glossary match
 *
 * @param {string} idSegment
 * @param {string} source
 * @param {string} fromTarget
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @param {string} [currentPassword=config.currentPassword]
 * @param {string} [idClient=config.id_client]
 * @returns {Promise<object>}
 */
export const getGlossaryMatch = async (
  idSegment,
  source,
  fromTarget,
  idJob = config.id_job,
  password = config.password,
  currentPassword = config.currentPassword,
  idClient = config.id_client,
) => {
  const dataParams = {
    action: 'glossary',
    exec: 'get',
    segment: source,
    automatic: false,
    translation: null,
    from_target: fromTarget,
    id_job: idJob,
    password: password,
    current_password: currentPassword,
    id_client: idClient,
    id_segment: idSegment,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}?action=glossary`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
