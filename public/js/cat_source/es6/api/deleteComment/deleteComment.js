import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Delete user comment
 *
 * @param {Object} options
 * @param {string} options.idComment
 * @param {string} options.idSegment
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {string} [options.sourcePage=config.revisionNumber]
 * @param {string} [options.idClient=config.id_client]
 * @returns {Promise<object>}
 */
export const deleteComment = async ({
  idComment,
  idSegment,
  idJob = config.id_job,
  password = config.password,
  sourcePage = config.revisionNumber,
}) => {
  const dataParams = {
    _sub: 'delete',
    id_comment: idComment,
    id_segment: idSegment,
    id_job: idJob,
    password,
    source_page: sourcePage ? sourcePage + 1 : 1,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}?action=comment`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
