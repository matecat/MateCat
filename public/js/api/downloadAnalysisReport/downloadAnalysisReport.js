import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

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
  const contentDisposition = response.headers.get('Content-Disposition')
  let filename = 'analysis-report.zip'
  if (contentDisposition) {
    const match = contentDisposition.match(/filename="?([^";\n]+)"?/)
    if (match?.[1]) {
      filename = match[1]
    }
  }

  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
