/**
 * PEE get data table
 *
 * @param {string} date
 * @returns {Promise<object>}
 */
export const peeDataTable = async (date) => {
  const dataParams = {
    date,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    if (dataParams[key] !== undefined) formData.append(key, dataParams[key])
  })

  const response = await fetch(`/api/app/utils/pee/table`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({response, errors: data.errors ?? data})
    } else {
      return Promise.reject({response})
    }
  }

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject({response, errors})
  return data
}
