import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain';

/**
 * Replace all terms into segments
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
export const replaceAllIntoSegments = async ({
  idJob = config.id_job,
  password = config.currentPassword,
  token,
  source,
  target,
  status,
  matchcase,
  exactmatch,
  replace,
  includeLocked,
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
    replace,
    inCurrentChunkOnly: true, // replace is fixed in context of current chunk
    includeLocked,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}api/app/replace-all`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
