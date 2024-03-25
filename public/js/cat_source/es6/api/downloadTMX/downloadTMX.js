import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Export TMX
 *
 * @param {Object} options
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {string} options.key
 * @param {string} options.name
 * @param {string} options.email
 * @returns {Promise<object>}
 */
export const downloadTMX = async ({
  idJob = config.id_job,
  password = config.password,
  key,
  name,
  stripTags,
}) => {
  const paramsData = {
    action: 'downloadTMX',
    tm_key: key,
    tm_name: name,
    strip_tags: stripTags,
    ...(idJob && {id_job: idJob}),
    ...(idJob && {password}),
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}?action=${paramsData.action}`,
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
