import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * modify existing project template
 *
 * @param {number} id
 * @param {Object} template
 * @returns {Promise<object>}
 */
export const updateProjectTemplate = async ({id, template}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/v3/project-template/${id}`,
    {
      method: 'PUT',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(template),
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
