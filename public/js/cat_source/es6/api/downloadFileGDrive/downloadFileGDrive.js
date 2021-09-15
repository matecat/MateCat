import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Download Google Drive translated file
 *
 * @param {number} openOriginalFiles
 * @param {string} idJob
 * @param {string} password
 * @param {string} downloadToken
 * @returns {Promise<object>}
 */
export const downloadFileGDrive = async (
  openOriginalFiles,
  idJob,
  password,
  downloadToken,
) => {
  const response = await fetch(
    `${getMatecatApiDomain()}?action=downloadFile&id_job=${idJob}&password=${password}&original=${openOriginalFiles}&downloadToken=${downloadToken}`,
    {
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
