import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Enable or disable tag speech to text
 *
 * @param {Object} options
 * @param {boolean} options.enabled
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @returns {Promise<object>}
 */
export const toggleSpeechToText = async ({
  enabled,
  idJob = config.id_job,
  password = config.password,
}) => {
  const dataParams = {
    speech2text: enabled,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/jobs/${idJob}/${password}/options`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

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
