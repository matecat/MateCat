import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'
import {getFilenameFromContentDisposition} from '../../utils/contentDisposition'

/**
 * Download Analysis Report
 *
 * @param {Object} options
 * @param {string} options.idProject
 * @param {string} options.password
 * @param {string} [options.downloadType='XTRF']
 * @returns {Promise<void>}
 */
export const downloadAnalysisReport = async ({
  idProject,
  password,
  downloadType = 'XTRF',
}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/download-analysis-report`,
    {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        id_project: idProject,
        password,
        download_type: downloadType,
      }),
    },
  )

  if (!response.ok) return Promise.reject(response)

  const blob = await response.blob()
  const filename = getFilenameFromContentDisposition(
    response.headers.get('Content-Disposition'),
    'analysis-report.zip',
  )

  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
