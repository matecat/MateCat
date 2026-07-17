/**
 * Google drive connected services
 *
 * @param {number} id
 * @returns {Promise<object>}
 */
export const connectedServicesGDrive = async (id) => {
  const paramsData = {
    disabled: true,
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(`/api/app/connected_services/${id}`, {
    method: 'POST',
    body: formData,
    credentials: 'include',
  })

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
