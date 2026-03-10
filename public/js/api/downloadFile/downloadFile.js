import {saveAs} from 'file-saver'
// import {Base64} from 'js-base64'

/**
 * Export TMX
 *
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @param checkErrors
 * @returns {Promise<object>}
 */
export const downloadFile = async ({
  idJob = config.id_job,
  password = config.password,
  checkErrors = true,
}) => {
  const response = await fetch(
    `${config.basepath}api/v2/translation/${idJob}/${password}?download_type=all&encoding=base64&disableErrorCheck=${checkErrors ? 0 : 1}`,
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
