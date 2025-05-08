import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Clear the uploaded files when an user refresh the home page
 * called in main.js
 *
 * @param {number} [time=newDate.getTime()]
 * @returns {Promise<object>}
 */
export const clearNotCompletedUploads = async (time = new Date().getTime()) => {
  const paramsData = {}
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/clear-not-completed-uploads`,
    {
      method: 'POST',
      body: formData,
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
