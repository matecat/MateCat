import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * delete specific quality framework template
 *
 * @param {integer} id
 * @returns {Promise<object>}
 */
export const deleteQualityFrameworkTemplate = async (id) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/qa_model_template/${id}`,
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
