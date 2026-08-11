import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain';

/**
 * Search term into segments
 *
 * @param {Object} options
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {string} options.token
 * @param {string} options.source
 * @param {string} options.target
 * @param {string} options.status
 * @param {boolean} options.matchcase
 * @param {boolean} options.exactmatch
 * @param {string} options.replace
 * @returns {Promise<object>}
 */
export const searchTermIntoSegments = async ({
  idJob = config.id_job,
  password = config.currentPassword,
  token,
  source,
  target,
  status,
  matchcase,
  exactmatch,
  inCurrentChunkOnly,
  replace,
}) => {
  // No revision_number: the phase is derived server side from this password.
  const paramsData = {
    id_job: idJob,
    password,
    token,
    source,
    target,
    status,
    matchcase,
    exactmatch,
    inCurrentChunkOnly: inCurrentChunkOnly,
    replace,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}api/app/search`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
