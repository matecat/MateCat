import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Upload glossary files
 *
 * @param {Object} options
 * @param {string} [options.filesToUpload=[]]
 * @param {string} options.tmKey
 * @param {string} options.keyName
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @returns {Promise<object>}
 */
export const uploadGlossary = async ({
  filesToUpload = [],
  tmKey,
  keyName,
  idJob = config.id_job,
  password = config.password,
}) => {
  const paramsData = {
    tm_key: tmKey,
    name: keyName,
    r: '1',
    w: '1',
    ...(config.is_cattool && {job_id: idJob, job_pass: password}),
  }
  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })
  filesToUpload.forEach((file) =>
    formData.append('uploaded_file[]', file, file.name),
  )

  const response = await fetch(
    `${getMatecatApiDomain()}api/v2/glossaries/import/`,
    {
      method: 'POST',
      body: formData,
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
