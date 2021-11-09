import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Export CSV from quality report page
 *
 * @param {Object} options
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */
export const exportCsv = async ({
  idJob = config.id_job,
  password = config.password,
} = {}) => {
  const dataParams = {
    jid: idJob,
    password,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(`${getMatecatApiDomain()}api/v3/qr/download`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })
  if (!response.ok) return Promise.reject(response)
  const blob = await response.blob()
  return blob
}
