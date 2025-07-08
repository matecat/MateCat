import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Export CSV from quality report page
 *
 * @param {Object} options
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @param {string} [format='csv']
 * @returns {Promise<object>}
 */
export const exportQualityReport = async ({
  idJob = config.id_job,
  password = config.password,
  format = 'csv',
} = {}) => {
  const dataParams = {
    jid: idJob,
    password,
    format,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(`${window.origin}/api/v3/qr/download`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })
  if (!response.ok) return Promise.reject(response)
  const blob = await response.blob()
  const temp = response.headers.get('Content-Disposition').split('filename')[1]
  return {
    blob,
    filename: temp.substring(temp.indexOf('"') + 1, temp.lastIndexOf('"')),
  }
}
