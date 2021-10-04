import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Remove glossary item
 *
 * @param {string} idSegment
 * @param {string} source
 * @param {string} target
 * @param {string|Array} id
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @param {string} [idClient=config.id_client]
 * @returns {Promise<object>}
 */
export const deleteGlossaryItem = async (
  idSegment,
  source,
  target,
  id,
  idJob = config.id_job,
  password = config.password,
  idClient = config.id_client,
) => {
  const dataParams = {
    exec: 'delete',
    segment: source,
    translation: target,
    id_job: idJob,
    password: password,
    id: Array.isArray(id) ? id : [id],
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
