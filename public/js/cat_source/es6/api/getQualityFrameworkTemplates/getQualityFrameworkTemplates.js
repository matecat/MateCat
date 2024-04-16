import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch list of quality framework templates
 *
 * @returns {Promise<object>}
 */
export const getQualityFrameworkTemplates = async () => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/qa_model_template`,
    {
      method: 'GET',
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
