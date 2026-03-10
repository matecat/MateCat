import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Download Google Drive translated file
 *
 * @param {number} openOriginalFiles
 * @param {string} idJob
 * @param {string} password
 * @param checkErrors
 * @param {string} downloadToken
 * @returns {Promise<object>}
 */
export const downloadFileGDrive = async (
  openOriginalFiles,
  idJob,
  password,
  checkErrors = true,
  downloadToken,
) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/translation/${idJob}/${password}?original=${openOriginalFiles}&downloadToken=${downloadToken}&disableErrorCheck=${checkErrors ? 0 : 1}`,
    {
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
