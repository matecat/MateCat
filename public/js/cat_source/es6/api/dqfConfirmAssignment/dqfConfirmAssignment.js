import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Assign DQF project yourself
 *
 * @param {Object} options
 * @param {string} [options.idJob=config.id_job]
 * @param {string} options.password
 * @returns {Promise<object>}
 */
export const dqfConfirmAssignment = async ({
  idJob = config.id_job,
  password,
}) => {
  const response = await fetch(
    `${getMatecatApiDomain()}api/app/dqf/jobs/${idJob}/${password}/assign`,
    {
      method: 'POST',
      credentials: 'include',
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
