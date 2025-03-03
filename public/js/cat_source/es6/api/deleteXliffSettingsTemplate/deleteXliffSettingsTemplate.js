import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * delete specific xliff settings template
 *
 * @param {integer} id
 * @returns {Promise<object>}
 */
export const deleteXliffSettingsTemplate = async (id) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/xliff-config-template/${id}`,
    {
      method: 'DELETE',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
