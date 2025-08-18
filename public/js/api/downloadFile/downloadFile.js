import {saveAs} from 'file-saver'
// import {Base64} from 'js-base64'

/**
 * Export TMX
 *
 * @param {Object} options
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @returns {Promise<object>}
 */
export const downloadFile = async ({
  idJob = config.id_job,
  password = config.password,
}) => {
  const response = await fetch(
    `${config.basepath}api/v2/translation/${idJob}/${password}?download_type=all&encoding=base64`,
    {
      credentials: 'include',
    },
  )
  if (!response.ok) return Promise.reject(response)
  const header = response.headers.get('Content-Disposition')
  const parts = header.split(';')
  let filename = parts[1].split('=')[1]
  filename = filename.replace(/"/g, '')
  const blob = await response.blob()
  saveAs(blob, filename)
  return Promise.resolve()
}
