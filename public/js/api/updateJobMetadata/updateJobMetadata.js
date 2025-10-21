import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Update job metadata
 *
 * @param {Object} options
 * @param {string} [options.idJob=config.id_job]
 * @param {string} [options.password=config.password]
 * @param {boolean} options.tmPrioritization
 * @param {boolean} options.characterCounterCountTags
 * @param {string} options.characterCounterMode
 * @returns {Promise<object>}
 */
export const updateJobMetadata = async ({
  idJob = config.id_job,
  password = config.password,
  tmPrioritization,
  characterCounterCountTags,
  characterCounterMode,
  subfilteringHandlers,
}) => {
  const paramsData = Object.entries({
    tm_prioritization: typeof tmPrioritization === 'boolean' ? tmPrioritization : undefined,
    character_counter_count_tags: typeof characterCounterCountTags === 'boolean' ? characterCounterCountTags : undefined,
    character_counter_mode: characterCounterMode,
    subfiltering_handlers: subfilteringHandlers,
  })
    .filter(([, value]) => typeof value !== 'undefined')
    .map(([key, value]) => ({key, value}))

  const response = await fetch(
    `${getMatecatApiDomain()}api/app/jobs/${idJob}/${password}/metadata`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(paramsData),
      credentials: 'include',
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)
  return data
}
