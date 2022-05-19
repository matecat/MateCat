import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Delete completion events
 *
 * @param {Object} options
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {string} [options.lastCompletionEventId=config.last_completion_event_id]
 * @returns {Promise<object>}
 */
export const deleteCompletionEvents = async () => {
  const idJob = config.id_job
  const password = config.password
  const lastCompletionEventId = config.last_completion_event_id
  const response = await fetch(
    `/api/app/jobs/${idJob}/${password}/completion-events/${lastCompletionEventId}`,
    {
      method: 'DELETE',
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
