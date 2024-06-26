import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Fetch list of project templates
 *
 * @returns {Promise<object>}
 */
export const getProjectTemplates = async () => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/project-template/`,
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
