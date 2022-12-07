import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Remove glossary item
 *
 * @param {Object} dataRequest
 * @returns {Promise<object>}
 */
export const deleteGlossaryItem = async (dataRequest) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/glossary/_delete`,
    {
      method: 'POST',
      credentials: 'include',
      body: JSON.stringify(dataRequest),
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
