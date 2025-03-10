/**
 * Send email with the instructions to create a new password
 *
 * @param {string} email
 * @param {string} wantedUrl
 * @returns {Promise<object>}
 */
export const fileUploadDelete = async ({
  file,
  segmentationRule,
  source,
  filtersTemplate,
}) => {
  const response = await fetch(
    `/fileupload/?file=${file}&segmentationRule=${segmentationRule}&source=${source}&filtersTemplate=${filtersTemplate}`,
    {
      method: 'delete',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject({response})

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject({errors})
  return data
}
